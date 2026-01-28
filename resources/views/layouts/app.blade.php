<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title . ' - ' : '' }}{{ config('app.name', 'Monitly') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Prevent FOUC -->
    <style>
        [x-cloak] { display: none !important; }
    </style>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @paddleJS
</head>
<body class="h-full font-sans antialiased bg-gray-50">
    @php
        $user = auth()->user();
        $currentTeam = $user?->currentTeam;
        $planKey = strtolower($user->billing_plan ?? 'free');
        
        // Determine billing entity
        $billable = $currentTeam && $currentTeam->paddle_subscription_id ? $currentTeam : $user;
        $billingPlan = $billable->billing_plan ?? 'free';
        $isTeamPlan = $billingPlan === 'team';
    @endphp

    <div class="h-full" x-data="{ 
        sidebarOpen: false,
        searchOpen: false,
        notificationsOpen: false,
        profileOpen: false 
    }">
        
        <!-- Mobile sidebar backdrop -->
        <div 
            x-show="sidebarOpen" 
            x-cloak
            class="relative z-50 lg:hidden" 
            role="dialog" 
            aria-modal="true"
        >
            <div 
                x-show="sidebarOpen"
                x-transition:enter="transition-opacity ease-linear duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-linear duration-300"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm"
                @click="sidebarOpen = false"
            ></div>

            <!-- Mobile sidebar -->
            <div class="fixed inset-0 flex z-50">
                <div 
                    x-show="sidebarOpen"
                    x-transition:enter="transition ease-in-out duration-300 transform"
                    x-transition:enter-start="-translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition ease-in-out duration-300 transform"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="-translate-x-full"
                    class="relative mr-16 flex w-full max-w-xs flex-1"
                >
                    <!-- Close button -->
                    <div class="absolute left-full top-0 flex w-16 justify-center pt-5">
                        <button type="button" class="-m-2.5 p-2.5" @click="sidebarOpen = false">
                            <span class="sr-only">Close sidebar</span>
                            <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Mobile sidebar content -->
                    @include('layouts.partials.sidebar')
                </div>
            </div>
        </div>

        <!-- Static sidebar for desktop -->
        <div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-72 lg:flex-col">
            @include('layouts.partials.sidebar')
        </div>

        <!-- Main content wrapper -->
        <div class="lg:pl-72 h-full flex flex-col">
            
            <!-- Top navigation bar -->
            <div class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 bg-white px-4 shadow-sm sm:gap-x-6 sm:px-6 lg:px-8">
                
                <!-- Mobile menu button -->
                <button 
                    type="button" 
                    class="-m-2.5 p-2.5 text-gray-700 lg:hidden hover:text-gray-900 transition-colors" 
                    @click="sidebarOpen = true"
                >
                    <span class="sr-only">Open sidebar</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <!-- Separator -->
                <div class="h-6 w-px bg-gray-200 lg:hidden"></div>

                <!-- Breadcrumbs/Page title -->
                <div class="flex flex-1 gap-x-4 self-stretch lg:gap-x-6 items-center">
                    {{ $breadcrumbs ?? '' }}
                </div>

                <!-- Right side actions -->
                <div class="flex items-center gap-x-2 lg:gap-x-4">
                    
                    <!-- Quick Actions Dropdown -->
                    <!--<div class="relative hidden lg:block" x-data="{ open: false }">-->
                    <!--    <button -->
                    <!--        @click="open = !open"-->
                    <!--        @click.away="open = false"-->
                    <!--        class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"-->
                    <!--    >-->
                    <!--        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">-->
                    <!--            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />-->
                    <!--        </svg>-->
                    <!--        <span class="hidden xl:inline">New</span>-->
                    <!--    </button>-->

                        <!-- Quick Actions Dropdown -->
                    <!--    <div -->
                    <!--        x-show="open"-->
                    <!--        x-cloak-->
                    <!--        x-transition:enter="transition ease-out duration-100"-->
                    <!--        x-transition:enter-start="transform opacity-0 scale-95"-->
                    <!--        x-transition:enter-end="transform opacity-100 scale-100"-->
                    <!--        x-transition:leave="transition ease-in duration-75"-->
                    <!--        x-transition:leave-start="transform opacity-100 scale-100"-->
                    <!--        x-transition:leave-end="transform opacity-0 scale-95"-->
                    <!--        class="absolute right-0 z-10 mt-2 w-56 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-gray-900/5 focus:outline-none"-->
                    <!--    >-->
                    <!--        <div class="py-1">-->
                    <!--            <a href="{{ route('monitors.create') }}" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">-->
                    <!--                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">-->
                    <!--                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />-->
                    <!--                </svg>-->
                    <!--                New Monitor-->
                    <!--            </a>-->
                    <!--            @if($isTeamPlan)-->
                    <!--            <a href="{{ route('team.members', $currentTeam->id) }}" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">-->
                    <!--                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">-->
                    <!--                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" />-->
                    <!--                </svg>-->
                    <!--                Invite Team Member-->
                    <!--            </a>-->
                    <!--            @endif-->
                    <!--        </div>-->
                    <!--    </div>-->
                    <!--</div>-->

                    <!-- Help & Docs -->
                    <a 
                        href="{{route('docs.index')}}" 
                        class="hidden lg:flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                        </svg>
                        <span class="hidden xl:inline">Docs</span>
                    </a>

                    <!-- Notifications -->
                    <div class="relative" x-data="{ open: false }">
                        <button 
                            type="button" 
                            @click="open = !open"
                            @click.away="open = false"
                            class="relative p-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                        >
                            <span class="sr-only">View notifications</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                            </svg>
                            <!-- Notification badge (if has notifications) -->
                            <span class="absolute top-1 right-1 block h-2 w-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                        </button>

                        <!-- Notifications dropdown -->
                        <div 
                            x-show="open"
                            x-cloak
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute right-0 z-10 mt-2 w-80 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-gray-900/5 focus:outline-none"
                        >
                            <div class="p-4 border-b border-gray-100">
                                <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
                            </div>
                            <div class="py-2 max-h-96 overflow-y-auto">
                                <!-- Example notification -->
                                <a href="#" class="flex gap-3 px-4 py-3 hover:bg-gray-50 transition-colors">
                                    <div class="flex-shrink-0">
                                        <div class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center">
                                            <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900">Monitor Down</p>
                                        <p class="text-xs text-gray-600 truncate">api.example.com is not responding</p>
                                        <p class="text-xs text-gray-500 mt-1">2 minutes ago</p>
                                    </div>
                                </a>
                                
                                <div class="px-4 py-3 text-center border-t border-gray-100">
                                    <a href="#" class="text-sm font-medium text-emerald-600 hover:text-emerald-700">
                                        View all notifications
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Separator -->
                    <div class="hidden lg:block lg:h-6 lg:w-px lg:bg-gray-200"></div>

                    <!-- Profile dropdown -->
                    <div class="relative" x-data="{ open: false }">
                        <button 
                            type="button" 
                            class="flex items-center gap-x-2 p-1.5 hover:bg-gray-100 rounded-lg transition-colors"
                            @click="open = !open"
                            @click.away="open = false"
                        >
                            <div class="flex items-center gap-x-3">
                                <div class="h-9 w-9 rounded-full bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center text-white text-sm font-semibold shadow-sm">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </div>
                                <div class="hidden lg:block text-left">
                                    <p class="text-sm font-semibold text-gray-900">{{ Str::limit($user->name, 20) }}</p>
                                    <p class="text-xs text-gray-500 capitalize">{{ $billingPlan }} Plan</p>
                                </div>
                                <svg class="hidden lg:block h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>

                        <!-- Dropdown menu -->
                        <div 
                            x-show="open"
                            x-cloak
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute right-0 z-10 mt-2 w-64 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-gray-900/5 focus:outline-none"
                        >
                            <!-- User info -->
                            <div class="px-4 py-3 border-b border-gray-100">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $user->name }}</p>
                                <p class="text-xs text-gray-600 truncate">{{ $user->email }}</p>
                            </div>

                            <!-- Menu items -->
                            <div class="py-1">
                                <a href="{{ route('profile.show') }}" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Your Profile
                                </a>
                                
                                @if($currentTeam)
                                <a href="{{ route('team.show', $currentTeam->id) }}" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                    </svg>
                                    Team Settings
                                </a>
                                @endif

                                <a href="{{ route('billing.index') }}" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                                    </svg>
                                    Billing & Plans
                                </a>
                            </div>

                            <!-- Sign out -->
                            <div class="py-1 border-t border-gray-100">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                                        </svg>
                                        Sign out
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
    @stack('scripts')
    <x-banner />
</body>
</html>