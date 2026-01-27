@props([
    'padding' => true,
])

<div {{ $attributes->merge(['class' => 'rounded-lg bg-[#1a1a1a] border border-white/[0.08] shadow-sm']) }}>
    @if(isset($header))
        <div class="border-b border-white/[0.08] px-6 py-4">
            {{ $header }}
        </div>
    @endif

    <div class="{{ $padding ? 'px-6 py-4' : '' }}">
        {{ $slot }}
    </div>

    @if(isset($footer))
        <div class="border-t border-white/[0.08] px-6 py-4 bg-white/[0.02]">
            {{ $footer }}
        </div>
    @endif
</div>
