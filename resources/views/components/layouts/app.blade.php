<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Monitly') }} - Uptime Monitoring</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    <style>[x-cloak]{display:none !important;}</style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @paddleJS
</head>

<body class="font-sans antialiased bg-gradient-to-br from-slate-50 via-white to-slate-50 text-slate-900">
    <x-banner />

    @php
        $user = auth()->user();
        $planKey = strtolower($user->billing_plan ?? 'free');
        $isTeamPlan = $planKey === 'team';
        $isProPlan = $planKey === 'pro';
        $team = $user?->currentTeam ?? $user?->teams()->first();
        $workspaceName = $isTeamPlan ? ($team?->name ?? $user->name) : $user->name;
        
        $planLabel = match($planKey) {
            'pro' => '🚀 Pro',
            'team' => '👥 Team',
            default => '⭐ Free',
        };

        $pageTitle = match (true) {
            request()->routeIs('monitors.*') => 'Monitors',
            request()->routeIs('sla.*') => 'SLA & Reports',
            request()->routeIs('billing.*') => 'Billing',
            request()->routeIs('profile.show') => 'Settings',
            request()->routeIs('admin.*') => 'Admin',
            default => 'Dashboard',
        };

        $isActive = fn (string $pattern) => request()->routeIs($pattern);
        
        $navItemClass = fn (bool $active) => 'px-4 py-2.5 rounded-lg font-medium transition-all duration-200 flex items-center gap-3 ' . 
            ($active 
                ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
            );
    @endphp

    <!-- Sidebar + Main Layout -->
    <div class="flex h-screen bg-white" x-data="{ sidebarOpen: false }">
        <!-- Sidebar -->
        <aside class="w-64 border-r border-slate-200 bg-gradient-to-b from-slate-50 to-white overflow-y-auto hidden lg:flex flex-col fixed lg:relative h-full z-50 lg:z-auto">
            <!-- Logo & Branding -->
            <div class="p-6 border-b border-slate-200">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center shadow-lg group-hover:shadow-xl transition-shadow font-bold text-white text-lg">
                        M
                    </div>
                    <div>
                        <div class="text-lg font-bold text-slate-900">Monitly</div>
                        <div class="text-xs text-slate-500">Monitor</div>
                    </div>
                </a>
            </div>

            <!-- Current Plan Badge -->
            <div class="px-6 py-4 mx-3 my-4 rounded-xl border-2 border-slate-200 bg-gradient-to-br from-slate-50 to-slate-100">
                <div class="text-xs text-slate-600 font-semibold uppercase tracking-wide mb-2">Current Plan</div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-bold text-slate-900">{{ $planLabel }}</span>
                    <a href="{{ route('billing.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">
                        Upgrade →
                    </a>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-3 py-6 space-y-2">
                <!-- Dashboard -->
                <a href="{{ route('dashboard') }}" class="{{ $navItemClass($isActive('dashboard')) }}">
                    <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="12 3 20 7.5 20 16.5 12 21 4 16.5 4 7.5 12 3"></polyline>
                        <line x1="12" y1="12" x2="20" y2="7.5"></line>
                        <line x1="12" y1="12" x2="12" y2="21"></line>
                        <line x1="12" y1="12" x2="4" y2="7.5"></line>
                    </svg>
                    <span>Dashboard</span>
                </a>

                <!-- Monitors -->
                <a href="{{ route('monitors.index') }}" class="{{ $navItemClass($isActive('monitors.*')) }}">
                    <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                    <span>Monitors</span>
                </a>

                <!-- SLA & Reports -->
                <a href="{{ route('sla.overview') ?? '#' }}" class="{{ $navItemClass($isActive('sla.*')) }}">
                    <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <polyline points="19 12 16 9 16 15"></polyline>
                        <polyline points="5 12 8 9 8 15"></polyline>
                    </svg>
                    <span>Reports</span>
                </a>

                <!-- Team (Show only for Team Plan) -->
                @if ($isTeamPlan)
                    <div class="pt-4 mt-4 border-t border-slate-200">
                        <div class="px-3 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">Team</div>
                        
                        <a href="{{ route('teams.show', $team->id ?? '#') }}" class="{{ $navItemClass($isActive('teams.*')) }}">
                            <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            <span>Team Members</span>
                        </a>

                        <a href="{{ route('team.notifications') ?? '#' }}" class="{{ $navItemClass($isActive('team.notifications')) }}">
                            <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                            <span>Integrations</span>
                        </a>
                    </div>
                @endif

                <!-- Billing -->
                <div class="pt-4 mt-4 border-t border-slate-200">
                    <a href="{{ route('billing.index') }}" class="{{ $navItemClass($isActive('billing.*')) }}">
                        <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                            <line x1="1" y1="10" x2="23" y2="10"></line>
                        </svg>
                        <span>Billing</span>
                    </a>
                </div>

                <!-- Settings & Admin -->
                <div class="pt-4 mt-4 border-t border-slate-200">
                    <a href="{{ route('profile.show') }}" class="{{ $navItemClass($isActive('profile.show')) }}">
                        <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="1"></circle>
                            <path d="M12 1v6m0 6v6"></path>
                            <path d="M4.22 4.22l4.24 4.24m-4.24 7.08l4.24 4.24"></path>
                            <path d="M1 12h6m6 0h6"></path>
                            <path d="M4.22 19.78l4.24-4.24m7.08 4.24l4.24-4.24"></path>
                        </svg>
                        <span>Settings</span>
                    </a>

                    @if ($user->is_admin)
                        <a href="{{ route('admin.index') }}" class="{{ $navItemClass($isActive('admin.*')) }}">
                            <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="1"></circle>
                                <path d="M12 1v6m0 6v6"></path>
                                <path d="M19 5l-4.24 4.24"></path>
                                <path d="M5 19l4.24-4.24M19 19l-4.24-4.24"></path>
                            </svg>
                            <span>Admin</span>
                        </a>
                    @endif
                </div>
            </nav>

            <!-- User Profile Section -->
            <div class="border-t border-slate-200 p-4">
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-slate-100 transition">
                        <img src="{{ $user->profile_photo_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($user->name) }}" 
                             alt="{{ $user->name }}" 
                             class="w-10 h-10 rounded-full object-cover">
                        <div class="flex-1 text-left">
                            <div class="text-sm font-semibold text-slate-900">{{ $user->name }}</div>
                            <div class="text-xs text-slate-500">{{ $user->email }}</div>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 transition" :class="{ 'rotate-180': open }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>

                    <!-- User Menu Dropdown -->
                    <div x-show="open" @click.away="open = false" 
                         x-transition 
                         class="absolute bottom-full left-0 right-0 mb-2 bg-white border border-slate-200 rounded-lg shadow-lg z-50">
                        <a href="{{ route('profile.show') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 border-b border-slate-100">
                            Profile Settings
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="block">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 rounded-b-lg">
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Mobile Overlay -->
        <div x-show="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden" x-transition></div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Header -->
            <header class="border-b border-slate-200 bg-white shadow-sm sticky top-0 z-40">
                <div class="flex items-center justify-between h-16 px-4 sm:px-6 lg:px-8">
                    <!-- Mobile Menu Button -->
                    <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded-lg hover:bg-slate-100">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    <!-- Page Title -->
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900">{{ $pageTitle }}</h1>

                    <!-- Right Actions -->
                    <div class="flex items-center gap-4">
                        <!-- Plan Badge -->
                        <div class="hidden sm:flex items-center gap-2 px-3 py-1 rounded-full text-sm font-semibold {{ 
                            match($planKey) {
                                'pro' => 'bg-blue-100 text-blue-700',
                                'team' => 'bg-purple-100 text-purple-700',
                                default => 'bg-slate-100 text-slate-700'
                            }
                        }}">
                            <span class="w-2 h-2 rounded-full {{ 
                                match($planKey) {
                                    'pro' => 'bg-blue-500',
                                    'team' => 'bg-purple-500',
                                    default => 'bg-slate-500'
                                }
                            }}"></span>
                            {{ $planLabel }}
                        </div>

                        <!-- Notifications -->
                        <button class="p-2 rounded-lg text-slate-600 hover:bg-slate-100 relative">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                            <span class="absolute top-1 right-1 w-3 h-3 bg-red-500 rounded-full"></span>
                        </button>

                        <!-- User Menu -->
                        <button class="flex items-center gap-2 p-2 rounded-lg hover:bg-slate-100">
                            <img src="{{ $user->profile_photo_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($user->name) }}" 
                                 alt="{{ $user->name }}" 
                                 class="w-8 h-8 rounded-full object-cover">
                        </button>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>