@props(['label' => null, 'error' => null, 'helper' => null, 'icon' => null])

@php
$errorClasses = $error 
    ? 'border-red-300 text-red-900 placeholder-red-300 focus:border-red-500 focus:ring-red-500' 
    : 'border-gray-300 focus:border-emerald-500 focus:ring-emerald-500';
    
$classes = 'block w-full rounded-lg border shadow-sm sm:text-sm ' . $errorClasses;
if ($icon) {
    $classes .= ' pl-10';
}
@endphp

<div>
    @if($label)
    <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $label }}
    </label>
    @endif
    
    <div class="relative">
        @if($icon)
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                {!! $icon !!}
            </svg>
        </div>
        @endif
        
        <input {{ $attributes->merge(['class' => $classes]) }} />
    </div>
    
    @if($error)
    <p class="mt-1 text-sm text-red-600">{{ $error }}</p>
    @elseif($helper)
    <p class="mt-1 text-sm text-gray-500">{{ $helper }}</p>
    @endif
</div>