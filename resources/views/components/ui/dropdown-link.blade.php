@props([
    'href' => '#',
])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'block px-4 py-2 text-sm leading-5 text-gray-300 hover:bg-white/[0.06] hover:text-white focus:outline-none focus:bg-white/[0.06] transition duration-150 ease-in-out']) }}>
    {{ $slot }}
</a>
