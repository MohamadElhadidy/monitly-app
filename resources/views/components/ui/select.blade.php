@props(['label' => null, 'error' => null, 'helper' => null, 'options' => []])

@php
$errorClasses = $error 
    ? 'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500' 
    : 'border-gray-300 focus:border-emerald-500 focus:ring-emerald-500';
    
$classes = 'block w-full rounded-lg border shadow-sm sm:text-sm ' . $errorClasses;
@endphp

<div>
    @if($label)
    <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $label }}
    </label>
    @endif
    
    <select {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
        @foreach($options as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>
    
    @if($error)
    <p class="mt-1 text-sm text-red-600">{{ $error }}</p>
    @elseif($helper)
    <p class="mt-1 text-sm text-gray-500">{{ $helper }}</p>
    @endif
</div>