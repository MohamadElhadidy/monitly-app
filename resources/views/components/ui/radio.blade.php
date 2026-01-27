@props([
    'checked' => false,
    'disabled' => false,
])

<input 
    type="radio" 
    {{ $checked ? 'checked' : '' }}
    {{ $disabled ? 'disabled' : '' }}
    {{ $attributes->merge(['class' => 'border-white/[0.12] bg-white/[0.06] text-emerald-500 focus:ring-emerald-500 focus:ring-offset-0 transition-colors']) }}
>
