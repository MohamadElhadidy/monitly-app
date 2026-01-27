@props([
    'title',
    'description' => null,
])

<div class="rounded-xl border border-slate-200 bg-white p-6">
    <div class="flex flex-col items-center text-center">
        @isset($icon)
            <div class="h-12 w-12 rounded-xl bg-slate-100 flex items-center justify-center">
                {{ $icon }}
            </div>
        @endisset

        <div class="mt-4 text-sm font-semibold text-slate-900">{{ $title }}</div>

        @if ($description)
            <div class="mt-1 text-sm text-slate-600 max-w-md">{{ $description }}</div>
        @endif

        @isset($actions)
            <div class="mt-4 flex flex-wrap justify-center gap-2">
                {{ $actions }}
            </div>
        @endisset
    </div>
</div>
