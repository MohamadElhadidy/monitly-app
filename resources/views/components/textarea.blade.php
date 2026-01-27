@props([
    'disabled' => false,
    'error' => false,
    'rows' => 3,
])

@php
$classes = 'block w-full rounded-lg border-0 bg-white/[0.06] px-3.5 py-2 text-sm text-white placeholder:text-gray-500 focus:ring-2 focus:ring-inset focus:ring-emerald-500 transition-colors resize-none';

if ($error) {
    $classes .= ' ring-2 ring-inset ring-red-500';
}

if ($disabled) {
    $classes .= ' opacity-50 cursor-not-allowed';
}
@endphp

<textarea 
    rows="{{ $rows }}"
    {{ $disabled ? 'disabled' : '' }} 
    {{ $attributes->merge(['class' => $classes]) }}
>{{ $slot }}</textarea>
