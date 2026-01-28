<?php

use App\Models\AuditLog;
use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Admin • Overview')]
class extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->can('access-admin'), 403);
    }

    public function with(): array
    {
        $users = User::query()->count();
        $admins = User::query()->where('is_admin', true)->count();
        $teams = Team::query()->count();
        $monitors = Monitor::query()->count();

        $proActive = User::query()->where('billing_plan', 'pro')->where('billing_status', 'active')->count();
        $teamActive = Team::query()->where('billing_plan', 'team')->where('billing_status', 'active')->count();

        $graceUsers = User::query()->where('billing_status', 'grace')->count();
        $graceTeams = Team::query()->where('billing_status', 'grace')->count();

        $bannedUsers = User::query()->whereNotNull('banned_at')->count();

        $estimate = $this->estimateRevenue($proActive, $teamActive);

        $recentAudits = AuditLog::query()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return compact(
            'users', 'admins', 'teams', 'monitors',
            'proActive', 'teamActive',
            'graceUsers', 'graceTeams',
            'bannedUsers',
            'estimate',
            'recentAudits'
        );
    }

    private function estimateRevenue(int $proActive, int $teamActive): array
    {
        $prices = (array) config('billing.estimate_prices', []);

        $proMonthly = (float) ($prices['pro_monthly'] ?? 0);
        $teamMonthly = (float) ($prices['team_monthly'] ?? 0);

        $addonMonitorPack = (float) ($prices['addon_monitor_pack_monthly'] ?? 0);
        $addonSeatPack = (float) ($prices['addon_seat_pack_monthly'] ?? 0);
        $addonInt2 = (float) ($prices['addon_interval_2_monthly'] ?? 0);
        $addonInt1 = (float) ($prices['addon_interval_1_monthly'] ?? 0);

        $proAddonMonitorPacks = (int) User::query()
            ->where('billing_plan', 'pro')
            ->where('billing_status', 'active')
            ->sum('addon_extra_monitor_packs');

        $teamAddonMonitorPacks = (int) Team::query()
            ->where('billing_plan', 'team')
            ->where('billing_status', 'active')
            ->sum('addon_extra_monitor_packs');

        $teamAddonSeatPacks = (int) Team::query()
            ->where('billing_plan', 'team')
            ->where('billing_status', 'active')
            ->sum('addon_extra_seat_packs');

        $proInt2 = User::query()->where('billing_plan', 'pro')->where('billing_status', 'active')->where('addon_interval_override_minutes', 2)->count();
        $proInt1 = User::query()->where('billing_plan', 'pro')->where('billing_status', 'active')->where('addon_interval_override_minutes', 1)->count();

        $teamInt2 = Team::query()->where('billing_plan', 'team')->where('billing_status', 'active')->where('addon_interval_override_minutes', 2)->count();
        $teamInt1 = Team::query()->where('billing_plan', 'team')->where('billing_status', 'active')->where('addon_interval_override_minutes', 1)->count();

        $base = ($proActive * $proMonthly) + ($teamActive * $teamMonthly);

        $addons =
            (($proAddonMonitorPacks + $teamAddonMonitorPacks) * $addonMonitorPack) +
            ($teamAddonSeatPacks * $addonSeatPack) +
            (($proInt2 + $teamInt2) * $addonInt2) +
            (($proInt1 + $teamInt1) * $addonInt1);

        return [
            'base_mrr' => round($base, 2),
            'addons_mrr' => round($addons, 2),
            'total_mrr' => round($base + $addons, 2),
        ];
    }
};
?>

<div class="space-y-6">
    {{-- Sticky header --}}
    <div class="sticky top-0 z-20 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-4 bg-white/80 backdrop-blur border-b border-slate-200">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-xl font-semibold text-slate-900">Admin Dashboard</div>
                <div class="mt-1 text-sm text-slate-600">Support + billing ops + system health.</div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('admin.users') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Users</a>
                <a href="{{ route('admin.system') }}" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2v2"/><path d="M12 20v2"/><path d="M4.93 4.93l1.41 1.41"/><path d="M17.66 17.66l1.41 1.41"/>
                        <path d="M2 12h2"/><path d="M20 12h2"/><path d="M6.34 17.66l-1.41 1.41"/><path d="M19.07 4.93l-1.41 1.41"/>
                        <circle cx="12" cy="12" r="4"/>
                    </svg>
                    System
                </a>
            </div>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
            <div class="text-sm text-slate-600">Users</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $users }}</div>
            <div class="mt-1 text-xs text-slate-500">Admins: {{ $admins }} · Banned: {{ $bannedUsers }}</div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
            <div class="text-sm text-slate-600">Teams</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $teams }}</div>
            <div class="mt-1 text-xs text-slate-500">Active Team subs: {{ $teamActive }}</div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
            <div class="text-sm text-slate-600">Monitors</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $monitors }}</div>
            <div class="mt-1 text-xs text-slate-500">Across all accounts</div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
            <div class="text-sm text-slate-600">Estimated MRR</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">${{ number_format($estimate['total_mrr'], 2) }}</div>
            <div class="mt-1 text-xs text-slate-500">Base: ${{ number_format($estimate['base_mrr'], 2) }} · Add-ons: ${{ number_format($estimate['addons_mrr'], 2) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Recent audits --}}
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6 lg:col-span-2">
            <div class="flex items-center justify-between">
                <div class="text-sm font-semibold text-slate-900">Recent Audit Logs</div>
                <a href="{{ route('admin.audit_logs') }}" class="text-sm font-medium text-slate-700 hover:underline">View all</a>
            </div>

            @if ($recentAudits->isEmpty())
                <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-6">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white border border-slate-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
                                <path d="M8 13h8"/><path d="M8 17h8"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-slate-900">No audit entries yet</div>
                            <div class="text-sm text-slate-600">Once you start managing accounts and billing, actions will appear here.</div>
                        </div>
                    </div>
                </div>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Time</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Action</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Actor</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Subject</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @foreach ($recentAudits as $a)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-2 text-sm text-slate-600">{{ $a->created_at?->format('Y-m-d H:i') }}</td>
                                    <td class="px-4 py-2 text-sm font-medium text-slate-900">{{ $a->action }}</td>
                                    <td class="px-4 py-2 text-sm text-slate-600">{{ $a->actor_type }}{{ $a->actor_id ? ' #'.$a->actor_id : '' }}</td>
                                    <td class="px-4 py-2 text-sm text-slate-600">{{ $a->subject_type ? class_basename($a->subject_type).' #'.$a->subject_id : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Billing watch --}}
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
            <div class="text-sm font-semibold text-slate-900">Billing Watch</div>

            <div class="mt-4 space-y-3 text-sm text-slate-600">
                <div class="flex items-center justify-between"><span>Pro active</span><span class="font-semibold text-slate-900">{{ $proActive }}</span></div>
                <div class="flex items-center justify-between"><span>Team active</span><span class="font-semibold text-slate-900">{{ $teamActive }}</span></div>
                <div class="flex items-center justify-between"><span>Grace (users)</span><span class="font-semibold text-slate-900">{{ $graceUsers }}</span></div>
                <div class="flex items-center justify-between"><span>Grace (teams)</span><span class="font-semibold text-slate-900">{{ $graceTeams }}</span></div>

                <div class="pt-4 border-t border-slate-200">
                    <a href="{{ route('admin.subscriptions') }}" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Subscriptions overview
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>