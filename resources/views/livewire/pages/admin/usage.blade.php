<?php

use App\Models\Team;
use App\Models\User;
use App\Services\Admin\AdminActionService;
use App\Services\Admin\AdminSettingsService;
use App\Services\Billing\PlanLimits;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.admin')]
#[Title('Admin • Usage & Limits')]
class extends Component
{
    public bool $loadError = false;
    public ?int $selectedId = null;
    public ?string $selectedType = null;
    public string $reason = '';

    public function refreshPage(): void
    {
        $this->loadError = false;
    }

    public function confirmRestrict(string $type, int $id): void
    {
        $this->selectedType = $type;
        $this->selectedId = $id;
    }

    public function performRestrict(AdminActionService $actions, AdminSettingsService $settings): void
    {
        if ($settings->getSettings()->read_only_mode) {
            $this->addError('reason', 'Read-only mode is enabled.');
            return;
        }

        $this->validate([
            'reason' => 'required|string|min:3',
        ]);

        if ($this->selectedType === 'team') {
            $team = Team::query()->findOrFail($this->selectedId);
            $actions->restrictTeam($team, $this->reason);
        } else {
            $user = User::query()->findOrFail($this->selectedId);
            $actions->restrictUser($user, $this->reason);
        }

        $this->reset(['selectedId', 'selectedType', 'reason']);
        session()->flash('status', 'Restriction applied.');
    }

    public function with(): array
    {
        $users = User::query()->get();
        $teams = Team::query()->where('personal_team', false)->get();

        $overLimit = [];
        $nearLimit = [];

        foreach ($users as $user) {
            $limit = PlanLimits::monitorLimitForUser($user);
            $count = $user->monitors()->count();
            $ratio = $limit > 0 ? $count / $limit : 0;
            $entry = [
                'type' => 'user',
                'id' => $user->id,
                'label' => $user->email,
                'usage' => "$count/$limit",
                'ratio' => $ratio,
            ];
            if ($ratio >= 1) {
                $overLimit[] = $entry;
            } elseif ($ratio >= 0.8) {
                $nearLimit[] = $entry;
            }
        }

        foreach ($teams as $team) {
            $monitorLimit = PlanLimits::monitorLimitForTeam($team);
            $userLimit = PlanLimits::seatLimitForTeam($team);
            $monitorCount = $team->monitors()->count();
            $userCount = $team->users()->count();
            $ratio = $monitorLimit > 0 ? $monitorCount / $monitorLimit : 0;
            $entry = [
                'type' => 'team',
                'id' => $team->id,
                'label' => $team->name,
                'usage' => "$monitorCount/$monitorLimit monitors • $userCount/$userLimit users",
                'ratio' => $ratio,
            ];
            if ($ratio >= 1 || ($userLimit > 0 && $userCount >= $userLimit)) {
                $overLimit[] = $entry;
            } elseif ($ratio >= 0.8 || ($userLimit > 0 && ($userCount / $userLimit) >= 0.8)) {
                $nearLimit[] = $entry;
            }
        }

        $heavyUsage = DB::table('monitor_checks')
            ->join('monitors', 'monitor_checks.monitor_id', '=', 'monitors.id')
            ->where('monitor_checks.checked_at', '>=', now()->subHour())
            ->selectRaw('monitors.user_id, monitors.team_id, COUNT(*) as checks')
            ->groupBy('monitors.user_id', 'monitors.team_id')
            ->orderByDesc('checks')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                if ($row->team_id) {
                    $team = Team::query()->find($row->team_id);
                    return [
                        'label' => $team?->name ?? 'Team',
                        'type' => 'team',
                        'checks' => $row->checks,
                    ];
                }
                $user = User::query()->find($row->user_id);
                return [
                    'label' => $user?->email ?? 'User',
                    'type' => 'user',
                    'checks' => $row->checks,
                ];
            })
            ->all();

        return [
            'overLimit' => $overLimit,
            'nearLimit' => $nearLimit,
            'heavyUsage' => $heavyUsage,
        ];
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Usage & Limits</h1>
        <p class="text-sm text-slate-600">Track over-limit usage and apply restrictions safely.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-sm font-semibold text-slate-900">Over-limit Entities</div>
            <div class="mt-3 space-y-3">
                @forelse($overLimit as $entity)
                    <div class="flex items-center justify-between rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm">
                        <div>
                            <div class="font-semibold text-rose-700">{{ $entity['label'] }}</div>
                            <div class="text-xs text-rose-600">{{ $entity['usage'] }}</div>
                        </div>
                        <button wire:click="confirmRestrict('{{ $entity['type'] }}', {{ $entity['id'] }})" class="text-xs font-semibold text-rose-700">Restrict</button>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">No over-limit entities.</div>
                @endforelse
            </div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-sm font-semibold text-slate-900">Near-limit Entities (80%+)</div>
            <div class="mt-3 space-y-3">
                @forelse($nearLimit as $entity)
                    <div class="flex items-center justify-between rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm">
                        <div>
                            <div class="font-semibold text-amber-700">{{ $entity['label'] }}</div>
                            <div class="text-xs text-amber-600">{{ $entity['usage'] }}</div>
                        </div>
                        <button wire:click="confirmRestrict('{{ $entity['type'] }}', {{ $entity['id'] }})" class="text-xs font-semibold text-amber-700">Restrict</button>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">No near-limit entities.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="text-sm font-semibold text-slate-900">Most Checks per Hour</div>
        <div class="mt-3 space-y-3 text-sm">
            @forelse($heavyUsage as $entry)
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-semibold text-slate-900">{{ $entry['label'] }}</div>
                        <div class="text-xs text-slate-500">{{ ucfirst($entry['type']) }}</div>
                    </div>
                    <div class="text-slate-700">{{ $entry['checks'] }} checks</div>
                </div>
            @empty
                <div class="text-sm text-slate-500">No heavy usage detected.</div>
            @endforelse
        </div>
    </div>

    @if($selectedId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-6">
                <h2 class="text-lg font-semibold text-slate-900">Restrict this entity?</h2>
                <p class="mt-2 text-sm text-slate-600">Restriction is reversible and temporarily throttles checks.</p>
                <div class="mt-4">
                    <label class="text-xs font-semibold uppercase text-slate-500">Reason</label>
                    <textarea wire:model.defer="reason" class="mt-2 w-full rounded-lg border border-slate-200 p-2 text-sm" rows="3" placeholder="Reason required"></textarea>
                    @error('reason') <div class="text-xs text-rose-600 mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('selectedId', null)" class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                    <button wire:click="performRestrict" class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Confirm</button>
                </div>
            </div>
        </div>
    @endif
</div>
