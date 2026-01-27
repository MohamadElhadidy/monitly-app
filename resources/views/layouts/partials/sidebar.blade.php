<!-- Sidebar component for both mobile and desktop -->
<div class="flex grow flex-col gap-y-5 overflow-y-auto bg-white border-r border-gray-200 px-6 pb-4">
    <!-- Logo -->
    <div class="flex h-16 shrink-0 items-center">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-x-3">
            <div class="h-8 w-8 rounded-lg bg-emerald-600 flex items-center justify-center">
                <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <span class="text-xl font-bold text-gray-900">Monitly</span>
        </a>
    </div>

    <!-- Plan badge -->
    @php
        $planName = ucfirst(auth()->user()->billing_plan ?? 'free');
        $planColors = [
            'Free' => 'bg-gray-100 text-gray-700 border-gray-200',
            'Pro' => 'bg-blue-100 text-blue-700 border-blue-200',
            'Team' => 'bg-purple-100 text-purple-700 border-purple-200',
        ];
        $planColor = $planColors[$planName] ?? $planColors['Free'];
    @endphp
    
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium border {{ $planColor }}">
                {{ $planName }} Plan
            </span>
        </div>
        @if($planName !== 'Team')
            <a href="{{ route('billing.index') }}" class="text-xs font-medium text-emerald-600 hover:text-emerald-700">
                Upgrade
            </a>
        @endif
    </div>

    <!-- Navigation -->
    <nav class="flex flex-1 flex-col">
        <ul role="list" class="flex flex-1 flex-col gap-y-7">
            <li>
                <ul role="list" class="-mx-2 space-y-1">
                    <!-- Dashboard -->
                    <li>
                        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'bg-gray-100 text-emerald-600' : 'text-gray-700 hover:text-emerald-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold transition-colors">
                            <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                            Dashboard
                        </a>
                    </li>

                    <!-- Monitors -->
                    <li>
                        <a href="{{ route('monitors.index') }}" class="{{ request()->routeIs('monitors.*') ? 'bg-gray-100 text-emerald-600' : 'text-gray-700 hover:text-emerald-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold transition-colors">
                            <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
                            </svg>
                            Monitors
                        </a>
                    </li>

                    <!-- SLA Reports -->
                    <li>
                        <a href="{{ route('sla.overview') }}" class="{{ request()->routeIs('sla.*') ? 'bg-gray-100 text-emerald-600' : 'text-gray-700 hover:text-emerald-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold transition-colors">
                            <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                            </svg>
                            SLA Reports
                        </a>
                    </li>

                    <!-- Divider -->
                    <li class="pt-3">
                        <div class="text-xs font-semibold leading-6 text-gray-500">Settings</div>
                    </li>

                    <!-- Notifications (Team plan only) -->
                    @if($isTeamPlan && $team)
                    <li>
                        <a href="{{ route('team.notifications', $team->id) }}" class="{{ request()->routeIs('team.notifications') ? 'bg-gray-100 text-emerald-600' : 'text-gray-700 hover:text-emerald-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold transition-colors">
                            <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                            </svg>
                            Notifications
                        </a>
                    </li>
                    @endif

                    <!-- Billing -->
                    <li>
                        <a href="{{ route('billing.index') }}" class="{{ request()->routeIs('billing.*') ? 'bg-gray-100 text-emerald-600' : 'text-gray-700 hover:text-emerald-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold transition-colors">
                            <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                            </svg>
                            Billing
                        </a>
                    </li>

                    <!-- API Keys -->
                    <li>
                        <a href="#" class="text-gray-700 hover:text-emerald-600 hover:bg-gray-50 group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold transition-colors">
                            <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                            </svg>
                            API Keys
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Admin section (if user is admin) -->
            @can('access-admin')
            <li>
                <div class="text-xs font-semibold leading-6 text-gray-500">Admin</div>
                <ul role="list" class="-mx-2 mt-2 space-y-1">
                    <li>
                        <a href="{{ route('admin.index') }}" class="{{ request()->routeIs('admin.*') ? 'bg-gray-100 text-emerald-600' : 'text-gray-700 hover:text-emerald-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold transition-colors">
                            <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Admin Panel
                        </a>
                    </li>
                </ul>
            </li>
            @endcan

            <!-- Bottom section -->
            <li class="mt-auto">
                <!-- Usage stats -->
                <div class="rounded-lg bg-gray-50 border border-gray-200 p-3 mb-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-gray-700">Monitor Usage</span>
                        <span class="text-xs text-gray-600">{{ $user->monitors()->count() }}/{{ \App\Services\Billing\PlanLimits::baseMonitorLimit($planKey) }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                        @php
                            $percentage = min(100, ($user->monitors()->count() / \App\Services\Billing\PlanLimits::baseMonitorLimit($planKey)) * 100);
                        @endphp
                        <div class="bg-emerald-600 h-1.5 rounded-full" style="width: {{ $percentage }}%"></div>
                    </div>
                </div>

                <!-- Support link -->
                <a href="#" class="group -mx-2 flex gap-x-3 rounded-md p-2 text-sm font-semibold leading-6 text-gray-700 hover:bg-gray-50 hover:text-emerald-600 transition-colors">
                    <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                    </svg>
                    Support
                </a>
            </li>
        </ul>
    </nav>
</div>