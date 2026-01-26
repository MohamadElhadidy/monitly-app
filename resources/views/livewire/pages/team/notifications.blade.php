<?php

use App\Models\NotificationChannel;
use App\Models\Team;
use App\Models\WebhookEndpoint;
use App\Services\Security\SsrfGuard;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Team Notifications')]
class extends Component
{
    public Team $team;

    public NotificationChannel $channel;

    public string $slack_webhook_url_input = '';
    public bool $slack_enabled = false;
    public bool $webhooks_enabled = false;

    public string $new_endpoint_url = '';
    public bool $new_endpoint_enabled = true;

    public ?string $generated_secret = null;

    public ?string $flashSuccess = null;
    public ?string $flashError = null;

    public function mount(Team $team): void
    {
        $this->team = $team;

        abort_unless($this->userCanManageTeam(), 403);

        // Team plan only
        abort_unless(strtolower((string) $this->team->billing_plan) === 'team', 403);

        $this->channel = NotificationChannel::query()->firstOrCreate(
            ['team_id' => $this->team->id],
            [
                'email_enabled' => true,
                'slack_enabled' => false,
                'webhooks_enabled' => false,
            ]
        );

        $this->slack_enabled = (bool) $this->channel->slack_enabled;
        $this->webhooks_enabled = (bool) $this->channel->webhooks_enabled;

        $this->slack_webhook_url_input = '';
    }

    private function userCanManageTeam(): bool
    {
        $u = auth()->user();

        if (! $u) return false;

        if ($u->ownsTeam($this->team)) return true;

        // Jetstream membership role
        return $u->hasTeamRole($this->team, 'admin');
    }

    public function saveChannelSettings(): void
    {
        abort_unless($this->userCanManageTeam(), 403);
        abort_unless(strtolower((string) $this->team->billing_plan) === 'team', 403);

        $this->validate([
            'slack_enabled' => ['boolean'],
            'webhooks_enabled' => ['boolean'],
            'slack_webhook_url_input' => ['nullable', 'string', 'max:2000'],
        ], [
            'slack_webhook_url_input.max' => 'Slack webhook URL is too long.',
        ]);

        if ($this->slack_enabled) {
            $input = trim($this->slack_webhook_url_input);

            // If enabling Slack, require webhook URL if none exists already
            if (($this->channel->slack_webhook_url ?? null) === null && $input === '') {
                $this->flashError = 'Slack webhook URL is required to enable Slack.';
                $this->flashSuccess = null;
                return;
            }

            if ($input !== '') {
                // Slack incoming webhook safety checks
                $parts = @parse_url($input);
                $scheme = strtolower((string) ($parts['scheme'] ?? ''));
                $host = strtolower((string) ($parts['host'] ?? ''));

                if (! in_array($scheme, ['https'], true)) {
                    $this->flashError = 'Slack webhook must use HTTPS.';
                    $this->flashSuccess = null;
                    return;
                }

                if ($host !== 'hooks.slack.com') {
                    $this->flashError = 'Slack webhook host must be hooks.slack.com.';
                    $this->flashSuccess = null;
                    return;
                }

                $this->channel->slack_webhook_url = $input; // encrypted cast
            }
        }

        if (! $this->slack_enabled) {
            // Keep URL stored (so user can re-enable easily) but disable sending
        }

        $this->channel->slack_enabled = (bool) $this->slack_enabled;
        $this->channel->webhooks_enabled = (bool) $this->webhooks_enabled;
        $this->channel->save();

        $this->slack_webhook_url_input = '';
        $this->flashSuccess = 'Notification settings saved.';
        $this->flashError = null;
    }

    public function createEndpoint(SsrfGuard $ssrfGuard): void
    {
        abort_unless($this->userCanManageTeam(), 403);
        abort_unless(strtolower((string) $this->team->billing_plan) === 'team', 403);

        $this->validate([
            'new_endpoint_url' => ['required', 'string', 'max:2000'],
            'new_endpoint_enabled' => ['boolean'],
        ]);

        $url = trim($this->new_endpoint_url);

        // Only http/https + block private networks
        $ssrfGuard->validateUrl($url);

        $secret = Str::random(40);

        $ep = new WebhookEndpoint();
        $ep->team_id = $this->team->id;
        $ep->url = $url;
        $ep->secret = $secret; // encrypted cast
        $ep->enabled = (bool) $this->new_endpoint_enabled;
        $ep->last_error = null;
        $ep->retry_meta = null;
        $ep->save();

        $this->generated_secret = $secret; // show once
        $this->new_endpoint_url = '';
        $this->new_endpoint_enabled = true;

        $this->flashSuccess = 'Webhook endpoint created. Copy the secret now (it will be masked later).';
        $this->flashError = null;
    }

    public function toggleEndpoint(int $endpointId): void
    {
        abort_unless($this->userCanManageTeam(), 403);

        $ep = WebhookEndpoint::query()
            ->where('team_id', $this->team->id)
            ->findOrFail($endpointId);

        $ep->enabled = ! (bool) $ep->enabled;
        $ep->save();

        $this->flashSuccess = $ep->enabled ? 'Endpoint enabled.' : 'Endpoint disabled.';
        $this->flashError = null;
    }

    public function deleteEndpoint(int $endpointId): void
    {
        abort_unless($this->userCanManageTeam(), 403);

        $ep = WebhookEndpoint::query()
            ->where('team_id', $this->team->id)
            ->findOrFail($endpointId);

        $ep->delete();

        $this->flashSuccess = 'Endpoint deleted.';
        $this->flashError = null;
    }

    public function regenerateSecret(int $endpointId): void
    {
        abort_unless($this->userCanManageTeam(), 403);

        $ep = WebhookEndpoint::query()
            ->where('team_id', $this->team->id)
            ->findOrFail($endpointId);

        $secret = Str::random(40);
        $ep->secret = $secret; // encrypted cast
        $ep->save();

        $this->generated_secret = $secret;

        $this->flashSuccess = 'Secret regenerated. Copy the new secret now.';
        $this->flashError = null;
    }

    public function with(): array
    {
        $endpoints = WebhookEndpoint::query()
            ->where('team_id', $this->team->id)
            ->orderByDesc('id')
            ->get();

        return [
            'endpoints' => $endpoints,
            'maskedSlack' => $this->maskSlack((string) ($this->channel->slack_webhook_url ?? '')),
        ];
    }

    private function maskSlack(string $url): string
    {
        $url = trim($url);
        if ($url === '') return 'Not set';

        $keep = 10;
        $len = mb_strlen($url);
        if ($len <= $keep) return str_repeat('*', $len);

        $tail = mb_substr($url, $len - $keep);
        return '***' . $tail;
    }

    private function maskSecret(string $secret): string
    {
        $secret = trim($secret);
        if ($secret === '') return 'Not set';
        $tail = mb_substr($secret, -6);
        return '***' . $tail;
    }
};
?>

<div class="space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-xl font-semibold text-slate-900">Team Notifications</h1>
            <p class="mt-1 text-sm text-slate-600">
                Manage Slack + webhook notifications for <span class="font-medium text-slate-900">{{ $team->name }}</span>.
                These features are available only on the <span class="font-medium text-slate-900">Team</span> plan.
            </p>
        </div>

        <a href="{{ route('dashboard') }}"
           class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Back
        </a>
    </div>

    @if ($flashSuccess)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 shadow-sm p-6">
            <div class="text-sm font-semibold text-emerald-800">Success</div>
            <div class="mt-1 text-sm text-emerald-700">{{ $flashSuccess }}</div>
        </div>
    @endif

    @if ($flashError)
        <div class="rounded-xl border border-rose-200 bg-rose-50 shadow-sm p-6">
            <div class="text-sm font-semibold text-rose-800">Error</div>
            <div class="mt-1 text-sm text-rose-700">{{ $flashError }}</div>
        </div>
    @endif

    <x-ui.card title="Slack" description="Send DOWN/RECOVERED alerts to a Slack channel via incoming webhook. URL is stored encrypted and masked here.">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" wire:model="slack_enabled"
                           class="rounded border-slate-200 focus:border-slate-900 focus:ring-slate-900">
                    <span class="text-sm text-slate-700 font-medium">Enable Slack alerts</span>
                </label>

                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="text-xs text-slate-500">Stored webhook URL (masked)</div>
                    <div class="mt-1 text-sm font-medium text-slate-900 break-all">{{ $maskedSlack }}</div>
                    @if ($channel->slack_last_error)
                        <div class="mt-3 text-xs text-rose-700">Last error: {{ $channel->slack_last_error }}</div>
                    @endif
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Replace webhook URL (optional)</label>
                    <input
                        type="text"
                        wire:model.defer="slack_webhook_url_input"
                        placeholder="https://hooks.slack.com/services/..."
                        class="mt-2 w-full rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900"
                    >
                    <p class="mt-2 text-sm text-slate-600">
                        Tip: leave blank to keep existing URL.
                    </p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <div class="text-sm font-semibold text-slate-900">What gets sent</div>
                <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc pl-5">
                    <li>Only on <span class="font-medium text-slate-900">DOWN</span> and <span class="font-medium text-slate-900">RECOVERED</span></li>
                    <li>Deduplicated by state transitions (no spam)</li>
                    <li>Respects monitor-level Slack toggle + member receive_alerts permissions</li>
                    <li>Queued delivery with retry + last error stored</li>
                </ul>
            </div>
        </div>

        <div class="mt-6">
            <x-ui.button wire:click="saveChannelSettings" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="saveChannelSettings">Save Slack settings</span>
                <span wire:loading wire:target="saveChannelSettings">Saving…</span>
            </x-ui.button>
        </div>
    </x-ui.card>

    <x-ui.card title="Webhooks" description="POST signed events to your endpoints on DOWN/RECOVERED. Endpoints + secrets are stored encrypted; secrets are shown only on creation/regeneration.">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" wire:model="webhooks_enabled"
                   class="rounded border-slate-200 focus:border-slate-900 focus:ring-slate-900">
            <span class="text-sm text-slate-700 font-medium">Enable webhook delivery</span>
        </label>

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div>
                <div class="text-sm font-semibold text-slate-900">Add endpoint</div>
                <div class="mt-3 space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Endpoint URL</label>
                        <input type="text"
                               wire:model.defer="new_endpoint_url"
                               placeholder="https://your-app.com/monitly/webhooks"
                               class="mt-2 w-full rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900">
                        <p class="mt-2 text-sm text-slate-600">
                            Only http/https allowed. Private networks are blocked for safety.
                        </p>
                    </div>

                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model.defer="new_endpoint_enabled"
                               class="rounded border-slate-200 focus:border-slate-900 focus:ring-slate-900">
                        <span class="text-sm text-slate-700">Enabled</span>
                    </label>

                    <div class="flex items-center gap-2">
                        <x-ui.button wire:click="createEndpoint" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="createEndpoint">Create endpoint</span>
                            <span wire:loading wire:target="createEndpoint">Creating…</span>
                        </x-ui.button>

                        <x-ui.button.secondary wire:click="saveChannelSettings" wire:loading.attr="disabled">
                            Save webhooks toggle
                        </x-ui.button.secondary>
                    </div>

                    @if ($generated_secret)
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <div class="text-sm font-semibold text-amber-900">Webhook secret (copy now)</div>
                            <div class="mt-2 font-mono text-sm text-amber-900 break-all">{{ $generated_secret }}</div>
                            <div class="mt-2 text-sm text-amber-800">
                                This secret will be masked later. Use it to verify signatures.
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <div class="text-sm font-semibold text-slate-900">Signature verification</div>
                <div class="mt-3 text-sm text-slate-600 space-y-2">
                    <div>Headers sent:</div>
                    <ul class="list-disc pl-5 space-y-1">
                        <li><span class="font-mono text-slate-900">X-Monitly-Event</span></li>
                        <li><span class="font-mono text-slate-900">X-Monitly-Timestamp</span></li>
                        <li><span class="font-mono text-slate-900">X-Monitly-Signature</span> (HMAC SHA-256)</li>
                    </ul>
                    <div class="mt-2">
                        Signature base string:
                        <div class="mt-1 font-mono text-xs text-slate-900 break-all">timestamp + "." + raw_body</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8">
            <div class="text-sm font-semibold text-slate-900">Endpoints</div>

            <div wire:loading class="mt-4 space-y-3">
                @for ($i=0; $i<4; $i++)
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="h-4 w-80 bg-slate-100 rounded"></div>
                        <div class="mt-2 h-3 w-96 bg-slate-100 rounded"></div>
                    </div>
                @endfor
            </div>

            <div wire:loading.remove class="mt-4">
                @if ($endpoints->count() === 0)
                    <x-ui.empty-state title="No webhook endpoints" description="Add an endpoint to receive DOWN/RECOVERED events.">
                        <x-slot:icon>
                            <svg class="h-6 w-6 text-slate-700" viewBox="0 0 24 24" fill="none">
                                <path d="M7 7h10v10H7V7Z" stroke="currentColor" stroke-width="2"/>
                                <path d="M4 12h3M17 12h3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M12 4v3M12 17v3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </x-slot:icon>
                    </x-ui.empty-state>
                @else
                    <x-ui.table>
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">URL</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Enabled</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Last error</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @foreach ($endpoints as $ep)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3">
                                        <div class="text-sm font-medium text-slate-900 break-all">{{ $ep->url }}</div>
                                        <div class="mt-1 text-xs text-slate-500">
                                            Secret: <span class="font-mono">***{{ mb_substr((string)$ep->secret, -6) }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        @if ($ep->enabled)
                                            <x-ui.badge variant="up">ON</x-ui.badge>
                                        @else
                                            <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset bg-slate-50 text-slate-700 ring-slate-200">OFF</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">
                                        {{ $ep->last_error ? $ep->last_error : '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <button type="button"
                                                    wire:click="toggleEndpoint({{ $ep->id }})"
                                                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                                {{ $ep->enabled ? 'Disable' : 'Enable' }}
                                            </button>
                                            <button type="button"
                                                    wire:click="regenerateSecret({{ $ep->id }})"
                                                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                                Regenerate secret
                                            </button>
                                            <button type="button"
                                                    wire:click="deleteEndpoint({{ $ep->id }})"
                                                    class="rounded-lg border border-rose-200 bg-white px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                @endif
            </div>
        </div>

        <div class="mt-6">
            <x-ui.button wire:click="saveChannelSettings" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="saveChannelSettings">Save Webhooks settings</span>
                <span wire:loading wire:target="saveChannelSettings">Saving…</span>
            </x-ui.button>
        </div>
    </x-ui.card>
</div>
