@props(['variant' => 'default', 'size' => 'md'])

@php
$variants = [
    'default' => 'bg-gray-100 text-gray-700 border-gray-200',
    'success' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
    'danger' => 'bg-red-100 text-red-700 border-red-200',
    'warning' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
    'info' => 'bg-blue-100 text-blue-700 border-blue-200',
    'secondary' => 'bg-gray-100 text-gray-700 border-gray-200',
];

$sizes = [
    'sm' => 'px-2 py-0.5 text-xs',
    'md' => 'px-2.5 py-0.5 text-sm',
    'lg' => 'px-3 py-1 text-base',
];

$classes = 'inline-flex items-center rounded-md font-medium border ' . $variants[$variant] . ' ' . $sizes[$size];
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>