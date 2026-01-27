@props([
    'disabled' => false,
    'type' => 'text',
    'error' => false,
])

@php
$classes = 'block w-full rounded-lg border-0 bg-white/[0.06] px-3.5 py-2 text-sm text-white placeholder:text-gray-500 focus:ring-2 focus:ring-inset focus:ring-emerald-500 transition-colors';

if ($error) {
    $classes .= ' ring-2 ring-inset ring-red-500';
}

if ($disabled) {
    $classes .= ' opacity-50 cursor-not-allowed';
}
@endphp

<input 
    type="{{ $type }}"
    {{ $disabled ? 'disabled' : '' }} 
    {{ $attributes->merge(['class' => $classes]) }}
>
