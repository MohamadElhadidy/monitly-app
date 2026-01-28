@props([
    'checkout' => null,
    'url' => null,
])

@php
    $checkoutUrl = $checkout['url'] ?? $url ?? '#';
@endphp

<a 
    href="{{ $checkoutUrl }}" 
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center']) }}
    @if($checkoutUrl === '#') onclick="event.preventDefault(); alert('Paddle checkout not configured. Please set PADDLE_PRICE_IDS_PRO and PADDLE_PRICE_IDS_TEAM in your .env file.');" @endif
>
    {{ $slot }}
</a>
