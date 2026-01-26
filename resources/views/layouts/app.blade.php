<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Monitly') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @paddleJS
</head>
<body class="bg-gray-50">
    <x-banner />

    @php
        $user = auth()->user();
        $plan = strtolower($user->billing_plan ?? 'free');
        
        $pageTitle = match (true) {
            request()->routeIs('monitors.*') => 'Monitors',
            request()->routeIs('sla.*') => 'Reports',
            request()->routeIs('billing.*') => 'Billing',
            request()->routeIs('profile.show') => 'Settings',
            request()->routeIs('admin.*') => 'Admin',
            default => 'Dashboard',
        };

        $isActive = fn(string $pattern) => request()->routeIs($pattern);
    @endphp

    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-gradient-to-b from-slate-900 to-slate-800 text-white hidden lg:flex flex-col border-r border-slate-700">
            <!-- Logo -->
            <div class="p-6 border-b border-slate-700">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center font-bold text-lg">M</div>
                    <div>
                        <div class="font-bold text-white">Monitly</div>
                        <div class="text-xs text-gray-400">Monitor</div>
                    </div>
                </a>
            </div>

            <!-- Plan Badge -->
            <div class="mx-4 mt-4 p-4 bg-slate-700 rounded-lg border border-slate-600">
                <div class="text-xs text-gray-300 uppercase mb-2">Current Plan</div>
                <div class="flex justify-between items-center">
                    <span class="font-bold capitalize">{{ $plan }}</span>
                    <a href="{{ route('billing.index') }}" class="text-xs text-blue-400 hover:text-blue-300">Upgrade</a>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-3 py-6 space-y-2 overflow-y-auto">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-4 py-2 rounded-lg {{ $isActive('dashboard') ? 'bg-blue-600 text-white' : 'hover:bg-slate-700' }}">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path></svg>
                    <span>Dashboard</span>
                </a>

                <a href="{{ route('monitors.index') }}" class="flex items-center gap-3 px-4 py-2 rounded-lg {{ $isActive('monitors.*') ? 'bg-blue-600 text-white' : 'hover:bg-slate-700' }}">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"></path></svg>
                    <span>Monitors</span>
                </a>

                <a href="{{ route('sla.overview') ?? '#' }}" class="flex items-center gap-3 px-4 py-2 rounded-lg {{ $isActive('sla.*') ? 'bg-blue-600 text-white' : 'hover:bg-slate-700' }}">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"></path></svg>
                    <span>Reports</span>
                </a>

                @if($plan === 'team')
                <div class="pt-4 mt-4 border-t border-slate-700">
                    <div class="px-4 py-2 text-xs font-bold text-gray-400 uppercase">Team</div>
                    
                    <a href="#" class="flex items-center gap-3 px-4 py-2 rounded-lg hover:bg-slate-700">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10.5 1.5H5.75A2.75 2.75 0 003 4.25v11.5A2.75 2.75 0 005.75 18.5h8.5A2.75 2.75 0 0017 15.75V4.25A2.75 2.75 0 0014.25 1.5h-3.75m0 3.5h2.5m-2.5 3h2.5m-7 0h2.5m-2.5 3h2.5m-2.5 3h6.5"></path></svg>
                        <span>Team Members</span>
                    </a>

                    <a href="#" class="flex items-center gap-3 px-4 py-2 rounded-lg hover:bg-slate-700">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M5.5 12a3.5 3.5 0 01-.369-6.98 4 4 0 117.753 1.977A4.5 4.5 0 1113.5 13H11V9.413l1.293 1.293a1 1 0 001.414-1.414l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13H5.5z"></path></svg>
                        <span>Integrations</span>
                    </a>
                </div>
                @endif

                <div class="pt-4 mt-4 border-t border-slate-700">
                    <a href="{{ route('billing.index') }}" class="flex items-center gap-3 px-4 py-2 rounded-lg {{ $isActive('billing.*') ? 'bg-blue-600 text-white' : 'hover:bg-slate-700' }}">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4z"></path></svg>
                        <span>Billing</span>
                    </a>
                </div>

                <div class="pt-4 mt-4 border-t border-slate-700">
                    <a href="{{ route('profile.show') }}" class="flex items-center gap-3 px-4 py-2 rounded-lg {{ $isActive('profile.show') ? 'bg-blue-600 text-white' : 'hover:bg-slate-700' }}">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"></path></svg>
                        <span>Settings</span>
                    </a>

                    @if ($user->is_admin)
                    <a href="{{ route('admin.index') }}" class="flex items-center gap-3 px-4 py-2 rounded-lg {{ $isActive('admin.*') ? 'bg-blue-600 text-white' : 'hover:bg-slate-700' }}">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.757a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM15.657 14.243a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM11 17a1 1 0 102 0v-1a1 1 0 10-2 0v1zM5.757 15.657a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414l-.707.707zM4 10a1 1 0 01-1-1V8a1 1 0 112 0v1a1 1 0 01-1 1zM5.757 4.343a1 1 0 00-1.414 1.414l.707.707a1 1 0 001.414-1.414L5.757 4.343zM10 4a6 6 0 100 12 6 6 0 000-12z"></path></svg>
                        <span>Admin</span>
                    </a>
                    @endif
                </div>
            </nav>

            <!-- User Section -->
            <div class="border-t border-slate-700 p-4">
                <div class="flex items-center gap-3 p-2">
                    <img src="{{ $user->profile_photo_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($user->name) }}" alt="{{ $user->name }}" class="w-10 h-10 rounded-full">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold truncate">{{ $user->name }}</div>
                        <div class="text-xs text-gray-400 truncate">{{ $user->email }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-300 hover:bg-slate-700 rounded-lg">
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-white border-b border-gray-200 h-16 px-6 flex items-center justify-between shadow-sm">
                <h1 class="text-2xl font-bold text-gray-900">{{ $pageTitle }}</h1>
                
                <div class="flex items-center gap-4">
                    <!-- Plan Badge -->
                    <span class="px-3 py-1 rounded-full text-sm font-semibold {{ 
                        match($plan) {
                            'pro' => 'bg-blue-100 text-blue-700',
                            'team' => 'bg-purple-100 text-purple-700',
                            default => 'bg-gray-100 text-gray-700'
                        }
                    }}">
                        {{ ucfirst($plan) }}
                    </span>

      
                </div>
            </header>

            <!-- Content -->
<main class="py-8 flex-1 overflow-y-auto ">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                                {{ $slot }}
                                </div>
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>