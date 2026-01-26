<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Status' }} • Monitly</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full">
    <div class="min-h-full">
        <header class="border-b border-slate-200 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="h-9 w-9 rounded-xl bg-slate-900 text-white flex items-center justify-center font-semibold">
                        M
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-slate-900 leading-tight">Monitly</div>
                        <div class="text-xs text-slate-600 leading-tight">Public Status</div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    @auth
                        <a href="{{ route('monitors.index') }}"
                           class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Open app
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                           class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Login
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <main>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {{ $slot }}
            </div>
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="text-xs text-slate-500">
                    © {{ date('Y') }} Monitly. Status data may be cached briefly for performance.
                </div>
            </div>
        </footer>
    </div>

    @livewireScripts
</body>
</html>