@props([
    'compact' => false,
])

@php
    $pad = $compact ? 'px-4 py-2' : 'px-4 py-3';
@endphp

<div {{ $attributes->merge(['class' => 'overflow-x-auto rounded-xl border border-slate-200']) }}>
    <table class="min-w-full divide-y divide-slate-200 bg-white">
        {{ $slot }}
    </table>
</div>
