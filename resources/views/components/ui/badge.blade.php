@props(['variant' => 'neutral'])

@php
    $base = 'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset';
    $v = strtolower((string) $variant);

    $classes = match ($v) {
        'up' => "{$base} bg-emerald-50 text-emerald-700 ring-emerald-200",
        'down' => "{$base} bg-rose-50 text-rose-700 ring-rose-200",
        'degraded' => "{$base} bg-amber-50 text-amber-700 ring-amber-200",
        'unknown' => "{$base} bg-slate-50 text-slate-700 ring-slate-200",
        default => "{$base} bg-slate-50 text-slate-700 ring-slate-200",
    };
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
