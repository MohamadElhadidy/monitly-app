@props([
    'variant' => 'info',
    'dismissible' => false,
])

@php
$baseClasses = 'rounded-lg px-4 py-3';

$variantClasses = match($variant) {
    'success' => 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-300',
    'danger' => 'bg-red-500/10 border border-red-500/20 text-red-300',
    'warning' => 'bg-yellow-500/10 border border-yellow-500/20 text-yellow-300',
    'info' => 'bg-blue-500/10 border border-blue-500/20 text-blue-300',
    default => 'bg-white/[0.06] border border-white/[0.08] text-gray-300',
};

$iconClasses = match($variant) {
    'success' => 'text-emerald-400',
    'danger' => 'text-red-400',
    'warning' => 'text-yellow-400',
    'info' => 'text-blue-400',
    default => 'text-gray-400',
};

$classes = $baseClasses . ' ' . $variantClasses;
@endphp

<div {{ $attributes->merge(['class' => $classes]) }} x-data="{ show: true }" x-show="show">
    <div class="flex">
        <div class="flex-shrink-0">
            @if($variant === 'success')
                <svg class="h-5 w-5 {{ $iconClasses }}" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                </svg>
            @elseif($variant === 'danger')
                <svg class="h-5 w-5 {{ $iconClasses }}" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                </svg>
            @elseif($variant === 'warning')
                <svg class="h-5 w-5 {{ $iconClasses }}" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                </svg>
            @else
                <svg class="h-5 w-5 {{ $iconClasses }}" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                </svg>
            @endif
        </div>
        <div class="ml-3 flex-1">
            <div class="text-sm font-medium">
                {{ $slot }}
            </div>
        </div>
        @if($dismissible)
            <div class="ml-auto pl-3">
                <div class="-mx-1.5 -my-1.5">
                    <button @click="show = false" type="button" class="inline-flex rounded-md p-1.5 hover:bg-white/[0.06] focus:outline-none focus:ring-2 focus:ring-offset-2">
                        <span class="sr-only">Dismiss</span>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                        </svg>
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
