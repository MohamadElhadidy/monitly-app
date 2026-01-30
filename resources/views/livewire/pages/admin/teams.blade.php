<?php

use App\Models\Team;
use App\Services\Admin\AdminActionService;
use App\Services\Admin\AdminBillingService;
use App\Services\Admin\AdminSettingsService;
use App\Services\Billing\PlanLimits;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.admin')]
#[Title('Admin • Teams')]
class extends Component
{
    public bool $loadError = false;
    public ?int $selectedId = null;
    public ?string $selectedAction = null;
    public string $reason = '';

    public function refreshPage(): void
    {
        $this->loadError = false;
    }

    public function confirmAction(string $action, int $id): void
    {
        $this->selectedAction = $action;
        $this->selectedId = $id;
    }

    public function performAction(AdminActionService $actions, AdminBillingService $billing, AdminSettingsService $settings): void
    {
        if ($settings->getSettings()->read_only_mode) {
            $this->addError('reason', 'Read-only mode is enabled.');
            return;
        }

        $this->validate([
            'reason' => 'required|string|min:3',
        ]);

        $team = Team::query()->findOrFail($this->selectedId);

        match ($this->selectedAction) {
            'suspend' => $actions->suspendTeam($team, $this->reason),
            'unsuspend' => $actions->unsuspendTeam($team, $this->reason),
            'ban' => $actions->banTeam($team, $this->reason),
            'enforce_limits' => $actions->enforceTeamLimits($team, $this->reason),
            'resync' => $billing->requestResync($team, $this->reason),
            default => null,
        };

        $this->reset(['selectedId', 'selectedAction', 'reason']);
        session()->flash('status', 'Team action completed.');
    }

    public function with(): array
    {
        $teams = Team::query()->latest()->limit(50)->get();

        $rows = $teams->map(function (Team $team) {
            $monitorLimit = PlanLimits::monitorLimitForTeam($team);
            $userLimit = PlanLimits::seatLimitForTeam($team);

            $monitorCount = $team->monitors()->count();
            $userCount = $team->users()->count();

            $status = $team->status ?? 'active';
            if ($team->banned_at) {
                $status = 'banned';
            }

            return [
                'id' => $team->id,
                'name' => $team->name,
                'owner_email' => optional($team->owner)->email,
                'plan' => $team->billing_plan ?? 'free',
                'status' => $status,
                'monitor_usage' => $monitorCount . '/' . $monitorLimit,
                'user_usage' => $userCount . '/' . $userLimit,
            ];
        });

        return ['rows' => $rows];
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Teams</h1>
        <p class="text-sm text-slate-600">Team billing, usage, and enforcement actions.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if($loadError)
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Unable to load teams.
            <button wire:click="refreshPage" class="ml-3 rounded border border-rose-300 px-2 py-1 text-xs font-semibold">Retry</button>
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Team</th>
                    <th class="px-4 py-3 text-left">Owner Email</th>
                    <th class="px-4 py-3 text-left">Plan</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Monitor Usage</th>
                    <th class="px-4 py-3 text-left">User Usage</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($rows as $row)
                    <tr>
                        <td class="px-4 py-3">{{ $row['name'] }}</td>
                        <td class="px-4 py-3">{{ $row['owner_email'] ?? '—' }}</td>
                        <td class="px-4 py-3">{{ ucfirst($row['plan']) }}</td>
                        <td class="px-4 py-3">{{ $row['status'] }}</td>
                        <td class="px-4 py-3">{{ $row['monitor_usage'] }}</td>
                        <td class="px-4 py-3">{{ $row['user_usage'] }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-3 text-xs">
                                <button wire:click="confirmAction('suspend', {{ $row['id'] }})" class="text-amber-700 hover:underline">Suspend</button>
                                <button wire:click="confirmAction('unsuspend', {{ $row['id'] }})" class="text-emerald-700 hover:underline">Unsuspend</button>
                                <button wire:click="confirmAction('ban', {{ $row['id'] }})" class="text-rose-600 hover:underline">Ban</button>
                                <button wire:click="confirmAction('enforce_limits', {{ $row['id'] }})" class="text-slate-700 hover:underline">Enforce limits now</button>
                                <button wire:click="confirmAction('resync', {{ $row['id'] }})" class="text-slate-700 hover:underline">Resync billing</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">No teams found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($selectedId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-6">
                <h2 class="text-lg font-semibold text-slate-900">Confirm action</h2>
                <p class="mt-2 text-sm text-slate-600">
                    @if($selectedAction === 'suspend')
                        Suspend this team? They will not be able to access Monitly.
                    @elseif($selectedAction === 'ban')
                        Ban this team for abuse? This is a serious action.
                    @elseif($selectedAction === 'unsuspend')
                        Unsuspend this team? Access will be restored.
                    @elseif($selectedAction === 'enforce_limits')
                        Enforce limits now? This blocks usage beyond plan limits without deleting data.
                    @else
                        Resync billing from Paddle? This may take a moment.
                    @endif
                </p>
                <div class="mt-4">
                    <label class="text-xs font-semibold uppercase text-slate-500">Reason</label>
                    <textarea wire:model.defer="reason" class="mt-2 w-full rounded-lg border border-slate-200 p-2 text-sm" rows="3" placeholder="Reason required"></textarea>
                    @error('reason') <div class="text-xs text-rose-600 mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('selectedId', null)" class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                    <button wire:click="performAction" class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Confirm</button>
                </div>
            </div>
        </div>
    @endif
</div>
