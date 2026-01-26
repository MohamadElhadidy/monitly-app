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
<body class="bg-gray-50 antialiased">
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
        
        $planColors = [
            'free' => ['bg' => 'bg-slate-100', 'text' => 'text-slate-700', 'border' => 'border-slate-300'],
            'pro' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'border' => 'border-blue-300'],
            'team' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-700', 'border' => 'border-purple-300'],
        ];
        $planColor = $planColors[$plan] ?? $planColors['free'];
    @endphp

    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-72 bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900 text-white hidden lg:flex flex-col border-r border-slate-700/50 shadow-2xl">
            <!-- Logo Section -->
            <div class="p-6 border-b border-slate-700/50 bg-slate-800/50 backdrop-blur-sm">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 group">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center font-bold text-xl shadow-lg group-hover:scale-105 transition-transform duration-200">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="font-bold text-lg text-white tracking-tight">Monitly</div>
                        <div class="text-xs text-slate-400 font-medium">Monitoring Platform</div>
                    </div>
                </a>
            </div>

            <!-- Plan Badge -->
            <div class="mx-4 mt-4 p-4 bg-gradient-to-br from-slate-800/80 to-slate-700/80 rounded-xl border border-slate-600/50 backdrop-blur-sm shadow-lg">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs font-semibold text-slate-300 uppercase tracking-wider">Current Plan</div>
                    @if($plan !== 'team')
                        <a href="{{ route('billing.index') }}" class="text-xs text-blue-400 hover:text-blue-300 font-medium transition-colors">
                            Upgrade →
                        </a>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-3 py-1.5 rounded-lg font-bold text-sm capitalize {{ $planColor['bg'] }} {{ $planColor['text'] }} border {{ $planColor['border'] }}">
                        {{ $plan }}
                    </span>
                    @if($plan === 'free')
                        <span class="text-xs text-slate-400">Limited</span>
                    @endif
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto scrollbar-thin scrollbar-thumb-slate-600 scrollbar-track-transparent">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 {{ $isActive('dashboard') ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    <span class="font-medium">Dashboard</span>
                    @if($isActive('dashboard'))
                        <div class="ml-auto w-2 h-2 bg-white rounded-full"></div>
                    @endif
                </a>

                <a href="{{ route('monitors.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 {{ $isActive('monitors.*') ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <span class="font-medium">Monitors</span>
                    @if($isActive('monitors.*'))
                        <div class="ml-auto w-2 h-2 bg-white rounded-full"></div>
                    @endif
                </a>

                <a href="{{ route('sla.overview') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 {{ $isActive('sla.*') ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <span class="font-medium">Reports</span>
                    @if($isActive('sla.*'))
                        <div class="ml-auto w-2 h-2 bg-white rounded-full"></div>
                    @endif
                </a>

                @if($plan === 'team')
                <div class="pt-4 mt-4 border-t border-slate-700/50">
                    <div class="px-4 py-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Team</div>
                    
                    <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 hover:bg-slate-700/50 hover:text-white transition-all duration-200">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <span class="font-medium">Team Members</span>
                    </a>

                    <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 hover:bg-slate-700/50 hover:text-white transition-all duration-200">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span class="font-medium">Integrations</span>
                    </a>
                </div>
                @endif

                <div class="pt-4 mt-4 border-t border-slate-700/50">
                    <a href="{{ route('billing.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 {{ $isActive('billing.*') ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                        <span class="font-medium">Billing</span>
                        @if($isActive('billing.*'))
                            <div class="ml-auto w-2 h-2 bg-white rounded-full"></div>
                        @endif
                    </a>
                </div>

                <div class="pt-4 mt-4 border-t border-slate-700/50">
                    <a href="{{ route('profile.show') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 {{ $isActive('profile.show') ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span class="font-medium">Settings</span>
                        @if($isActive('profile.show'))
                            <div class="ml-auto w-2 h-2 bg-white rounded-full"></div>
                        @endif
                    </a>

                    @if ($user->is_admin)
                    <a href="{{ route('admin.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 {{ $isActive('admin.*') ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                        </svg>
                        <span class="font-medium">Admin</span>
                        @if($isActive('admin.*'))
                            <div class="ml-auto w-2 h-2 bg-white rounded-full"></div>
                        @endif
                    </a>
                    @endif
                </div>
            </nav>

            <!-- User Section -->
            <div class="border-t border-slate-700/50 p-4 bg-slate-800/30 backdrop-blur-sm">
                <div class="flex items-center gap-3 p-2 rounded-xl hover:bg-slate-700/30 transition-colors">
                    <img src="{{ $user->profile_photo_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&background=3b82f6&color=fff' }}" 
                         alt="{{ $user->name }}" 
                         class="w-10 h-10 rounded-xl ring-2 ring-slate-600 shadow-lg">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold text-white truncate">{{ $user->name }}</div>
                        <div class="text-xs text-slate-400 truncate">{{ $user->email }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-2.5 text-sm font-medium text-slate-300 hover:bg-slate-700/50 hover:text-white rounded-xl transition-all duration-200 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden bg-gray-50">
            <!-- Header -->
            <header class="bg-white border-b border-gray-200/80 backdrop-blur-sm shadow-sm sticky top-0 z-40">
                <div class="h-16 px-6 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $pageTitle }}</h1>
                        @if(request()->routeIs('monitors.show'))
                            <span class="text-sm text-gray-500">/</span>
                            <span class="text-sm text-gray-600 font-medium">Monitor Details</span>
                        @endif
                    </div>
                    
                    <div class="flex items-center gap-3">
                        <!-- Quick Actions -->
                        @if(request()->routeIs('monitors.index'))
                            <a href="{{ route('monitors.index') }}" class="hidden sm:flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg font-medium text-sm hover:from-blue-700 hover:to-indigo-700 transition-all shadow-md hover:shadow-lg">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                New Monitor
                            </a>
                        @endif

                        <!-- Plan Badge -->
                        <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg {{ $planColor['bg'] }} {{ $planColor['text'] }} border {{ $planColor['border'] }}">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-sm font-semibold capitalize">{{ $plan }}</span>
                        </div>

                        <!-- Timezone Selector -->
                        <div class="relative">
                            <select 
                                onchange="updateTimezone(this.value)"
                                class="appearance-none bg-white border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @php
                                    $timezones = [
                                        'UTC' => 'UTC',
                                        'America/New_York' => 'ET',
                                        'America/Chicago' => 'CT',
                                        'America/Denver' => 'MT',
                                        'America/Los_Angeles' => 'PT',
                                        'Europe/London' => 'GMT',
                                        'Europe/Paris' => 'CET',
                                        'Europe/Berlin' => 'CET',
                                        'Asia/Tokyo' => 'JST',
                                        'Asia/Shanghai' => 'CST',
                                        'Asia/Dubai' => 'GST',
                                        'Asia/Kolkata' => 'IST',
                                        'Australia/Sydney' => 'AEDT',
                                        'America/Sao_Paulo' => 'BRT',
                                    ];
                                    $currentTimezone = $user->timezone ?? 'UTC';
                                @endphp
                                @foreach ($timezones as $tz => $label)
                                    <option value="{{ $tz }}" {{ $currentTimezone === $tz ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Notifications (placeholder) -->
                        <button class="relative p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main class="flex-1 overflow-y-auto">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    @livewireScripts
    <script>
        function updateTimezone(timezone) {
            fetch('{{ route('timezone.update') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ timezone: timezone })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload the page to apply the new timezone
                    window.location.reload();
                } else {
                    console.error('Failed to update timezone');
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html>
