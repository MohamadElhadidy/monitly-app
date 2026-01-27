@props(['label' => null, 'description' => null])

<div class="relative flex items-start">
    <div class="flex h-6 items-center">
        <input 
            type="checkbox" 
            {{ $attributes->merge(['class' => 'h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600']) }}
        />
    </div>
    @if($label || $description)
    <div class="ml-3 text-sm leading-6">
        @if($label)
        <label class="font-medium text-gray-900">{{ $label }}</label>
        @endif
        @if($description)
        <p class="text-gray-500">{{ $description }}</p>
        @endif
    </div>
    @endif
</div>