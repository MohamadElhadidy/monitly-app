<?php

use App\Models\Team;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Admin • Subscriptions')]
class extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->can('access-admin'), 403);
    }

    public function with(): array
    {
        $userSubs = User::query()
            ->whereNotNull('paddle_subscription_id')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        $teamSubs = Team::query()
            ->whereNotNull('paddle_subscription_id')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        return compact('userSubs', 'teamSubs');
    }
};
?>

<div class="space-y-6">
    <div class="sticky top-0 z-20 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-4 bg-white/80 backdrop-blur border-b border-slate-200">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-xl font-semibold text-slate-900">Subscriptions</div>
                <div class="mt-1 text-sm text-slate-600">Quick view of Paddle identifiers + grace windows.</div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('admin.users') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Users</a>
                <a href="{{ route('admin.teams') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Teams</a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div class="text-sm font-semibold text-slate-900">User Subscriptions</div>
                <div class="text-xs text-slate-500">{{ $userSubs->count() }} shown</div>
            </div>

            @if ($userSubs->isEmpty())
                <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-6">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white border border-slate-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 7H4"/><path d="M20 11H4"/><path d="M10 15H4"/><path d="M20 15h-6"/><path d="M20 19H4"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-slate-900">No user subscriptions found</div>
                            <div class="text-sm text-slate-600">Users with Paddle subscription IDs will appear here.</div>
                        </div>
                    </div>
                </div>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">User</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Plan</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Next bill</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Grace ends</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Subscription</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @foreach ($userSubs as $u)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-2 text-sm text-slate-600">
                                        <div class="font-semibold text-slate-900">{{ $u->name }}</div>
                                        <div class="text-xs text-slate-500">#{{ $u->id }} · {{ $u->email }}</div>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-slate-600">{{ strtoupper($u->billing_plan) }}</td>
                                    <td class="px-4 py-2 text-sm text-slate-600">{{ strtoupper($u->billing_status) }}</td>
                                    <td class="px-4 py-2 text-sm text-slate-600">{{ $u->next_bill_at?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="px-4 py-2 text-sm text-slate-600">{{ $u->grace_ends_at?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="px-4 py-2 text-xs text-slate-500 font-mono">{{ $u->paddle_subscription_id }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div class="text-sm font-semibold text-slate-900">Team Subscriptions</div>
                <div class="text-xs text-slate-500">{{ $teamSubs->count() }} shown</div>
            </div>

            @if ($teamSubs->isEmpty())
                <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-6">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white border border-slate-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="8.5" cy="7" r="4"/>
                                <path d="M20 8v6"/><path d="M23 11h-6"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-slate-900">No team subscriptions found</div>
                            <div class="text-sm text-slate-600">Teams with Paddle subscription IDs will appear here.</div>
                        </div>
                    </div>
                </div>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Team</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Plan</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Next bill</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Grace ends</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Subscription</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @foreach ($teamSubs as $t)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-2 text-sm text-slate-600">
                                        <div class="font-semibold text-slate-900">{{ $t->name }}</div>
                                        <div class="text-xs text-slate-500">#{{ $t->id }} · owner #{{ $t->user_id }}</div>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-slate-600">{{ strtoupper($t->billing_plan) }}</td>
                                    <td class="px-4 py-2 text-sm text-slate-600">{{ strtoupper($t->billing_status) }}</td>
                                    <td class="px-4 py-2 text-sm text-slate-600">{{ $t->next_bill_at?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="px-4 py-2 text-sm text-slate-600">{{ $t->grace_ends_at?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="px-4 py-2 text-xs text-slate-500 font-mono">{{ $t->paddle_subscription_id }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>