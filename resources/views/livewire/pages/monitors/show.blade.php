<?php

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorMemberPermission;
use App\Models\User;
use App\Services\Sla\SlaCalculator;
use App\Services\Sla\SlaTargetResolver;
use App\Services\Sla\MonitorSlaPdfReportService;
use App\Jobs\Sla\EvaluateMonitorSlaJob;
use Illuminate\Support\Arr;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Monitor')]
class extends Component
{
    use WithPagination;

    public Monitor $monitor;

    #[Url(as: 'tab', history: true)]
    public string $tab = 'overview';

    public array $grants = [];
    public array $teamUsers = [];

    public ?string $flashSuccess = null;
    public ?string $flashError = null;

    public ?string $slaDownloadUrl = null;

    public function mount(Monitor $monitor): void
    {
        $this->monitor = $monitor;

        abort_unless(auth()->user()->can('view', $this->monitor), 403);

        $allowedTabs = ['overview', 'checks', 'incidents', 'sla'];
        if (! in_array($this->tab, $allowedTabs, true)) {
            $this->tab = 'overview';
        }

        $this->monitor->loadMissing(['latestCheck', 'openIncident', 'team', 'owner']);

        if ($this->canManagePermissions()) {
            $this->hydrateTeamUsersAndGrants();
        }
    }

    public function updatedTab(): void
    {
        $allowedTabs = ['overview', 'checks', 'incidents', 'sla'];
        if (! in_array($this->tab, $allowedTabs, true)) {
            $this->tab = 'overview';
        }

        $this->resetPage('checksPage');
        $this->resetPage('incidentsPage');

        // clear transient SLA download url when switching tabs
        if ($this->tab !== 'sla') {
            $this->slaDownloadUrl = null;
        }
    }

    private function canManagePermissions(): bool
    {
        return auth()->user()->can('managePermissions', $this->monitor);
    }

    private function teamPlanAllowsIntegrations(): bool
    {
        return (bool) ($this->monitor->team_id && $this->monitor->team && strtolower((string) $this->monitor->team->billing_plan) === 'team');
    }

    private function hydrateTeamUsersAndGrants(): void
    {
        $team = $this->monitor->team;

        if (! $team) {
            $this->teamUsers = [];
            $this->grants = [];
            return;
        }

        $members = $team->users()->get();
        $owner = User::query()->find($team->user_id);

        $all = $members;
        if ($owner && ! $members->contains('id', $owner->id)) {
            $all = $members->concat(collect([$owner]));
        }

        $rows = [];
        foreach ($all->sortBy('name') as $u) {
            $role = 'Member';
            if ((int) $u->id === (int) $team->user_id) {
                $role = 'Owner';
            } else {
                $pivotRole = $u->membership?->role;
                if ($pivotRole === 'admin') $role = 'Admin';
                if ($pivotRole === 'member') $role = 'Member';
            }

            $rows[] = [
                'id' => (int) $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $role,
                'is_owner' => ((int) $u->id === (int) $team->user_id),
            ];
        }

        $this->teamUsers = $rows;

        $existing = MonitorMemberPermission::query()
            ->where('monitor_id', $this->monitor->id)
            ->get()
            ->keyBy('user_id');

        $grants = [];
        foreach ($this->teamUsers as $row) {
            $uid = (int) $row['id'];
            $perm = $existing->get($uid);

            $grants[$uid] = [
                'view_logs' => (bool) ($perm?->view_logs ?? false),
                'receive_alerts' => (bool) ($perm?->receive_alerts ?? false),
                'pause_resume' => (bool) ($perm?->pause_resume ?? false),
                'edit_settings' => (bool) ($perm?->edit_settings ?? false),
            ];
        }

        $this->grants = $grants;
    }

    public function togglePaused(): void
    {
        abort_unless(auth()->user()->can('pauseResume', $this->monitor), 403);

        $this->monitor->paused = ! $this->monitor->paused;
        $this->monitor->save();

        $this->flashSuccess = $this->monitor->paused ? 'Monitor paused.' : 'Monitor resumed.';
        $this->flashError = null;
    }

    public function toggleEmailAlerts(): void
    {
        abort_unless(auth()->user()->can('editSettings', $this->monitor), 403);

        $this->monitor->email_alerts_enabled = ! (bool) $this->monitor->email_alerts_enabled;
        $this->monitor->save();

        $this->flashSuccess = $this->monitor->email_alerts_enabled ? 'Email alerts enabled for this monitor.' : 'Email alerts disabled for this monitor.';
        $this->flashError = null;
    }

    public function toggleSlackAlerts(): void
    {
        abort_unless(auth()->user()->can('editSettings', $this->monitor), 403);
        abort_unless($this->teamPlanAllowsIntegrations(), 403);

        $this->monitor->slack_alerts_enabled = ! (bool) $this->monitor->slack_alerts_enabled;
        $this->monitor->save();

        $this->flashSuccess = $this->monitor->slack_alerts_enabled ? 'Slack alerts enabled for this monitor.' : 'Slack alerts disabled for this monitor.';
        $this->flashError = null;
    }

    public function toggleWebhookAlerts(): void
    {
        abort_unless(auth()->user()->can('editSettings', $this->monitor), 403);
        abort_unless($this->teamPlanAllowsIntegrations(), 403);

        $this->monitor->webhook_alerts_enabled = ! (bool) $this->monitor->webhook_alerts_enabled;
        $this->monitor->save();

        $this->flashSuccess = $this->monitor->webhook_alerts_enabled ? 'Webhook alerts enabled for this monitor.' : 'Webhook alerts disabled for this monitor.';
        $this->flashError = null;
    }

    public function savePermissions(): void
    {
        abort_unless($this->canManagePermissions(), 403);

        $team = $this->monitor->team;
        if (! $team) {
            $this->flashError = 'Permissions can only be managed for team monitors.';
            $this->flashSuccess = null;
            return;
        }

        $allowedIds = collect($this->teamUsers)->pluck('id')->map(fn ($v) => (int) $v)->all();

        foreach ($this->grants as $userId => $perm) {
            if (! in_array((int) $userId, $allowedIds, true)) continue;

            foreach (['view_logs','receive_alerts','pause_resume','edit_settings'] as $k) {
                $this->grants[(int) $userId][$k] = (bool) Arr::get($perm, $k, false);
            }
        }

        foreach ($this->grants as $userId => $perm) {
            $userId = (int) $userId;
            if (! in_array($userId, $allowedIds, true)) continue;

            $hasAny = (bool) ($perm['view_logs'] || $perm['receive_alerts'] || $perm['pause_resume'] || $perm['edit_settings']);

            if (! $hasAny) {
                MonitorMemberPermission::query()
                    ->where('monitor_id', $this->monitor->id)
                    ->where('user_id', $userId)
                    ->delete();
                continue;
            }

            MonitorMemberPermission::query()->updateOrCreate(
                ['monitor_id' => $this->monitor->id, 'user_id' => $userId],
                [
                    'view_logs' => $perm['view_logs'],
                    'receive_alerts' => $perm['receive_alerts'],
                    'pause_resume' => $perm['pause_resume'],
                    'edit_settings' => $perm['edit_settings'],
                ]
            );
        }

        $this->flashSuccess = 'Permissions saved.';
        $this->flashError = null;

        $this->hydrateTeamUsersAndGrants();
    }

    public function recalcSlaNow(): void
    {
        abort_unless(auth()->user()->can('editSettings', $this->monitor), 403);

        EvaluateMonitorSlaJob::dispatch((int) $this->monitor->id)->onQueue('sla');
        $this->flashSuccess = 'SLA recalculation queued.';
        $this->flashError = null;

        $this->monitor->refresh();
    }

    public function generateSlaPdf(): void
    {
        abort_unless(auth()->user()->can('view', $this->monitor), 403);

        $this->slaDownloadUrl = null;

        /** @var MonitorSlaPdfReportService $svc */
        $svc = app(MonitorSlaPdfReportService::class);

        $result = $svc->generate($this->monitor->fresh(['team', 'owner']), auth()->user(), 30, 30);

        $this->slaDownloadUrl = (string) $result['download_url'];

        $this->flashSuccess = 'SLA PDF generated. Download link expires in 30 minutes.';
        $this->flashError = null;
    }

    private function incidentTimeline30d(int $monitorId): array
    {
        $now = now();
        $start = $now->copy()->subDays(30);
        $windowSeconds = max(1, $start->diffInSeconds($now));

        $incidents = Incident::query()
            ->where('monitor_id', $monitorId)
            ->orderByDesc('started_at')
            ->limit(200)
            ->get(['id', 'started_at', 'recovered_at', 'cause_summary', 'sla_counted']);

        $segments = [];

        foreach ($incidents as $inc) {
            if (! $inc->started_at) continue;

            $iStart = $inc->started_at->copy();
            $iEnd = $inc->recovered_at ? $inc->recovered_at->copy() : $now->copy();

            if ($iEnd->lt($start)) continue;

            $effStart = $iStart->lt($start) ? $start->copy() : $iStart;
            $effEnd = $iEnd->gt($now) ? $now->copy() : $iEnd;

            if ($effEnd->lte($effStart)) continue;

            $left = ($start->diffInSeconds($effStart) / $windowSeconds) * 100;
            $width = ($effStart->diffInSeconds($effEnd) / $windowSeconds) * 100;

            $segments[] = [
                'id' => (int) $inc->id,
                'left' => max(0, min(100, $left)),
                'width' => max(0.2, min(100, $width)),
                'started_at' => $iStart->format('Y-m-d H:i'),
                'recovered_at' => $inc->recovered_at ? $iEnd->format('Y-m-d H:i') : null,
                'cause' => $inc->cause_summary ?: 'Incident',
                'sla_counted' => (bool) $inc->sla_counted,
            ];
        }

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $now->format('Y-m-d'),
            'segments' => $segments,
        ];
    }

    private function humanDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        if ($seconds === 0) return '0s';

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        $parts = [];
        if ($h > 0) $parts[] = "{$h}h";
        if ($m > 0) $parts[] = "{$m}m";
        if ($s > 0) $parts[] = "{$s}s";

        return implode(' ', $parts);
    }

    public function with(): array
    {
        $user = auth()->user();

        $can = [
            'view' => $user->can('view', $this->monitor),
            'view_logs' => $user->can('viewLogs', $this->monitor),
            'receive_alerts' => $user->can('receiveAlerts', $this->monitor),
            'pause_resume' => $user->can('pauseResume', $this->monitor),
            'edit_settings' => $user->can('editSettings', $this->monitor),
            'manage_permissions' => $this->canManagePermissions(),
        ];

        $lastCheckAt = $this->monitor->latestCheck?->checked_at?->format('Y-m-d H:i:s') ?? '—';
        $lastRtt = $this->monitor->latestCheck?->response_time_ms ? ($this->monitor->latestCheck->response_time_ms.' ms') : '—';
        $nextCheckAt = $this->monitor->next_check_at?->format('Y-m-d H:i:s') ?? '—';

        $checks = null;
        if ($can['view_logs'] && $this->tab === 'checks') {
            $checks = $this->monitor->checks()
                ->orderByDesc('checked_at')
                ->paginate(20, ['*'], 'checksPage');
        }

        $incidents = null;
        $timeline = null;
        if ($this->tab === 'incidents') {
            $incidents = $this->monitor->incidents()
                ->orderByDesc('started_at')
                ->paginate(15, ['*'], 'incidentsPage');

            $timeline = $this->incidentTimeline30d($this->monitor->id);
        }

        $sla = null;
        if ($this->tab === 'sla') {
            /** @var SlaCalculator $calc */
            $calc = app(SlaCalculator::class);
            /** @var SlaTargetResolver $targets */
            $targets = app(SlaTargetResolver::class);

            $stats = $calc->forMonitor($this->monitor, now(), 30);
            $target = $targets->targetPctForMonitor($this->monitor);
            $breach = ((float) $stats['uptime_pct']) < $target;

            $sla = [
                'target' => $target,
                'breach' => $breach,
                'uptime_pct' => (float) $stats['uptime_pct'],
                'downtime_seconds' => (int) $stats['downtime_seconds'],
                'incident_count' => (int) $stats['incident_count'],
                'mttr_seconds' => $stats['mttr_seconds'],
                'window_start' => $stats['window_start']->format('Y-m-d H:i'),
                'window_end' => $stats['window_end']->format('Y-m-d H:i'),
            ];
        }

        $tabs = [
            ['key' => 'overview', 'label' => 'Overview'],
            ['key' => 'checks', 'label' => 'Checks Log'],
            ['key' => 'incidents', 'label' => 'Incidents'],
            ['key' => 'sla', 'label' => 'SLA'],
        ];

        $teamIntegrations = $this->teamPlanAllowsIntegrations();

        return compact(
            'can',
            'tabs',
            'lastCheckAt',
            'lastRtt',
            'nextCheckAt',
            'checks',
            'incidents',
            'timeline',
            'sla',
            'teamIntegrations',
        );
    }
};
?>


<div class="space-y-6">
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

    <x-ui.card>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="text-xl font-semibold text-slate-900">{{ $monitor->name }}</div>
                    <x-ui.badge :variant="$monitor->last_status">{{ strtoupper($monitor->last_status) }}</x-ui.badge>

                    @if ($monitor->paused)
                        <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset bg-slate-50 text-slate-700 ring-slate-200">PAUSED</span>
                    @endif

                    @if ($monitor->is_public)
                        <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset bg-slate-50 text-slate-700 ring-slate-200">PUBLIC</span>
                    @endif
                </div>

                <div class="mt-1 text-sm text-slate-600 break-all">{{ $monitor->url }}</div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="text-xs text-slate-500">Last checked</div>
                        <div class="mt-1 text-sm font-semibold text-slate-900">{{ $lastCheckAt }}</div>
                        <div class="mt-1 text-sm text-slate-600">RTT: {{ $lastRtt }}</div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="text-xs text-slate-500">Next check</div>
                        <div class="mt-1 text-sm font-semibold text-slate-900">{{ $nextCheckAt }}</div>
                        <div class="mt-1 text-sm text-slate-600">Auto scheduled</div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="text-xs text-slate-500">Consecutive failures</div>
                        <div class="mt-1 text-sm font-semibold text-slate-900">{{ (int)$monitor->consecutive_failures }}</div>
                        <div class="mt-1 text-sm text-slate-600">DOWN after 2 failures</div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="togglePaused"
                    wire:loading.attr="disabled"
                    class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 {{ $can['pause_resume'] ? '' : 'opacity-50 cursor-not-allowed' }}"
                    @if (! $can['pause_resume']) disabled @endif
                >
                    {{ $monitor->paused ? 'Resume' : 'Pause' }}
                </button>

                <button
                    type="button"
                    wire:click="toggleEmailAlerts"
                    wire:loading.attr="disabled"
                    class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 {{ $can['edit_settings'] ? '' : 'opacity-50 cursor-not-allowed' }}"
                    @if (! $can['edit_settings']) disabled @endif
                >
                    Email: {{ $monitor->email_alerts_enabled ? 'On' : 'Off' }}
                </button>

                @if ($teamIntegrations)
                    <button
                        type="button"
                        wire:click="toggleSlackAlerts"
                        wire:loading.attr="disabled"
                        class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 {{ $can['edit_settings'] ? '' : 'opacity-50 cursor-not-allowed' }}"
                        @if (! $can['edit_settings']) disabled @endif
                    >
                        Slack: {{ $monitor->slack_alerts_enabled ? 'On' : 'Off' }}
                    </button>

                    <button
                        type="button"
                        wire:click="toggleWebhookAlerts"
                        wire:loading.attr="disabled"
                        class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 {{ $can['edit_settings'] ? '' : 'opacity-50 cursor-not-allowed' }}"
                        @if (! $can['edit_settings']) disabled @endif
                    >
                        Webhooks: {{ $monitor->webhook_alerts_enabled ? 'On' : 'Off' }}
                    </button>

                    @if ($monitor->team_id && $can['edit_settings'])
                        <a href="{{ route('team.notifications', $monitor->team_id) }}"
                           class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Team Notifications
                        </a>
                    @endif
                @endif

                <a href="{{ route('monitors.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Back
                </a>
            </div>
        </div>

        <div class="mt-6 border-t border-slate-200 pt-4">
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($tabs as $t)
                    @php
                        $active = $tab === $t['key'];
                        $cls = $active
                            ? 'bg-slate-900 text-white'
                            : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50';
                    @endphp
                    <button
                        type="button"
                        wire:click="$set('tab', '{{ $t['key'] }}')"
                        class="rounded-lg px-4 py-2 text-sm font-medium {{ $cls }}"
                    >
                        {{ $t['label'] }}
                    </button>
                @endforeach
            </div>
        </div>
    </x-ui.card>

    @if ($tab === 'overview')
        <x-ui.card title="Overview" description="High-level monitor info and quick actions.">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="rounded-xl border border-slate-200 bg-white p-6">
                    <div class="text-sm font-semibold text-slate-900">Current state</div>
                    <div class="mt-3 flex items-center gap-2">
                        <x-ui.badge :variant="$monitor->last_status">{{ strtoupper($monitor->last_status) }}</x-ui.badge>
                        <div class="text-sm text-slate-600">
                            @if ($monitor->openIncident)
                                Incident open since <span class="font-medium text-slate-900">{{ $monitor->openIncident->started_at?->format('Y-m-d H:i') }}</span>
                            @else
                                No active incident
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 text-sm text-slate-600">
                        Email alerts are <span class="font-semibold text-slate-900">{{ $monitor->email_alerts_enabled ? 'enabled' : 'disabled' }}</span>.
                        @if ($teamIntegrations)
                            Slack/Webhooks are available under your Team plan and can be toggled per monitor.
                        @else
                            Slack/Webhooks require Team plan.
                        @endif
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6">
                    <div class="text-sm font-semibold text-slate-900">Quick links</div>
                    <div class="mt-3 space-y-2 text-sm">
                        <a class="text-slate-900 hover:underline" href="{{ route('sla.overview') }}">Account SLA overview</a>
                        <div class="text-slate-600">Public status: <span class="font-medium text-slate-900">/status</span> (if monitor is public)</div>
                    </div>
                </div>
            </div>

            @if ($can['manage_permissions'])
                <div class="mt-8 border-t border-slate-200 pt-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-semibold text-slate-900">Member permissions</div>
                            <div class="mt-1 text-sm text-slate-600">Grant per-monitor permissions for members (Owner/Admin only).</div>
                        </div>

                        <x-ui.button wire:click="savePermissions" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="savePermissions">Save</span>
                            <span wire:loading wire:target="savePermissions">Saving…</span>
                        </x-ui.button>
                    </div>

                    <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">User</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Role</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">View logs</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Receive alerts</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Pause/Resume</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Edit settings</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @foreach ($teamUsers as $u)
                                    @php $uid = (int) $u['id']; $isOwner = (bool) $u['is_owner']; @endphp
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3">
                                            <div class="text-sm font-medium text-slate-900">{{ $u['name'] }}</div>
                                            <div class="text-sm text-slate-600">{{ $u['email'] }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-600">{{ $u['role'] }}</td>

                                        @foreach (['view_logs','receive_alerts','pause_resume','edit_settings'] as $k)
                                            <td class="px-4 py-3">
                                                <label class="inline-flex items-center gap-2">
                                                    <input type="checkbox"
                                                           wire:model="grants.{{ $uid }}.{{ $k }}"
                                                           class="rounded border-slate-200 focus:border-slate-900 focus:ring-slate-900"
                                                           @if ($isOwner) disabled @endif>
                                                    <span class="text-sm text-slate-600">{{ $isOwner ? 'Owner' : '' }}</span>
                                                </label>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-2 text-sm text-slate-600">
                        Owner always has full access. Admin/Member permissions apply only to this monitor.
                    </div>
                </div>
            @endif
        </x-ui.card>
    @endif

    @if ($tab === 'checks')
        <x-ui.card title="Checks Log" description="Latest checks. Only visible if you have view_logs permission.">
            @if (! $can['view_logs'])
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-6">
                    <div class="text-sm font-semibold text-rose-800">Access denied</div>
                    <div class="mt-1 text-sm text-rose-700">You don't have permission to view logs for this monitor.</div>
                </div>
            @else
                <div wire:loading class="space-y-3">
                    @for ($i=0; $i<6; $i++)
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <div class="h-4 w-64 bg-slate-100 rounded"></div>
                            <div class="mt-2 h-3 w-96 bg-slate-100 rounded"></div>
                        </div>
                    @endfor
                </div>

                <div wire:loading.remove>
                    @if ($checks && $checks->count() === 0)
                        <x-ui.empty-state title="No checks yet" description="Checks will appear after the scheduler runs.">
                            <x-slot:icon>
                                <svg class="h-6 w-6 text-slate-700" viewBox="0 0 24 24" fill="none">
                                    <path d="M12 6v6l4 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="2"/>
                                </svg>
                            </x-slot:icon>
                        </x-ui.empty-state>
                    @else
                        <x-ui.table>
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Time</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">OK</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">RTT</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Error</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @foreach ($checks as $c)
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 text-sm text-slate-900">{{ $c->checked_at?->format('Y-m-d H:i:s') }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            @if ($c->ok)
                                                <x-ui.badge variant="up">YES</x-ui.badge>
                                            @else
                                                <x-ui.badge variant="down">NO</x-ui.badge>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-600">{{ $c->status_code ?? '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-600">{{ $c->response_time_ms ? ($c->response_time_ms.' ms') : '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-600">
                                            {{ $c->error_code ? ($c->error_code . ': ' . $c->error_message) : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.table>

                        <div class="mt-4">
                            {{ $checks->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </x-ui.card>
    @endif

    @if ($tab === 'incidents')
        <x-ui.card title="Incidents" description="Downtime timeline and incident list.">
            <div wire:loading class="space-y-3">
                @for ($i=0; $i<4; $i++)
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="h-4 w-72 bg-slate-100 rounded"></div>
                        <div class="mt-2 h-3 w-96 bg-slate-100 rounded"></div>
                    </div>
                @endfor
            </div>

            <div wire:loading.remove>
                @if ($timeline)
                    <div class="rounded-xl border border-slate-200 bg-white p-6">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <div class="text-sm font-semibold text-slate-900">30-day timeline</div>
                                <div class="mt-1 text-sm text-slate-600">{{ $timeline['start'] }} → {{ $timeline['end'] }}</div>
                            </div>
                            <div class="text-sm text-slate-600">Red blocks are downtime windows</div>
                        </div>

                        <div class="mt-4 relative h-8 rounded-lg bg-slate-100 overflow-hidden">
                            @foreach ($timeline['segments'] as $seg)
                                <div
                                    class="absolute top-0 h-8 bg-rose-300"
                                    style="left: {{ $seg['left'] }}%; width: {{ $seg['width'] }}%;"
                                    title="{{ $seg['cause'] }} ({{ $seg['started_at'] }} → {{ $seg['recovered_at'] ?? 'ongoing' }})"
                                ></div>
                            @endforeach
                        </div>

                        @if (count($timeline['segments']) === 0)
                            <div class="mt-3 text-sm text-slate-600">No downtime segments in the last 30 days.</div>
                        @endif
                    </div>
                @endif

                @if ($incidents && $incidents->count() === 0)
                    <x-ui.empty-state title="No incidents" description="This monitor has no incidents yet.">
                        <x-slot:icon>
                            <svg class="h-6 w-6 text-slate-700" viewBox="0 0 24 24" fill="none">
                                <path d="M12 8v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M10.3 4.3a2 2 0 0 1 3.4 0l7.2 12.5A2 2 0 0 1 19.2 20H4.8a2 2 0 0 1-1.7-3.2l7.2-12.5Z" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </x-slot:icon>
                    </x-ui.empty-state>
                @else
                    <x-ui.table>
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Started</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Recovered</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Downtime</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Cause</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">SLA</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @foreach ($incidents as $i)
                                @php
                                    $start = $i->started_at;
                                    $end = $i->recovered_at ?? now();
                                    $dur = $start ? $start->diffInSeconds($end) : 0;
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-sm text-slate-900">{{ $i->started_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-900">{{ $i->recovered_at?->format('Y-m-d H:i') ?? 'Ongoing' }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-600">{{ $this->humanDuration($dur) }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-600">{{ $i->cause_summary ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        @if ($i->sla_counted)
                                            <x-ui.badge variant="up">Counted</x-ui.badge>
                                        @else
                                            <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset bg-slate-50 text-slate-700 ring-slate-200">Excluded</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>

                    <div class="mt-4">
                        {{ $incidents->links() }}
                    </div>
                @endif
            </div>
        </x-ui.card>
    @endif

    @if ($tab === 'sla')
        <x-ui.card title="SLA (Rolling 30 days)" description="Calculated from real incident downtime durations. Open incidents count until they recover.">
            <div wire:loading class="space-y-3">
                @for ($i=0; $i<4; $i++)
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="h-4 w-72 bg-slate-100 rounded"></div>
                        <div class="mt-2 h-3 w-96 bg-slate-100 rounded"></div>
                    </div>
                @endfor
            </div>

            <div wire:loading.remove>
                @if (! $sla)
                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-6">
                        <div class="text-sm font-semibold text-rose-800">Error</div>
                        <div class="mt-1 text-sm text-rose-700">Unable to load SLA metrics.</div>
                    </div>
                @else
                    @php
                        $uptime = number_format((float)$sla['uptime_pct'], 4);
                        $target = number_format((float)$sla['target'], 1);
                        $breach = (bool)$sla['breach'];
                        $downtime = $this->humanDuration((int)$sla['downtime_seconds']);
                        $incCount = (int)$sla['incident_count'];
                        $mttr = $sla['mttr_seconds'] ? $this->humanDuration((int)$sla['mttr_seconds']) : '—';
                    @endphp

                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                        <div class="rounded-xl border border-slate-200 bg-white p-6">
                            <div class="text-xs text-slate-500">Uptime (30d)</div>
                            <div class="mt-1 text-xl font-semibold text-slate-900">{{ $uptime }}%</div>
                            <div class="mt-1 text-sm text-slate-600">Window: {{ $sla['window_start'] }} → {{ $sla['window_end'] }}</div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-6">
                            <div class="text-xs text-slate-500">Target</div>
                            <div class="mt-1 text-xl font-semibold text-slate-900">{{ $target }}%</div>
                            <div class="mt-1 text-sm text-slate-600">
                                @if ($breach)
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset bg-rose-50 text-rose-700 ring-rose-200">BREACHED</span>
                                @else
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset bg-emerald-50 text-emerald-700 ring-emerald-200">OK</span>
                                @endif
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-6">
                            <div class="text-xs text-slate-500">Total downtime</div>
                            <div class="mt-1 text-xl font-semibold text-slate-900">{{ $downtime }}</div>
                            <div class="mt-1 text-sm text-slate-600">Counted incidents only</div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-6">
                            <div class="text-xs text-slate-500">Incidents / MTTR</div>
                            <div class="mt-1 text-xl font-semibold text-slate-900">{{ $incCount }}</div>
                            <div class="mt-1 text-sm text-slate-600">MTTR: {{ $mttr }}</div>
                        </div>
                    </div>

                    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-sm font-semibold text-slate-900">Breach alerts</div>
                                <div class="mt-1 text-sm text-slate-600">
                                    Alert triggers only when uptime crosses below target (deduplicated).
                                    Email is supported on all plans. Slack breach alerts are Team-only.
                                </div>
                                @if ($monitor->sla_last_breach_alert_at)
                                    <div class="mt-2 text-sm text-slate-600">
                                        Last breach alert: <span class="font-medium text-slate-900">{{ $monitor->sla_last_breach_alert_at->format('Y-m-d H:i') }}</span>
                                    </div>
                                @endif
                            </div>

                            <button
                                type="button"
                                wire:click="recalcSlaNow"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 {{ $can['edit_settings'] ? '' : 'opacity-50 cursor-not-allowed' }}"
                                @if (! $can['edit_settings']) disabled @endif
                            >
                                <span wire:loading.remove wire:target="recalcSlaNow">Recalculate now</span>
                                <span wire:loading wire:target="recalcSlaNow">Queuing…</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="rounded-xl border border-slate-200 bg-white p-6">
                    <div class="flex items-start justify-between gap-4">
                    <div>
                    <div class="text-sm font-semibold text-slate-900">SLA PDF report</div>
                    <div class="mt-1 text-sm text-slate-600">
                    Generate a print-safe PDF for the last 30 days. Download link expires in 30 minutes.
                    </div>
                    </div>
                    
                    
                    <button
                    type="button"
                    wire:click="generateSlaPdf"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                    >
                    <span wire:loading.remove wire:target="generateSlaPdf">Download SLA PDF</span>
                    <span wire:loading wire:target="generateSlaPdf">Generating…</span>
                    </button>
                    </div>
                    
                    
                    @if ($slaDownloadUrl)
                    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <div class="text-sm font-semibold text-emerald-800">Ready</div>
                    <div class="mt-1 text-sm text-emerald-700">
                    <a class="font-semibold underline" href="{{ $slaDownloadUrl }}">Click here to download</a>
                    <span class="text-emerald-700"> (expires in 30 minutes)</span>
                    </div>
                    </div>
                    @endif
                    </div>
                @endif
            </div>
        </x-ui.card>
    @endif
</div>