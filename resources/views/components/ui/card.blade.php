@props([
    'title' => null,
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white shadow-sm']) }}>
    @if ($title || $description || isset($header) || isset($actions))
        <div class="p-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                @if ($title)
                    <div class="text-sm font-semibold text-slate-900">{{ $title }}</div>
                @endif
                @if ($description)
                    <div class="mt-1 text-sm text-slate-600">{{ $description }}</div>
                @endif

                @isset($header)
                    <div class="mt-2">{{ $header }}</div>
                @endisset
            </div>

            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">
                    {{ $actions }}
                </div>
            @endisset
        </div>
        <div class="border-t border-slate-200"></div>
        <div class="p-6">
            {{ $slot }}
        </div>
    @else
        <div class="p-6">
            {{ $slot }}
        </div>
    @endif
</div>
