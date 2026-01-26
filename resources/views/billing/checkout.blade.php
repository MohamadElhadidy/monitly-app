<x-layouts.app>
<div class="max-w-lg mx-auto py-16 text-center space-y-6">

    <h1 class="text-2xl font-bold">Complete Payment</h1>

    <p class="text-gray-600">
        You are subscribing to the <strong>{{ ucfirst($plan) }}</strong> plan.
    </p>

    {{-- Paddle Button --}}
    <x-paddle-button
        :checkout="$checkout"
        class="bg-black text-white px-6 py-3 rounded-lg hover:opacity-90"
    >
        Pay Now
    </x-paddle-button>

</div>
</x-layouts.app>