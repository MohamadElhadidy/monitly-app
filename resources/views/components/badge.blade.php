@props([
    'variant' => 'default',
    'size' => 'md',
    'dot' => false,
])

@php
$baseClasses = 'inline-flex items-center font-medium rounded-full';

$variantClasses = match($variant) {
    'success' => 'bg-emerald-400/10 text-emerald-400 ring-1 ring-inset ring-emerald-400/20',
    'danger' => 'bg-red-400/10 text-red-400 ring-1 ring-inset ring-red-400/20',
    'warning' => 'bg-yellow-400/10 text-yellow-400 ring-1 ring-inset ring-yellow-400/20',
    'info' => 'bg-blue-400/10 text-blue-400 ring-1 ring-inset ring-blue-400/20',
    'default' => 'bg-white/[0.06] text-gray-300 ring-1 ring-inset ring-white/[0.08]',
    default => 'bg-white/[0.06] text-gray-300 ring-1 ring-inset ring-white/[0.08]',
};

$sizeClasses = match($size) {
    'sm' => 'text-xs px-2 py-0.5',
    'md' => 'text-xs px-2.5 py-0.5',
    'lg' => 'text-sm px-3 py-1',
    default => 'text-xs px-2.5 py-0.5',
};

$dotColor = match($variant) {
    'success' => 'bg-emerald-400',
    'danger' => 'bg-red-400',
    'warning' => 'bg-yellow-400',
    'info' => 'bg-blue-400',
    default => 'bg-gray-400',
};

$classes = $baseClasses . ' ' . $variantClasses . ' ' . $sizeClasses;
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    @if($dot)
        <svg class="mr-1.5 h-1.5 w-1.5 {{ $dotColor }}" viewBox="0 0 6 6" aria-hidden="true">
            <circle cx="3" cy="3" r="3" fill="currentColor" />
        </svg>
    @endif
    {{ $slot }}
</span>
