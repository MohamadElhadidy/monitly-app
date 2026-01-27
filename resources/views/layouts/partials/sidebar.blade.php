@php
    $user = auth()->user();
    $planKey = strtolower($user->billing_plan ?? 'free');
    $isTeamPlan = $planKey === 'team';
    $team = $user?->currentTeam ?? $user?->teams()->first();
    $workspaceName = $team?->name ?? $user->name;
    $isActive = fn (string $pattern) => request()->routeIs($pattern);
@endphp

<!-- Sidebar -->
<div class="flex grow flex-col gap-y-5 overflow-y-auto bg-white dark:bg-[#0a0a0a] border-r border-gray-200 dark:border-white/[0.08] px-6 pb-4">
    <!-- Logo & Workspace Selector -->
    <div class="flex h-16 shrink-0 items-center justify-between">
        <div class="flex items-center gap-x-3 flex-1 min-w-0">
            <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-emerald-500 ring-1 ring-gray-200 dark:ring-white/10">
                <span class="text-white font-bold text-sm">M</span>
            </div>
            <div class="flex items-center gap-x-2 min-w-0 flex-1">
                <span class="text-base font-semibold text-gray-900 dark:text-white truncate">{{ $workspaceName }}</span>
                <button type="button" class="flex-shrink-0 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex flex-1 flex-col">
        <ul role="list" class="flex flex-1 flex-col gap-y-7">
            <!-- Main Navigation -->
            <li>
                <ul role="list" class="-mx-2 space-y-1">
                    <!-- Overview -->
                    <li>
                        <a 
                            href="{{ route('dashboard') }}" 
                            class="group flex gap-x-3 rounded-lg p-2 text-sm leading-6 font-medium transition-all
                                {{ $isActive('dashboard') 
                                    ? 'bg-gray-100 dark:bg-white/[0.06] text-gray-900 dark:text-white' 
                                    : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/[0.06]' 
                                }}"
                        >
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                            Overview
                        </a>
                    </li>

                    <!-- Monitors -->
                    <li>
                        <a 
                            href="{{ route('monitors.index') }}" 
                            class="group flex gap-x-3 rounded-lg p-2 text-sm leading-6 font-medium transition-all
                                {{ $isActive('monitors.*') 
                                    ? 'bg-gray-100 dark:bg-white/[0.06] text-gray-900 dark:text-white' 
                                    : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/[0.06]' 
                                }}"
                        >
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
                            </svg>
                            Monitors
                        </a>
                    </li>

                    <!-- Reports -->
                    <li>
                        <a 
                            href="{{ route('sla.overview') ?? '#' }}" 
                            class="group flex gap-x-3 rounded-lg p-2 text-sm leading-6 font-medium transition-all
                                {{ $isActive('sla.*') 
                                    ? 'bg-gray-100 dark:bg-white/[0.06] text-gray-900 dark:text-white' 
                                    : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/[0.06]' 
                                }}"
                        >
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                            </svg>
                            Reports
                        </a>
                    </li>

                    <!-- Usage -->
                    <li>
                        <a 
                            href="#" 
                            class="group flex gap-x-3 rounded-lg p-2 text-sm leading-6 font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/[0.06] transition-all"
                        >
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z" />
                            </svg>
                            Usage
                        </a>
                    </li>

                    <!-- Teams -->
                    <li>
                        <a 
                            href="#" 
                            class="group flex gap-x-3 rounded-lg p-2 text-sm leading-6 font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/[0.06] transition-all"
                        >
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                            </svg>
                            Teams
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Team Section (if team plan) -->
            @if($isTeamPlan)
            <li>
                <div class="text-xs font-semibold leading-6 text-gray-500 uppercase tracking-wider">Team</div>
                <ul role="list" class="-mx-2 mt-2 space-y-1">
                    <li>
                        <a 
                            href="{{ route('teams.show', $team->id ?? '#') }}" 
                            class="group flex gap-x-3 rounded-lg p-2 text-sm leading-6 font-medium transition-all
                                {{ $isActive('teams.*') 
                                    ? 'bg-gray-100 dark:bg-white/[0.06] text-gray-900 dark:text-white' 
                                    : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/[0.06]' 
                                }}"
                        >
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                            Members
                        </a>
                    </li>

                    <li>
                        <a 
                            href="{{ route('team.notifications') ?? '#' }}" 
                            class="group flex gap-x-3 rounded-lg p-2 text-sm leading-6 font-medium transition-all
                                {{ $isActive('team.notifications') 
                                    ? 'bg-gray-100 dark:bg-white/[0.06] text-gray-900 dark:text-white' 
                                    : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/[0.06]' 
                                }}"
                        >
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                            </svg>
                            Integrations
                        </a>
                    </li>
                </ul>
            </li>
            @endif

            <!-- Settings Section -->
            <li class="mt-auto">
                <div class="text-xs font-semibold leading-6 text-gray-500 uppercase tracking-wider mb-2">Settings</div>
                <ul role="list" class="-mx-2 space-y-1">
                    <!-- General -->
                    <li>
                        <a 
                            href="{{ route('profile.show') }}" 
                            class="group flex gap-x-3 rounded-lg p-2 text-sm leading-6 font-medium transition-all
                                {{ $isActive('profile.show') 
                                    ? 'bg-gray-100 dark:bg-white/[0.06] text-gray-900 dark:text-white' 
                                    : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/[0.06]' 
                                }}"
                        >
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.107-1.204l-.527-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            General
                        </a>
                    </li>

                    <!-- Billing -->
                    <li>
                        <a 
                            href="{{ route('billing.index') }}" 
                            class="group flex gap-x-3 rounded-lg p-2 text-sm leading-6 font-medium transition-all
                                {{ $isActive('billing.*') 
                                    ? 'bg-gray-100 dark:bg-white/[0.06] text-gray-900 dark:text-white' 
                                    : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/[0.06]' 
                                }}"
                        >
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                            </svg>
                            Billing
                        </a>
                    </li>

                    <!-- Integrations -->
                    <li>
                        <a 
                            href="#" 
                            class="group flex gap-x-3 rounded-lg p-2 text-sm leading-6 font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/[0.06] transition-all"
                        >
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 002.25-2.25V6a2.25 2.25 0 00-2.25-2.25H6A2.25 2.25 0 003.75 6v2.25A2.25 2.25 0 006 10.5zm0 9.75h2.25A2.25 2.25 0 0010.5 18v-2.25a2.25 2.25 0 00-2.25-2.25H6a2.25 2.25 0 00-2.25 2.25V18A2.25 2.25 0 006 20.25zm9.75-9.75H18a2.25 2.25 0 002.25-2.25V6A2.25 2.25 0 0018 3.75h-2.25A2.25 2.25 0 0013.5 6v2.25a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            Integrations
                        </a>
                    </li>

                    <!-- Notifications -->
                    <li>
                        <a 
                            href="#" 
                            class="group flex gap-x-3 rounded-lg p-2 text-sm leading-6 font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/[0.06] transition-all"
                        >
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                            </svg>
                            Notifications
                        </a>
                    </li>

                    @if($user->is_admin)
                    <!-- Admin -->
                    <li>
                        <a 
                            href="{{ route('admin.index') }}" 
                            class="group flex gap-x-3 rounded-lg p-2 text-sm leading-6 font-medium transition-all
                                {{ $isActive('admin.*') 
                                    ? 'bg-gray-100 dark:bg-white/[0.06] text-gray-900 dark:text-white' 
                                    : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/[0.06]' 
                                }}"
                        >
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Admin
                        </a>
                    </li>
                    @endif
                </ul>

                <!-- Footer links -->
                <div class="mt-6 flex items-center justify-between text-xs text-gray-500">
                    <a href="#" class="hover:text-gray-700 dark:hover:text-gray-400 transition-colors">Status</a>
                    <a href="#" class="hover:text-gray-700 dark:hover:text-gray-400 transition-colors">Changelog</a>
                    <a href="#" class="hover:text-gray-700 dark:hover:text-gray-400 transition-colors">Docs</a>
                    <a href="#" class="hover:text-gray-700 dark:hover:text-gray-400 transition-colors">Help</a>
                    <a href="#" class="hover:text-gray-700 dark:hover:text-gray-400 transition-colors">Legal</a>
                </div>
            </li>
        </ul>
    </nav>
</div>