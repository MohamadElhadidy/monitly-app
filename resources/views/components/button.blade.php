@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'loading' => false,
])

@php
$baseClasses = 'inline-flex items-center justify-center font-semibold rounded-lg transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50 disabled:pointer-events-none';

$variantClasses = match($variant) {
    'primary' => 'bg-emerald-500 text-white hover:bg-emerald-600 focus-visible:outline-emerald-500',
    'secondary' => 'bg-white/[0.06] text-white hover:bg-white/[0.12] border border-white/[0.08]',
    'danger' => 'bg-red-500 text-white hover:bg-red-600 focus-visible:outline-red-500',
    'ghost' => 'bg-transparent text-gray-400 hover:bg-white/[0.06] hover:text-white',
    'outline' => 'border border-white/[0.08] bg-transparent text-gray-300 hover:bg-white/[0.06] hover:text-white',
    default => 'bg-emerald-500 text-white hover:bg-emerald-600',
};

$sizeClasses = match($size) {
    'xs' => 'text-xs px-2 py-1',
    'sm' => 'text-sm px-3 py-1.5',
    'md' => 'text-sm px-4 py-2',
    'lg' => 'text-base px-6 py-3',
    default => 'text-sm px-4 py-2',
};

$classes = $baseClasses . ' ' . $variantClasses . ' ' . $sizeClasses;
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($loading)
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($loading)
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        @endif
        {{ $slot }}
    </button>
@endif
