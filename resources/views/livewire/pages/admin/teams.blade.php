<?php

use App\Models\Team;
use App\Services\Audit\Audit;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Admin • Teams')]
class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $plan = 'all';   // all|free|team
    public string $status = 'all'; // all|free|active|past_due|canceling|canceled

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('access-admin'), 403);
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingPlan(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->reset(['search', 'plan', 'status']);
        $this->resetPage();
    }

    public function setRefundOverride30d(int $teamId): void
    {
        $t = Team::query()->findOrFail($teamId);
        $t->refund_override_until = now()->addDays(30);
        $t->save();

        Audit::log(action: 'billing.refund_override_set', subject: $t, teamId: (int) $t->id, meta: ['until' => $t->refund_override_until?->toIso8601String()]);
    }

    public function clearRefundOverride(int $teamId): void
    {
        $t = Team::query()->findOrFail($teamId);
        $t->refund_override_until = null;
        $t->save();

        Audit::log(action: 'billing.refund_override_cleared', subject: $t, teamId: (int) $t->id);
    }

    public function with(): array
    {
        $teams = Team::query()
            ->when($this->search, function ($qq) {
                $s = trim($this->search);
                $qq->where(function ($w) use ($s) {
                    $w->where('name', 'like', "%{$s}%")
                      ->orWhere('slug', 'like', "%{$s}%")
                      ->orWhere('id', (int) $s);
                });
            })
            ->when($this->plan !== 'all', fn ($qq) => $qq->where('billing_plan', $this->plan))
            ->when($this->status !== 'all', fn ($qq) => $qq->where('billing_status', $this->status))
            ->withCount('users')
            ->orderByDesc('created_at')
            ->paginate(20);

        return compact('teams');
    }
};
?>

<div class="space-y-6">
    {{-- Sticky header --}}
    <div class="sticky top-0 z-20 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-4 bg-white/80 backdrop-blur border-b border-slate-200">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-xl font-semibold text-slate-900">Teams</div>
                <div class="mt-1 text-sm text-slate-600">Billing status, public pages, refund overrides.</div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('admin.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Overview</a>
                <a href="{{ route('admin.subscriptions') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Subscriptions</a>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
        <div class="flex items-center justify-between gap-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 flex-1">
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Search</label>
                    <input wire:model.live="search" class="mt-1 w-full rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900" placeholder="Name, slug, id">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600">Plan</label>
                    <select wire:model.live="plan" class="mt-1 w-full rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900">
                        <option value="all">All</option>
                        <option value="free">Free</option>
                        <option value="team">Team</option>
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
            </div>

            <button wire:click="clearFilters" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Clear</button>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        {{-- Skeleton --}}
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
            @if ($teams->isEmpty())
                <div class="p-10">
                    <div class="mx-auto max-w-md text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl border border-slate-200 bg-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="8.5" cy="7" r="4"/>
                                <path d="M20 8v6"/><path d="M23 11h-6"/>
                            </svg>
                        </div>
                        <div class="mt-4 text-sm font-semibold text-slate-900">No teams match your filters</div>
                        <div class="mt-1 text-sm text-slate-600">Clear filters to see all teams.</div>
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
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Team</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Billing</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Members</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Public</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @foreach ($teams as $t)
                                @php
                                    $status = (string) $t->billing_status;
                                    $statusClass = match($status) {
                                        'active' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
                                        'past_due' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
                                        'canceling' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-200',
                                        'canceled' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
                                        default => 'bg-slate-50 text-slate-700 ring-1 ring-slate-200',
                                    };
                                    $plan = (string) $t->billing_plan;
                                    $planClass = match($plan) {
                                        'team' => 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200',
                                        'business' => 'bg-purple-50 text-purple-700 ring-1 ring-purple-200',
                                        default => 'bg-slate-50 text-slate-700 ring-1 ring-slate-200',
                                    };
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-semibold text-slate-900">{{ $t->name }}</div>
                                        <div class="text-xs text-slate-500">#{{ $t->id }} · slug: {{ $t->slug ?? '—' }} · owner #{{ $t->user_id }}</div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $planClass }}">
                                                {{ strtoupper($plan ?: 'free') }}
                                            </span>
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusClass }}">
                                                {{ strtoupper($status ?: 'free') }}
                                            </span>
                                            @if ($t->billing_status === 'canceling' && $t->next_bill_at)
                                                <span class="text-xs text-slate-500">ends {{ $t->next_bill_at->format('Y-m-d') }}</span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        {{ ($t->users_count ?? 0) + 1 }} total (incl owner)
                                    </td>

                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        @if ($t->public_status_enabled)
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">Enabled</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-slate-50 text-slate-700 ring-1 ring-slate-200">Disabled</span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            {{-- Primary action (one) --}}
                                            <button wire:click="setRefundOverride30d({{ $t->id }})" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
                                                Override +30d
                                            </button>

                                            @if ($t->refund_override_until)
                                                <button wire:click="clearRefundOverride({{ $t->id }})" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                                    Clear
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 bg-white">
                    {{ $teams->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
