<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Monitly') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    <style>[x-cloak]{display:none !important;}</style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
        @paddleJS
</head>

<body class="h-full font-sans antialiased bg-slate-50 text-slate-900">
<x-banner />

@php
    $user = auth()->user();

    // Jetstream creates a personal team for every user when Teams are enabled.
    // Our business rule: show Team features ONLY if the account is on the Team plan.
    $rawPlan =
        data_get($user, 'billing.plan')
        ?? data_get($user, 'plan')
        ?? null;

    $planKey = is_string($rawPlan) ? strtolower(trim($rawPlan)) : null;
    $isTeamPlan = $planKey === 'team';

    // Safe team reference (may exist even for Individual plans due to Jetstream personal team)
    $team = $user?->currentTeam
        ?? $user?->ownedTeams()->first()
        ?? $user?->teams()->first();

    // Workspace label: for Individual plans, don't surface personal-team name (keep it "Account/User")
    $workspaceName = $isTeamPlan
        ? ($user?->currentTeam?->name ?? $user?->name ?? 'Account')
        : ($user?->name ?? 'Account');

    // Always define to avoid "Undefined variable"
    $planLabel = (is_string($planKey) && $planKey !== '') ? ucfirst($planKey) : null;

    $pageTitle = match (true) {
        request()->routeIs('monitors.*') => 'Monitors',
        request()->routeIs('sla.*') || request()->routeIs('sla.overview') => 'SLA',
        request()->routeIs('billing.*') || request()->routeIs('billing.index') => 'Billing',
        request()->routeIs('team.notifications') => 'Team Notifications',
        request()->routeIs('public.status') || request()->routeIs('public.team-status') => 'Status',
        request()->routeIs('profile.show') => 'Settings',
        request()->routeIs('admin.*') => 'Admin',
        default => 'Dashboard',
    };

    $routeExists = fn (string $name) => \Illuminate\Support\Facades\Route::has($name);

    $isActive = fn (string $pattern) => request()->routeIs($pattern);

    $navItemClass = function (bool $active): string {
        return $active
            ? 'bg-slate-900 text-white shadow-sm'
            : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900';
    };

    $iconClass = function (bool $active): string {
        return $active ? 'text-white' : 'text-slate-500 group-hover:text-slate-700';
    };

    // Team settings: ONLY if Team plan
    $canSeeTeamSettings =
        $isTeamPlan
        && $user
        && $team
        && \Laravel\Jetstream\Jetstream::hasTeamFeatures()
        && $routeExists('teams.show');

    // Team notifications: ONLY if Team plan + owner/admin role
    $canSeeTeamNotifications = false;
    if ($isTeamPlan && $user && $team && $routeExists('team.notifications')) {
        $canSeeTeamNotifications =
            ($team->user_id === $user->id)
            || (method_exists($user, 'hasTeamRole') && $user->hasTeamRole($team, 'admin'));
    }

    // Admin nav
    $showAdmin = $user && $user->can('access-admin') && $routeExists('admin.index');

    // Build nav from your route names ONLY (no placeholders)
    $nav = [];
    
    if ($routeExists('dashboard')) {
    $nav[] = [
        'label' => 'Dashboard',
        'href' => route('dashboard'),
        'active' => $isActive('dashboard'),
        'icon' => 'pulse', // reuse your pulse icon
    ];
}

    if ($routeExists('monitors.index')) {
        $nav[] = [
            'label' => 'Monitors',
            'href' => route('monitors.index'),
            'active' => $isActive('dashboard') || $isActive('monitors.*'),
            'icon' => 'monitor',
        ];
    }

    if ($routeExists('sla.overview')) {
        $nav[] = [
            'label' => 'SLA',
            'href' => route('sla.overview'),
            'active' => $isActive('sla.*') || $isActive('sla.overview'),
            'icon' => 'doc',
        ];
    }

    if ($routeExists('billing.index')) {
        $nav[] = [
            'label' => 'Billing',
            'href' => route('billing.index'),
            'active' => $isActive('billing.*') || $isActive('billing.index'),
            'icon' => 'billing',
        ];
    }

    // Public status: you have Volt::route('/status', ...)->name('public.status')
    /*if ($routeExists('public.status')) {
        $nav[] = [
            'label' => 'Status',
            'href' => route('public.status'),
            'active' => $isActive('public.status') || $isActive('public.team-status'),
            'icon' => 'pulse',
            'external' => true,
        ];
    }*/

    if ($canSeeTeamNotifications) {
        $nav[] = [
            'label' => 'Team Notifications',
            'href' => route('team.notifications', $team),
            'active' => $isActive('team.notifications'),
            'icon' => 'bell',
        ];
    }

  /*  if ($routeExists('profile.show')) {
        $nav[] = [
            'label' => 'Settings',
            'href' => route('profile.show'),
            'active' => $isActive('profile.show'),
            'icon' => 'user',
        ];
    }*/
@endphp

{{-- Sidebar is OPEN by default on desktop, closed on mobile --}}
<div x-data="{ sidebarOpen: false }" :class="sidebarOpen ? 'h-screen overflow-hidden md:overflow-visible md:h-auto' : ''" class="min-h-screen md:flex">

    {{-- Mobile overlay + drawer --}}
    <div x-cloak x-show="sidebarOpen" class="fixed inset-0 z-50 md:hidden">
        <div class="absolute inset-0 bg-slate-900/50" @click="sidebarOpen=false"></div>

        <aside
            class="absolute inset-y-0 left-0 w-72 bg-white border-r border-slate-200 shadow-xl"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
        >
            <div class="h-full flex flex-col">
                <div class="h-16 flex items-center gap-3 px-4 border-b border-slate-200">
                    <a href="{{ $routeExists('monitors.index') ? route('monitors.index') : route('dashboard') }}" class="inline-flex items-center gap-2">
                        <x-application-mark class="h-9 w-9" />
                        <div class="leading-tight min-w-0">
                            <div class="text-sm font-semibold text-slate-900">Monitly</div>
                            <div class="text-xs text-slate-500 truncate max-w-[180px]">{{ $workspaceName }}</div>
                        </div>
                    </a>

                    <button
                        type="button"
                        class="ml-auto inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        @click="sidebarOpen=false"
                    >
                        Close
                    </button>
                </div>

                <nav class="p-4 space-y-1 flex-1 overflow-y-auto">
                    <div class="px-3 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider">Workspace</div>

                    @foreach ($nav as $item)
                        @php $active = (bool) $item['active']; @endphp

                        <a href="{{ $item['href'] }}"
                           class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ $navItemClass($active) }}"
                           @if(!empty($item['external'])) target="_blank" rel="noopener noreferrer" @endif
                        >
                            @if ($item['icon'] === 'monitor')
                                <svg class="h-5 w-5 {{ $iconClass($active) }}" viewBox="0 0 24 24" fill="none">
                                    <path d="M6 18.5c1.5-2 3.6-3 6-3s4.5 1 6 3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M9 15c.8-1 1.9-1.5 3-1.5S14.2 14 15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M12 12h.01" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                                </svg>
                            @elseif ($item['icon'] === 'doc')
                                <svg class="h-5 w-5 {{ $iconClass($active) }}" viewBox="0 0 24 24" fill="none">
                                    <path d="M7 3h7l3 3v15a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2"/>
                                    <path d="M14 3v4a2 2 0 0 0 2 2h4" stroke="currentColor" stroke-width="2"/>
                                </svg>
                            @elseif ($item['icon'] === 'billing')
                                <svg class="h-5 w-5 {{ $iconClass($active) }}" viewBox="0 0 24 24" fill="none">
                                    <path d="M21 8H3" stroke="currentColor" stroke-width="2"/>
                                    <path d="M21 12H3" stroke="currentColor" stroke-width="2"/>
                                    <path d="M21 16H3" stroke="currentColor" stroke-width="2"/>
                                    <path d="M7 8v12" stroke="currentColor" stroke-width="2"/>
                                </svg>
                            @elseif ($item['icon'] === 'pulse')
                                <svg class="h-5 w-5 {{ $iconClass($active) }}" viewBox="0 0 24 24" fill="none">
                                    <path d="M3 12h3l3-9 6 18 3-9h3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            @elseif ($item['icon'] === 'bell')
                                <svg class="h-5 w-5 {{ $iconClass($active) }}" viewBox="0 0 24 24" fill="none">
                                    <path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 7h18s-3 0-3-7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M13.73 21a2 2 0 0 1-3.46 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            @else
                                <svg class="h-5 w-5 {{ $iconClass($active) }}" viewBox="0 0 24 24" fill="none">
                                    <path d="M20 21a8 8 0 0 0-16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z" stroke="currentColor" stroke-width="2"/>
                                </svg>
                            @endif

                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    @endforeach

                    @if ($showAdmin)
                        <div class="pt-4 mt-3 border-t border-slate-200"></div>
                        <div class="px-3 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider">Admin</div>

                        <a href="{{ route('admin.index') }}"
                           class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ $navItemClass($isActive('admin.*')) }}">
                            <svg class="h-5 w-5 {{ $iconClass($isActive('admin.*')) }}" viewBox="0 0 24 24" fill="none">
                                <path d="M12 2v2M12 20v2M4 12H2m20 0h-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            <span>Admin Dashboard</span>
                        </a>
                    @endif
                </nav>

                <div class="p-4 border-t border-slate-200">
                    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4">
                        <div class="text-xs text-slate-500">Workspace</div>
                        <div class="mt-1 text-sm font-semibold text-slate-900 truncate">{{ $workspaceName }}</div>
                        @if ($planLabel)
                            <div class="mt-2 text-xs text-slate-500">Plan: {{ $planLabel }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </aside>
    </div>

    {{-- Desktop sidebar --}}
    <aside class="hidden md:flex md:w-72 md:flex-col md:border-r md:border-slate-200 md:bg-white">
        <div class="h-16 flex items-center gap-3 px-4 border-b border-slate-200">
            <a href="{{ $routeExists('monitors.index') ? route('monitors.index') : route('dashboard') }}" class="inline-flex items-center gap-2">
                <x-application-mark class="h-9 w-9" />
                <div class="leading-tight min-w-0">
                    <div class="text-sm font-semibold text-slate-900">Monitly</div>
                    <div class="text-xs text-slate-500 truncate max-w-[180px]">{{ $workspaceName }}</div>
                </div>
            </a>
        </div>

        <nav class="p-4 space-y-1 flex-1 overflow-y-auto">
            <div class="px-3 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider">Workspace</div>

            @foreach ($nav as $item)
                @php $active = (bool) $item['active']; @endphp

                <a href="{{ $item['href'] }}"
                   class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ $navItemClass($active) }}"
                   @if(!empty($item['external'])) target="_blank" rel="noopener noreferrer" @endif
                >
                    @if ($item['icon'] === 'monitor')
                        <svg class="h-5 w-5 {{ $iconClass($active) }}" viewBox="0 0 24 24" fill="none">
                            <path d="M6 18.5c1.5-2 3.6-3 6-3s4.5 1 6 3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M9 15c.8-1 1.9-1.5 3-1.5S14.2 14 15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M12 12h.01" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                        </svg>
                    @elseif ($item['icon'] === 'doc')
                        <svg class="h-5 w-5 {{ $iconClass($active) }}" viewBox="0 0 24 24" fill="none">
                            <path d="M7 3h7l3 3v15a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2"/>
                            <path d="M14 3v4a2 2 0 0 0 2 2h4" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    @elseif ($item['icon'] === 'billing')
                        <svg class="h-5 w-5 {{ $iconClass($active) }}" viewBox="0 0 24 24" fill="none">
                            <path d="M21 8H3" stroke="currentColor" stroke-width="2"/>
                            <path d="M21 12H3" stroke="currentColor" stroke-width="2"/>
                            <path d="M21 16H3" stroke="currentColor" stroke-width="2"/>
                            <path d="M7 8v12" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    @elseif ($item['icon'] === 'pulse')
                        <svg class="h-5 w-5 {{ $iconClass($active) }}" viewBox="0 0 24 24" fill="none">
                            <path d="M3 12h3l3-9 6 18 3-9h3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    @elseif ($item['icon'] === 'bell')
                        <svg class="h-5 w-5 {{ $iconClass($active) }}" viewBox="0 0 24 24" fill="none">
                            <path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 7h18s-3 0-3-7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    @else
                        <svg class="h-5 w-5 {{ $iconClass($active) }}" viewBox="0 0 24 24" fill="none">
                            <path d="M20 21a8 8 0 0 0-16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    @endif

                    <span class="truncate">{{ $item['label'] }}</span>
                </a>
            @endforeach

            @if ($showAdmin)
                <div class="pt-4 mt-3 border-t border-slate-200"></div>
                <div class="px-3 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider">Admin</div>

                <a href="{{ route('admin.index') }}"
                   class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ $navItemClass($isActive('admin.*')) }}">
                    <svg class="h-5 w-5 {{ $iconClass($isActive('admin.*')) }}" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2v2M12 20v2M4 12H2m20 0h-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <span>Admin Dashboard</span>
                </a>
            @endif
        </nav>

        <div class="p-4 border-t border-slate-200">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4">
                <div class="text-xs text-slate-500">Workspace</div>
                <div class="mt-1 text-sm font-semibold text-slate-900 truncate">{{ $workspaceName }}</div>
                @if ($planLabel)
                    <div class="mt-2 text-xs text-slate-500">Plan: {{ $planLabel }}</div>
                @endif
            </div>
        </div>
    </aside>

    {{-- Main --}}
    <div class="flex-1 min-w-0">
        <header class="sticky top-0 z-30 bg-white/80 backdrop-blur border-b border-slate-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center gap-3 min-w-0">
                    <button
                        type="button"
                        class="md:hidden inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        @click="sidebarOpen = true"
                    >
                        <span class="sr-only">Open sidebar</span>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none">
                            <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>

                    <div class="min-w-0">
                        <div class="text-xl font-semibold text-slate-900 truncate">{{ $pageTitle }}</div>
                        <div class="text-sm text-slate-600 hidden sm:block">Uptime monitoring + SLA reporting.</div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    @if ($user)
                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                    @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                                        <img class="h-7 w-7 rounded-full object-cover" src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}" />
                                    @else
                                        <svg class="h-6 w-6 text-slate-700" viewBox="0 0 24 24" fill="none">
                                            <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z" stroke="currentColor" stroke-width="2"/>
                                            <path d="M20 21a8 8 0 0 0-16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                    @endif
                                    <span class="hidden sm:inline">{{ $user->name }}</span>
                                    <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none">
                                        <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <div class="px-4 py-3">
                                    <div class="text-sm font-semibold text-slate-900">{{ $user->name }}</div>
                                    <div class="text-sm text-slate-600 truncate">{{ $user->email }}</div>
                                </div>

                                <div class="border-t border-slate-200"></div>

                                @if ($routeExists('profile.show'))
                                    <x-dropdown-link href="{{ route('profile.show') }}">
                                        {{ __('Profile') }}
                                    </x-dropdown-link>
                                @endif

                                {{-- Team Settings: ONLY if Team plan --}}
                                @if ($canSeeTeamSettings)
                                    <x-dropdown-link href="{{ route('teams.show', $team->id) }}">
                                        {{ __('Team Settings') }}
                                    </x-dropdown-link>
                                @endif

                                @if ($user->can('access-admin') && $routeExists('admin.index'))
                                    <x-dropdown-link href="{{ route('admin.index') }}">
                                        {{ __('Admin') }}
                                    </x-dropdown-link>
                                @endif

                                <div class="border-t border-slate-200"></div>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <x-dropdown-link href="{{ route('logout') }}"
                                                     onclick="event.preventDefault(); this.closest('form').submit();">
                                        {{ __('Log Out') }}
                                    </x-dropdown-link>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    @endif
                </div>
            </div>
        </header>

        <main class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {{ $slot }}
            </div>
        </main>
    </div>
</div>

@stack('modals')
@livewireScripts
</body>
</html>