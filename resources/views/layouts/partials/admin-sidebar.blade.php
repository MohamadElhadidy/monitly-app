@php
    $nav = [
        ['label' => 'Overview', 'route' => 'admin.index'],
        ['label' => 'Revenue', 'route' => 'admin.revenue'],
        ['label' => 'Subscriptions', 'route' => 'admin.subscriptions'],
        ['label' => 'Payments', 'route' => 'admin.payments', 'badge' => $badges['payment_failures'] ?? 0],
        ['label' => 'Refunds', 'route' => 'admin.refunds'],
        ['label' => 'Users', 'route' => 'admin.users'],
        ['label' => 'Teams', 'route' => 'admin.teams'],
        ['label' => 'Usage & Limits', 'route' => 'admin.usage'],
        ['label' => 'Queues', 'route' => 'admin.queues'],
        ['label' => 'Failed Jobs', 'route' => 'admin.jobs.failed', 'badge' => $badges['failed_jobs'] ?? 0],
        ['label' => 'Webhooks (Paddle)', 'route' => 'admin.webhooks', 'badge' => $badges['webhook_failures'] ?? 0],
        ['label' => 'Errors', 'route' => 'admin.errors'],
        ['label' => 'Notifications Health', 'route' => 'admin.notifications'],
        ['label' => 'Incidents Health', 'route' => 'admin.incidents'],
        ['label' => 'Audit Log', 'route' => 'admin.audit'],
        ['label' => 'Admin Settings', 'route' => 'admin.settings'],
    ];
@endphp

<div class="flex h-full flex-col">
    <div class="px-6 py-6">
        <div class="text-xl font-semibold text-slate-900">Monitly</div>
        <div class="text-xs uppercase tracking-wide text-slate-500">Owner Admin</div>
    </div>

    <nav class="flex-1 space-y-1 px-3 pb-6">
        @foreach($nav as $item)
            @php
                $active = request()->routeIs($item['route']);
                $badge = $item['badge'] ?? 0;
            @endphp
            <a href="{{ route($item['route']) }}"
               class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium {{ $active ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                <span>{{ $item['label'] }}</span>
                @if($badge)
                    <span class="rounded-full bg-rose-500 px-2 py-0.5 text-xs font-semibold text-white">{{ $badge }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    <div class="border-t border-slate-200 px-6 py-4 text-xs text-slate-500">
        Owner-only access
    </div>
</div>
