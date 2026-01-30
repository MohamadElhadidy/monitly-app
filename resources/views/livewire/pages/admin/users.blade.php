<?php

use App\Models\Team;
use App\Models\User;
use App\Services\Admin\AdminActionService;
use App\Services\Admin\AdminSettingsService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.admin')]
#[Title('Admin • Users')]
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

    public function performAction(AdminActionService $service, AdminSettingsService $settings): void
    {
        if ($settings->getSettings()->read_only_mode) {
            $this->addError('reason', 'Read-only mode is enabled.');
            return;
        }

        $this->validate([
            'reason' => 'required|string|min:3',
        ]);

        $user = User::query()->findOrFail($this->selectedId);

        match ($this->selectedAction) {
            'suspend' => $service->suspendUser($user, $this->reason),
            'unsuspend' => $service->unsuspendUser($user, $this->reason),
            'ban' => $service->banUser($user, $this->reason),
            'force_logout' => $service->forceLogoutUser($user, $this->reason),
            default => null,
        };

        $this->reset(['selectedId', 'selectedAction', 'reason']);
        session()->flash('status', 'User action completed.');
    }

    public function with(): array
    {
        $users = User::query()->latest()->limit(50)->get();

        $teamOwners = Team::query()->pluck('user_id')->all();

        $rows = $users->map(function (User $user) use ($teamOwners) {
            $teamMembership = $user->teams()->where('personal_team', false)->first();
            $type = 'normal';
            $plan = $user->billing_plan ?? 'free';

            if ($teamMembership) {
                $type = in_array($user->id, $teamOwners, true) ? 'team-owner' : 'team-member';
                $plan = $teamMembership->billing_plan ?? 'free';
            }

            $status = $user->status ?? 'active';
            if ($user->banned_at) {
                $status = 'banned';
            }

            return [
                'id' => $user->id,
                'email' => $user->email,
                'type' => $type,
                'created_at' => $user->created_at,
                'last_active' => $user->last_login_at ?? null,
                'status' => $status,
                'plan' => $plan,
            ];
        });

        return ['rows' => $rows];
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Users</h1>
        <p class="text-sm text-slate-600">Normal users and team members with moderation tools.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if($loadError)
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Unable to load users.
            <button wire:click="refreshPage" class="ml-3 rounded border border-rose-300 px-2 py-1 text-xs font-semibold">Retry</button>
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Email</th>
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-left">Created</th>
                    <th class="px-4 py-3 text-left">Last Active</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Plan</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($rows as $row)
                    <tr>
                        <td class="px-4 py-3">{{ $row['email'] }}</td>
                        <td class="px-4 py-3">{{ str_replace('-', ' ', ucfirst($row['type'])) }}</td>
                        <td class="px-4 py-3">{{ \Carbon\Carbon::parse($row['created_at'])->toDateString() }}</td>
                        <td class="px-4 py-3">{{ $row['last_active'] ? \Carbon\Carbon::parse($row['last_active'])->diffForHumans() : '—' }}</td>
                        <td class="px-4 py-3">{{ $row['status'] }}</td>
                        <td class="px-4 py-3">{{ ucfirst($row['plan']) }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-3 text-xs">
                                <button wire:click="confirmAction('suspend', {{ $row['id'] }})" class="text-amber-700 hover:underline">Suspend</button>
                                <button wire:click="confirmAction('unsuspend', {{ $row['id'] }})" class="text-emerald-700 hover:underline">Unsuspend</button>
                                <button wire:click="confirmAction('ban', {{ $row['id'] }})" class="text-rose-600 hover:underline">Hard ban</button>
                                <button wire:click="confirmAction('force_logout', {{ $row['id'] }})" class="text-slate-700 hover:underline">Force logout</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">No users found.</td>
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
                        Suspend this user? They will not be able to access Monitly.
                    @elseif($selectedAction === 'ban')
                        Ban this user for abuse? This is a serious action.
                    @elseif($selectedAction === 'unsuspend')
                        Unsuspend this user? Access will be restored.
                    @else
                        Force logout this user? They will need to sign in again.
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
