<!-- Modern Sidebar Component -->
<div class="flex h-full flex-col gap-y-5 overflow-y-auto bg-white border-r border-gray-200">
    
    <!-- Logo & Brand -->
    <div class="flex h-16 shrink-0 items-center px-6 border-b border-gray-100">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-x-3 group">
            <div class="relative h-9 w-9 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center shadow-lg shadow-emerald-500/30 group-hover:shadow-emerald-500/40 transition-shadow">
                <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <span class="text-xl font-bold bg-gradient-to-r from-gray-900 to-gray-700 bg-clip-text text-transparent">Monitly</span>
        </a>
    </div>

    <div class="flex flex-1 flex-col px-4">
        
        <!-- Plan Badge & Upgrade CTA -->
        @php
            // Use variables from parent layout (app.blade.php):
            // $user, $currentTeam, $billable, $billingPlan are already defined
            $planName = ucfirst($billingPlan ?? 'free');
            
            $planColors = [
                'Free' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'border' => 'border-gray-200', 'dot' => 'bg-gray-400'],
                'Pro' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-700', 'border' => 'border-blue-200', 'dot' => 'bg-blue-500'],
                'Team' => ['bg' => 'bg-purple-50', 'text' => 'text-purple-700', 'border' => 'border-purple-200', 'dot' => 'bg-purple-500'],
                'Business' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'border' => 'border-amber-200', 'dot' => 'bg-amber-500'],
            ];
            $colors = $planColors[$planName] ?? $planColors['Free'];
        @endphp
        
        <div class="rounded-lg {{ $colors['bg'] }} border {{ $colors['border'] }} p-3 mb-4">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-2 w-2 rounded-full {{ $colors['dot'] }}"></span>
                    <span class="text-xs font-semibold {{ $colors['text'] }}">{{ $planName }} Plan</span>
                </div>
                @if(!in_array($billingPlan, ['team', 'business'], true))
                    <a href="{{ route('billing.index') }}" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 transition-colors">
                        Upgrade →
                    </a>
                @endif
            </div>
            
            <!-- Usage Stats -->
            @php
                if ($billable instanceof \App\Models\Team) {
                    $monitorCount = $billable->monitors()->count();
                    $monitorLimit = \App\Services\Billing\PlanLimits::monitorLimitForTeam($billable);
                } else {
                    $monitorCount = \App\Models\Monitor::where('user_id', $user->id)->count();
                    $monitorLimit = \App\Services\Billing\PlanLimits::monitorLimitForUser($user);
                }
                $percentage = $monitorLimit > 0 ? min(100, ($monitorCount / $monitorLimit) * 100) : 0;
            @endphp
            
            <div class="space-y-1">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-600">Monitors</span>
                    <span class="text-xs font-medium {{ $colors['text'] }}">{{ $monitorCount }}/{{ $monitorLimit }}</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-300 {{ $percentage >= 90 ? 'bg-red-500' : ($percentage >= 70 ? 'bg-yellow-500' : 'bg-emerald-500') }}" 
                         style="width: {{ $percentage }}%"></div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex flex-1 flex-col gap-y-6">
            
            <!-- Main Navigation -->
            <div>
                <h3 class="px-2 mb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Main</h3>
                <ul role="list" class="space-y-1">
                    
                    <!-- Dashboard -->
                    <li>
                        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-700 hover:bg-gray-50 hover:text-emerald-700' }} group flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm transition-all">
                            <svg class="h-5 w-5 {{ request()->routeIs('dashboard') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-emerald-600' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                            </svg>
                            <span>Overview</span>
                        </a>
                    </li>

                    <!-- Monitors -->
                    <li>
                        <a href="{{ route('monitors.index') }}" class="{{ request()->routeIs('monitors.*') ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-700 hover:bg-gray-50 hover:text-emerald-700' }} group flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm transition-all">
                            <svg class="h-5 w-5 {{ request()->routeIs('monitors.*') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-emerald-600' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
                            </svg>
                            <span>Monitors</span>
                            @if($monitorCount > 0)
                            <span class="ml-auto inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                {{ $monitorCount }}
                            </span>
                            @endif
                        </a>
                    </li>

                    <!-- Incidents -->
                    <li>
                        <a href="{{ route('incidents.index') }}" class="{{ request()->routeIs('incidents.*') ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-700 hover:bg-gray-50 hover:text-emerald-700' }} group flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm transition-all">
                            <svg class="h-5 w-5 {{ request()->routeIs('incidents.*') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-emerald-600' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                            <span>Incidents</span>
                        </a>
                    </li>

                    <!-- Reports -->
                    <li>
                        <a href="{{ route('sla.overview') }}" class="{{ request()->routeIs('sla.*') ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-700 hover:bg-gray-50 hover:text-emerald-700' }} group flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm transition-all">
                            <svg class="h-5 w-5 {{ request()->routeIs('sla.*') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-emerald-600' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                            </svg>
                            <span>Reports</span>
                        </a>
                    </li>

                    <!-- Status Pages -->
                    <li>
                        <a href="{{ route('public.status.legacy') }}" target="_blank" class="text-gray-700 hover:bg-gray-50 hover:text-emerald-700 group flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm transition-all">
                            <svg class="h-5 w-5 text-gray-400 group-hover:text-emerald-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                            </svg>
                            <span>Status Page</span>
                            <svg class="h-3.5 w-3.5 text-gray-400 ml-auto" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Settings Section -->
            <div>
                <h3 class="px-2 mb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Settings</h3>
                <ul role="list" class="space-y-1">
                    
                    <!-- Team (Team plan only) -->
                    @if($currentTeam)
                    <li>
                        <a href="{{ route('team.show', $currentTeam->id) }}" class="{{ request()->routeIs('team.*') ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-700 hover:bg-gray-50 hover:text-emerald-700' }} group flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm transition-all">
                            <svg class="h-5 w-5 {{ request()->routeIs('team.*') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-emerald-600' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                            </svg>
                            <span>Team</span>
                        </a>
                    </li>
                    @endif

                    <!-- Notifications -->
                    @if(in_array($billingPlan, ['team', 'business'], true) && $currentTeam)
                    <li>
                        <a href="{{ route('team.notifications', $currentTeam->id) }}" class="{{ request()->routeIs('team.notifications') ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-700 hover:bg-gray-50 hover:text-emerald-700' }} group flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm transition-all">
                            <svg class="h-5 w-5 {{ request()->routeIs('team.notifications') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-emerald-600' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                            </svg>
                            <span>Notifications</span>
                        </a>
                    </li>
                    @endif

                    <!-- Integrations -->
                    <li>
                        <a href="{{ route('integrations.index') }}" class="{{ request()->routeIs('integrations.*') ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-700 hover:bg-gray-50 hover:text-emerald-700' }} group flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm transition-all">
                            <svg class="h-5 w-5 {{ request()->routeIs('integrations.*') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-emerald-600' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 002.25-2.25V6a2.25 2.25 0 00-2.25-2.25H6A2.25 2.25 0 003.75 6v2.25A2.25 2.25 0 006 10.5zm0 9.75h2.25A2.25 2.25 0 0010.5 18v-2.25a2.25 2.25 0 00-2.25-2.25H6a2.25 2.25 0 00-2.25 2.25V18A2.25 2.25 0 006 20.25zm9.75-9.75H18a2.25 2.25 0 002.25-2.25V6A2.25 2.25 0 0018 3.75h-2.25A2.25 2.25 0 0013.5 6v2.25a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            <span>Integrations</span>
                        </a>
                    </li>

                    <!-- Billing -->
                    <li>
                        <a href="{{ route('billing.index') }}" class="{{ request()->routeIs('billing.*') ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-700 hover:bg-gray-50 hover:text-emerald-700' }} group flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm transition-all">
                            <svg class="h-5 w-5 {{ request()->routeIs('billing.*') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-emerald-600' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                            </svg>
                            <span>Billing</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Admin Section -->
            @can('access-admin')
            <div class="pt-6 border-t border-gray-200">
                <h3 class="px-2 mb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Admin</h3>
                <ul role="list" class="space-y-1">
                    <li>
                        <a href="{{ route('admin.index') }}" class="{{ request()->routeIs('admin.*') ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-700 hover:bg-gray-50 hover:text-emerald-700' }} group flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm transition-all">
                            <svg class="h-5 w-5 {{ request()->routeIs('admin.*') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-emerald-600' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span>Admin Panel</span>
                        </a>
                    </li>
                </ul>
            </div>
            @endcan

            <!-- Bottom Section - Support & Status -->
            <div class="mt-auto space-y-3 pb-4">
                
                <!-- Help & Support -->
                <a href="mailto:support@monitly.app" class="group flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-emerald-700 transition-all">
                    <svg class="h-5 w-5 text-gray-400 group-hover:text-emerald-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                    </svg>
                    <span>Help & Support</span>
                </a>

                <!-- System Status -->
                <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2.5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="flex h-2 w-2 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                            </span>
                            <span class="text-xs font-medium text-gray-700">All Systems Operational</span>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </div>
</div>
