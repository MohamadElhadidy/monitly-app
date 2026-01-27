<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
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
        /* Initialize theme before page renders */
        html:not(.dark):not(.light) { visibility: hidden; }
    </style>

    <!-- Theme initialization script (inline to prevent flash) -->
    <script>
        (function() {
            const theme = localStorage.getItem('theme') || 
                         (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.classList.add(theme);
        })();
    </script>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @paddleJS
</head>
<body class="h-full font-sans antialiased bg-white dark:bg-[#0a0a0a] text-gray-900 dark:text-gray-100">
    @php
        $user = auth()->user();
        $planKey = strtolower($user->billing_plan ?? 'free');
        $isTeamPlan = $planKey === 'team';
        $team = $user?->currentTeam ?? $user?->teams()->first();
        $workspaceName = $team?->name ?? $user->name;
    @endphp

    <div class="h-full" x-data="{ sidebarOpen: false }" x-init="$nextTick(() => { if (typeof themeManager !== 'undefined') { themeManager() } })">
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
                class="fixed inset-0 bg-black/80 backdrop-blur-sm"
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
            <div class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0a0a0a] px-4 sm:gap-x-6 sm:px-6 lg:px-8">
                <!-- Mobile menu button -->
                <button 
                    type="button" 
                    class="-m-2.5 p-2.5 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 lg:hidden" 
                    @click="sidebarOpen = true"
                >
                    <span class="sr-only">Open sidebar</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <!-- Separator -->
                <div class="h-6 w-px bg-gray-200 dark:bg-white/[0.08] lg:hidden"></div>

                <!-- Breadcrumbs/Page title -->
                <div class="flex flex-1 gap-x-4 self-stretch lg:gap-x-6 items-center">
                    {{ $breadcrumbs ?? '' }}
                </div>

                <!-- Right side actions -->
                <div class="flex items-center gap-x-4 lg:gap-x-6">
                    <!-- Theme Toggle -->
                    <button 
                        type="button"
                        @click="$dispatch('toggle-theme')"
                        x-data="{ theme: localStorage.getItem('theme') || 'dark' }"
                        @toggle-theme.window="theme = theme === 'dark' ? 'light' : 'dark'; localStorage.setItem('theme', theme); document.documentElement.classList.toggle('dark'); document.documentElement.classList.toggle('light');"
                        class="-m-2.5 p-2.5 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
                        title="Toggle theme"
                    >
                        <span class="sr-only">Toggle theme</span>
                        <!-- Sun icon (show in dark mode) -->
                        <svg x-show="theme === 'dark'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                        </svg>
                        <!-- Moon icon (show in light mode) -->
                        <svg x-show="theme === 'light'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                        </svg>
                    </button>

                    <!-- Search -->
                    @if(isset($search))
                        {{ $search }}
                    @else
                        <button type="button" class="hidden lg:flex -m-2.5 p-2.5 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 items-center gap-2 text-sm">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                            <span class="hidden xl:inline text-gray-500">Search</span>
                            <kbd class="hidden xl:inline-flex items-center px-2 py-0.5 text-xs font-semibold text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-white/[0.06] border border-gray-200 dark:border-white/[0.08] rounded">
                                ⌘K
                            </kbd>
                        </button>
                    @endif

                    <!-- Docs link -->
                    <a href="#" class="hidden lg:block text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                        Docs
                    </a>

                    <!-- Help link -->
                    <a href="#" class="hidden lg:block text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                        Help
                    </a>

                    <!-- Notifications -->
                    <div class="relative" x-data="{ open: false }">
                        <button 
                            type="button" 
                            @click="open = !open"
                            class="-m-2.5 p-2.5 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 relative"
                        >
                            <span class="sr-only">View notifications</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                            </svg>
                        </button>
                    </div>

                    <!-- Separator -->
                    <div class="hidden lg:block lg:h-6 lg:w-px lg:bg-gray-200 dark:lg:bg-white/[0.08]"></div>

                    <!-- Profile dropdown -->
                    <div class="relative" x-data="{ open: false }">
                        <button 
                            type="button" 
                            class="flex items-center gap-x-3 -m-1.5 p-1.5 hover:bg-gray-100 dark:hover:bg-white/[0.06] rounded-lg transition-colors"
                            @click="open = !open"
                            @click.away="open = false"
                        >
                            <div class="flex items-center gap-x-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500 text-white text-sm font-semibold ring-1 ring-gray-200 dark:ring-white/10">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </div>
                                <span class="hidden lg:flex lg:items-center">
                                    <span class="text-sm font-semibold leading-6 text-gray-900 dark:text-white">{{ Str::limit($workspaceName, 20) }}</span>
                                    <svg class="ml-2 h-5 w-5 text-gray-500 dark:text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                    </svg>
                                </span>
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
                            class="absolute right-0 z-10 mt-2.5 w-64 origin-top-right rounded-lg bg-white dark:bg-[#1a1a1a] shadow-lg ring-1 ring-gray-200 dark:ring-white/10 focus:outline-none"
                        >
                            <div class="px-4 py-3 border-b border-gray-200 dark:border-white/[0.08]">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $user->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $user->email }}</p>
                            </div>
                            <div class="py-1">
                                <a href="{{ route('profile.show') }}" class="flex items-center gap-x-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/[0.06] transition-colors">
                                    <svg class="h-4 w-4 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                    </svg>
                                    Account
                                    <kbd class="ml-auto text-xs text-gray-500">A</kbd>
                                </a>
                               
                            </div>
                            <div class="py-1 border-t border-gray-200 dark:border-white/[0.08]">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-x-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/[0.06] transition-colors">
                                        <svg class="h-4 w-4 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                                        </svg>
                                        Sign out
                                        <kbd class="ml-auto text-xs text-gray-500">Q</kbd>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto bg-gray-50 dark:bg-[#0a0a0a]">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
    
    <x-banner />
</body>
</html>