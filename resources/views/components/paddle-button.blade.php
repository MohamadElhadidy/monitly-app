@props([
    'checkout' => null,
    'url' => null,
])

@php
    $checkoutUrl = $url ?? '#';

    if ($checkout) {
        if (is_array($checkout)) {
            $checkoutUrl = $checkout['url'] ?? $checkoutUrl;
        } elseif (is_object($checkout) && method_exists($checkout, 'url')) {
            $checkoutUrl = $checkout->url() ?? $checkoutUrl;
        }
    }
@endphp

<a 
    href="{{ $checkoutUrl }}" 
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center']) }}
    @if($checkoutUrl === '#') onclick="event.preventDefault(); alert('Paddle checkout not configured. Please set PADDLE_PRICE_IDS_PRO and PADDLE_PRICE_IDS_TEAM in your .env file.');" @endif
>
    {{ $slot }}
</a>
