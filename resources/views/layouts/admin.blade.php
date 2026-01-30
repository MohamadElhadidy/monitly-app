<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title . ' - ' : '' }}{{ config('app.name', 'Monitly') }} Admin</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <style>
        [x-cloak] { display: none !important; }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full font-sans antialiased bg-slate-50">
    @php
        $metrics = app(\App\Services\Admin\AdminMetrics::class);
        $badges = $metrics->navBadges();
        $settings = app(\App\Services\Admin\AdminSettingsService::class)->getSettings();
    @endphp

    <div class="min-h-screen" x-data="{ sidebarOpen: false }">
        <div class="lg:hidden">
            <div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-40 bg-slate-900/70" @click="sidebarOpen = false"></div>
            <div class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-lg" x-show="sidebarOpen" x-cloak>
                @include('layouts.partials.admin-sidebar', ['badges' => $badges])
            </div>
        </div>

        <div class="hidden lg:fixed lg:inset-y-0 lg:flex lg:w-72 lg:flex-col">
            <div class="flex flex-col flex-grow border-r border-slate-200 bg-white">
                @include('layouts.partials.admin-sidebar', ['badges' => $badges])
            </div>
        </div>

        <div class="lg:pl-72">
            <div class="sticky top-0 z-30 border-b border-slate-200 bg-white/80 backdrop-blur">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <button class="lg:hidden text-slate-600" @click="sidebarOpen = true">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 6h18M3 12h18M3 18h18" />
                            </svg>
                        </button>
                        <div>
                            <div class="text-sm uppercase tracking-wide text-slate-500">Owner Console</div>
                            <div class="text-lg font-semibold text-slate-900">Admin Console</div>
                        </div>
                    </div>
                    <div class="text-sm text-slate-600">{{ auth()->user()?->email }}</div>
                </div>
            </div>

            @if($settings->read_only_mode)
                <div class="mx-4 mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 sm:mx-6 lg:mx-8">
                    Read-only mode is enabled. Admin actions will be blocked from making changes.
                </div>
            @endif

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
