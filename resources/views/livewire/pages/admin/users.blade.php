<?php

use App\Models\User;
use App\Services\Audit\Audit;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Admin • Users')]
class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $plan = 'all';   // all|free|pro
    public string $status = 'all'; // all|free|active|past_due|canceling|canceled
    public string $banned = 'all'; // all|yes|no

    public ?int $selectedUserId = null;

    public bool $showBanModal = false;
    public string $banReason = '';

    public bool $showRefundOverrideModal = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('access-admin'), 403);
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingPlan(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }
    public function updatingBanned(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->reset(['search', 'plan', 'status', 'banned']);
        $this->resetPage();
    }

    public function openBan(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->banReason = '';
        $this->showBanModal = true;
    }

    public function ban(): void
    {
        $this->validate([
            'selectedUserId' => ['required', 'integer', 'exists:users,id'],
            'banReason' => ['required', 'string', 'max:190'],
        ]);

        $u = User::query()->findOrFail($this->selectedUserId);

        $u->banned_at = now();
        $u->ban_reason = $this->banReason;
        $u->save();

        Audit::log(action: 'user.banned', subject: $u, meta: ['reason' => $this->banReason]);

        $this->showBanModal = false;
        $this->selectedUserId = null;
    }

    public function unban(int $userId): void
    {
        $u = User::query()->findOrFail($userId);

        $u->banned_at = null;
        $u->ban_reason = null;
        $u->save();

        Audit::log(action: 'user.unbanned', subject: $u);
    }

    public function toggleAdmin(int $userId): void
    {
        $u = User::query()->findOrFail($userId);

        $currentlyAdmin = (bool) $u->is_admin;

        if ($currentlyAdmin) {
            $adminCount = User::query()->where('is_admin', true)->count();
            if ($adminCount <= 1) {
                $this->addError('admin', 'Cannot remove the last admin.');
                return;
            }
        }

        $u->is_admin = ! $currentlyAdmin;
        $u->save();

        Audit::log(action: 'user.admin_toggled', subject: $u, meta: ['is_admin' => (bool) $u->is_admin]);
    }

    public function openRefundOverride(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->showRefundOverrideModal = true;
    }

    public function setRefundOverride30d(): void
    {
        $this->validate([
            'selectedUserId' => ['required', 'integer', 'exists:users,id'],
        ]);

        $u = User::query()->findOrFail($this->selectedUserId);

        $u->refund_override_until = now()->addDays(30);
        $u->save();

        Audit::log(action: 'billing.refund_override_set', subject: $u, meta: ['until' => $u->refund_override_until?->toIso8601String()]);

        $this->showRefundOverrideModal = false;
        $this->selectedUserId = null;
    }

    public function clearRefundOverride(int $userId): void
    {
        $u = User::query()->findOrFail($userId);

        $u->refund_override_until = null;
        $u->save();

        Audit::log(action: 'billing.refund_override_cleared', subject: $u);
    }

    public function with(): array
    {
        $q = User::query()
            ->when($this->search, function ($qq) {
                $s = trim($this->search);
                $qq->where(function ($w) use ($s) {
                    $w->where('name', 'like', "%{$s}%")
                      ->orWhere('email', 'like', "%{$s}%")
                      ->orWhere('id', (int) $s);
                });
            })
            ->when($this->plan !== 'all', fn ($qq) => $qq->where('billing_plan', $this->plan))
            ->when($this->status !== 'all', fn ($qq) => $qq->where('billing_status', $this->status))
            ->when($this->banned === 'yes', fn ($qq) => $qq->whereNotNull('banned_at'))
            ->when($this->banned === 'no', fn ($qq) => $qq->whereNull('banned_at'))
            ->orderByDesc('created_at');

        $users = $q->paginate(20);

        return compact('users');
    }
};
?>

<div class="space-y-6">
    {{-- Sticky header --}}
    <div class="sticky top-0 z-20 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-4 bg-white/80 backdrop-blur border-b border-slate-200">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-xl font-semibold text-slate-900">Users</div>
                <div class="mt-1 text-sm text-slate-600">Bans, admin access, refund overrides.</div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('admin.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Overview</a>
                <a href="{{ route('admin.audit_logs') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Audit logs</a>
            </div>
        </div>
    </div>

    @error('admin')
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{{ $message }}</div>
    @enderror

    {{-- Filters --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
        <div class="flex items-center justify-between gap-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 flex-1">
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Search</label>
                    <input wire:model.live="search" class="mt-1 w-full rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900" placeholder="Name, email, id">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600">Plan</label>
                    <select wire:model.live="plan" class="mt-1 w-full rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900">
                        <option value="all">All</option>
                        <option value="free">Free</option>
                        <option value="pro">Pro</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600">Status</label>
                    <select wire:model.live="status" class="mt-1 w-full rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900">
                        <option value="all">All</option>
                        <option value="free">Free</option>
                        <option value="active">Active</option>
                        <option value="past_due">Past due</option>
                        <option value="canceling">Canceling</option>
                        <option value="canceled">Canceled</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600">Banned</label>
                    <select wire:model.live="banned" class="mt-1 w-full rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900">
                        <option value="all">All</option>
                        <option value="no">Not banned</option>
                        <option value="yes">Banned</option>
                    </select>
                </div>
            </div>

            <button wire:click="clearFilters" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Clear
            </button>
        </div>
    </div>

    {{-- Table card --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        {{-- Skeleton loading --}}
        <div wire:loading.delay class="p-6">
            <div class="animate-pulse space-y-4">
                <div class="h-4 w-1/3 rounded bg-slate-200"></div>
                <div class="space-y-3">
                    @for ($i = 0; $i < 8; $i++)
                        <div class="h-10 rounded bg-slate-100 border border-slate-200"></div>
                    @endfor
                </div>
            </div>
        </div>

        <div wire:loading.remove>
            @if ($users->isEmpty())
                <div class="p-10">
                    <div class="mx-auto max-w-md text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl border border-slate-200 bg-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                        </div>
                        <div class="mt-4 text-sm font-semibold text-slate-900">No users found</div>
                        <div class="mt-1 text-sm text-slate-600">Try adjusting filters or clearing your search.</div>
                        <div class="mt-6">
                            <button wire:click="clearFilters" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                                Clear filters
                            </button>
                        </div>
                    </div>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">User</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Plan</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Billing</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Flags</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @foreach ($users as $u)
                                @php
                                    $plan = (string) $u->billing_plan;
                                    $status = (string) $u->billing_status;
                                    $statusClass = match($status) {
                                        'active' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
                                        'past_due' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
                                        'canceling' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-200',
                                        'canceled' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
                                        default => 'bg-slate-50 text-slate-700 ring-1 ring-slate-200',
                                    };
                                    $planClass = match($plan) {
                                        'pro' => 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200',
                                        default => 'bg-slate-50 text-slate-700 ring-1 ring-slate-200',
                                    };
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-semibold text-slate-900">{{ $u->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $u->email }} · #{{ $u->id }}</div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $planClass }}">
                                            {{ strtoupper($plan ?: 'free') }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusClass }}">
                                                {{ strtoupper($status ?: 'free') }}
                                            </span>
                                            @if ($u->billing_status === 'canceling' && $u->next_bill_at)
                                                <span class="text-xs text-slate-500">ends {{ $u->next_bill_at->format('Y-m-d') }}</span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap items-center gap-2">
                                            @if ($u->is_admin)
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-slate-900 text-white">ADMIN</span>
                                            @endif
                                            @if ($u->banned_at)
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-rose-50 text-rose-700 ring-1 ring-rose-200">BANNED</span>
                                            @endif
                                            @if ($u->refund_override_until)
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-slate-50 text-slate-700 ring-1 ring-slate-200">
                                                    refund override
                                                </span>
                                            @endif
                                        </div>
                                        @if ($u->banned_at && $u->ban_reason)
                                            <div class="mt-1 text-xs text-slate-500">{{ $u->ban_reason }}</div>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            {{-- Primary action (only one) --}}
                                            @if ($u->banned_at)
                                                <button wire:click="unban({{ $u->id }})" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
                                                    Unban
                                                </button>
                                            @else
                                                <button wire:click="openBan({{ $u->id }})" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-100">
                                                    Ban
                                                </button>
                                            @endif

                                            {{-- Overflow menu (keeps view from feeling crowded) --}}
                                            <div x-data="{open:false}" class="relative">
                                                <button @click="open = !open" type="button" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                                    More
                                                </button>
                                                <div x-cloak x-show="open" @click.outside="open=false" class="absolute right-0 mt-2 w-56 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden z-30">
                                                    <button wire:click="toggleAdmin({{ $u->id }})" @click="open=false" class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                                        {{ $u->is_admin ? 'Remove admin' : 'Make admin' }}
                                                    </button>
                                                    <button wire:click="openRefundOverride({{ $u->id }})" @click="open=false" class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                                        Set refund override +30d
                                                    </button>
                                                    @if ($u->refund_override_until)
                                                        <button wire:click="clearRefundOverride({{ $u->id }})" @click="open=false" class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                                            Clear refund override
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 bg-white">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Ban Modal --}}
    @if ($showBanModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
            <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-sm p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">Ban user</div>
                        <div class="mt-1 text-sm text-slate-600">Immediately blocks access and forces logout.</div>
                    </div>
                    <button wire:click="$set('showBanModal', false)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Close</button>
                </div>

                <div class="mt-4">
                    <label class="block text-xs font-semibold text-slate-600">Reason</label>
                    <input wire:model="banReason" class="mt-1 w-full rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900" placeholder="e.g. Chargeback fraud">
                    @error('banReason') <div class="mt-1 text-xs text-rose-700">{{ $message }}</div> @enderror
                </div>

                <div class="mt-6 flex items-center justify-end gap-2">
                    <button wire:click="$set('showBanModal', false)" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button wire:click="ban" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-100">Confirm ban</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Refund Override Modal --}}
    @if ($showRefundOverrideModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
            <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-sm p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">Refund override</div>
                        <div class="mt-1 text-sm text-slate-600">Extends refund eligibility beyond “first payment + 30 days”.</div>
                    </div>
                    <button wire:click="$set('showRefundOverrideModal', false)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Close</button>
                </div>

                <div class="mt-6 flex items-center justify-end gap-2">
                    <button wire:click="$set('showRefundOverrideModal', false)" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button wire:click="setRefundOverride30d" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Set override +30 days
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
