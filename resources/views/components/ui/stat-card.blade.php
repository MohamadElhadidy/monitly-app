@props(['title', 'value', 'icon', 'trend' => null, 'trendLabel' => null, 'color' => 'gray'])

@php
$colors = [
    'gray' => 'bg-gray-50 text-gray-600',
    'emerald' => 'bg-emerald-50 text-emerald-600',
    'blue' => 'bg-blue-50 text-blue-600',
    'red' => 'bg-red-50 text-red-600',
    'yellow' => 'bg-yellow-50 text-yellow-600',
];
@endphp

<div class="overflow-hidden rounded-lg bg-white border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
    <div class="p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="rounded-lg p-3 {{ $colors[$color] }}">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        {!! $icon !!}
                    </svg>
                </div>
            </div>
            <div class="ml-5 w-0 flex-1">
                <dl>
                    <dt class="text-sm font-medium text-gray-500 truncate">{{ $title }}</dt>
                    <dd class="flex items-baseline">
                        <div class="text-2xl font-semibold text-gray-900">{{ $value }}</div>
                        @if($trend)
                        <div class="ml-2 flex items-baseline text-sm font-semibold {{ $trend > 0 ? 'text-emerald-600' : 'text-red-600' }}">
                            <svg class="h-4 w-4 flex-shrink-0 self-center {{ $trend > 0 ? '' : 'rotate-180' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18" />
                            </svg>
                            <span class="ml-1">{{ abs($trend) }}%</span>
                        </div>
                        @endif
                    </dd>
                    @if($trendLabel)
                    <dd class="mt-1 text-sm text-gray-500">{{ $trendLabel }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>