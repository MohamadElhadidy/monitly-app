<x-layouts.app>
<div class="max-w-4xl mx-auto py-10 space-y-8">

    <h1 class="text-2xl font-bold">Billing</h1>

    <div class="grid md:grid-cols-2 gap-6">

        {{-- PRO PLAN --}}
        <div class="border rounded-xl p-6">
            <h2 class="text-xl font-semibold mb-2">Pro</h2>
            <p class="text-gray-600 mb-4">For individuals</p>

            <form method="POST" action="{{ route('billing.checkout') }}">
                @csrf
                <input type="hidden" name="plan" value="pro">
                <input type="hidden" name="scope" value="user">

                <button
                    type="submit"
                    class="w-full bg-black text-white py-2 rounded-lg hover:opacity-90">
                    Subscribe
                </button>
            </form>
        </div>

        {{-- TEAM PLAN --}}
        <div class="border rounded-xl p-6">
            <h2 class="text-xl font-semibold mb-2">Team</h2>
            <p class="text-gray-600 mb-4">For teams</p>

            @if(auth()->user()->currentTeam)
                <form method="POST" action="{{ route('billing.checkout') }}">
                    @csrf
                    <input type="hidden" name="plan" value="team">
                    <input type="hidden" name="scope" value="team">

                    <button
                        type="submit"
                        class="w-full bg-black text-white py-2 rounded-lg hover:opacity-90">
                        Subscribe as Team
                    </button>
                </form>
            @else
                <p class="text-sm text-red-600">
                    You must belong to a team to subscribe.
                </p>
            @endif
        </div>

    </div>

    {{-- PORTAL --}}
    <div>
        <a href="{{ route('billing.portal') }}"
           class="text-sm text-blue-600 underline">
            Open Billing Portal
        </a>
    </div>

</div>
</x-layouts.app>