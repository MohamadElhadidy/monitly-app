@props([
    'icon' => 'default',
    'title' => 'No data',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'text-center py-12 px-6']) }}>
    @if($icon === 'monitor')
        <svg class="mx-auto h-12 w-12 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
        </svg>
    @elseif($icon === 'success')
        <svg class="mx-auto h-12 w-12 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    @elseif($icon === 'document')
        <svg class="mx-auto h-12 w-12 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
        </svg>
    @elseif($icon === 'server')
        <svg class="mx-auto h-12 w-12 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
            <g transform="translate(2, 2)">
                <path d="M2.5 5h15a1 1 0 0 0 1-1V2.5a1 1 0 0 0-1-1h-15a1 1 0 0 0-1 1V4a1 1 0 0 0 1 1z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/>
                <path d="M2.5 11h15a1 1 0 0 0 1-1V8.5a1 1 0 0 0-1-1h-15a1 1 0 0 0-1 1V10a1 1 0 0 0 1 1z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/>
                <path d="M2.5 17h15a1 1 0 0 0 1-1v-1.5a1 1 0 0 0-1-1h-15a1 1 0 0 0-1 1V16a1 1 0 0 0 1 1z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/>
                <circle cx="5.5" cy="3.5" r="0.5" fill="currentColor"/>
                <circle cx="5.5" cy="9.5" r="0.5" fill="currentColor"/>
                <circle cx="5.5" cy="15.5" r="0.5" fill="currentColor"/>
            </g>
        </svg>
    @else
        <svg class="mx-auto h-12 w-12 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
        </svg>
    @endif

    <h3 class="mt-4 text-sm font-semibold text-white">{{ $title }}</h3>
    
    @if($description)
        <p class="mt-2 text-sm text-gray-400">{{ $description }}</p>
    @endif

    @if(isset($action))
        <div class="mt-6">
            {{ $action }}
        </div>
    @endif
</div>
