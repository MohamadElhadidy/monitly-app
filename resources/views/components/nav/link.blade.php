@props([
    'href' => '#',
    'active' => false,
    'icon' => null,
])

@php
    $base = 'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition';
    $activeClass = 'bg-slate-900 text-white shadow-sm';
    $inactiveClass = 'text-slate-700 hover:bg-slate-100 hover:text-slate-900';
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => $base.' '.($active ? $activeClass : $inactiveClass)]) }}>
    @if ($icon)
        <span class="{{ $active ? 'text-white' : 'text-slate-500 group-hover:text-slate-700' }}">
            {!! $icon !!}
        </span>
    @endif

    <span class="truncate">{{ $slot }}</span>
</a>