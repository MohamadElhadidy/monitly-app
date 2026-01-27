@props(['headers' => []])

<div class="overflow-hidden rounded-lg border border-gray-200 shadow-sm">
    <table {{ $attributes->merge(['class' => 'min-w-full divide-y divide-gray-200']) }}>
        @if(count($headers) > 0)
        <thead class="bg-gray-50">
            <tr>
                @foreach($headers as $header)
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    {{ $header }}
                </th>
                @endforeach
            </tr>
        </thead>
        @endif
        <tbody class="divide-y divide-gray-200 bg-white">
            {{ $slot }}
        </tbody>
    </table>
</div>