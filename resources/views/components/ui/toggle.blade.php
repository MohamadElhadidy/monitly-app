@props(['label' => null, 'description' => null, 'checked' => false])

<div x-data="{ enabled: {{ $checked ? 'true' : 'false' }} }">
    <div class="flex items-center justify-between">
        <div class="flex-1">
            @if($label)
            <label class="block text-sm font-medium text-gray-900">{{ $label }}</label>
            @endif
            @if($description)
            <p class="text-sm text-gray-500">{{ $description }}</p>
            @endif
        </div>
        <button 
            type="button" 
            @click="enabled = !enabled"
            :class="enabled ? 'bg-emerald-600' : 'bg-gray-200'"
            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
        >
            <span 
                :class="enabled ? 'translate-x-5' : 'translate-x-0'"
                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
            ></span>
        </button>
        <input type="hidden" {{ $attributes }} x-model="enabled" />
    </div>
</div>