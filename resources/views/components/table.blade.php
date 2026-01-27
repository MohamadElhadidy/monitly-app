@props([
    'headers' => [],
])

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg bg-[#1a1a1a] border border-white/[0.08] shadow-sm']) }}>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-white/[0.08]">
            @if(isset($head))
                <thead class="bg-white/[0.02]">
                    {{ $head }}
                </thead>
            @elseif(!empty($headers))
                <thead class="bg-white/[0.02]">
                    <tr>
                        @foreach($headers as $header)
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                {{ $header }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
            @endif

            <tbody class="divide-y divide-white/[0.08] bg-[#1a1a1a]">
                {{ $slot }}
            </tbody>

            @if(isset($footer))
                <tfoot class="bg-white/[0.02]">
                    {{ $footer }}
                </tfoot>
            @endif
        </table>
    </div>
</div>
