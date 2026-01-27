<?php
use Livewire\Volt\Component;
use App\Services\Billing\PlanLimits;
use Livewire\Attributes\Layout;

new
#[Layout('layouts.app')]
class extends Component {
    public function currentPlan()
    {
        $user = auth()->user();
        $planName = $user->billing_plan ?? 'free';
        
        return [
            'name' => ucfirst($planName),
            'isSubscribed' => $user->isSubscribed(),
        ];
    }
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <h2 class="text-2xl font-bold text-gray-900">Billing & Subscription</h2>
    </x-slot>

    @php
        $plan = $this->currentPlan();
    @endphp

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        <x-ui.card>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">{{ $plan['name'] }} Plan</h3>
                    <x-ui.badge :variant="$plan['isSubscribed'] ? 'success' : 'default'">
                        {{ $plan['isSubscribed'] ? 'Active' : 'Free' }}
                    </x-ui.badge>
                </div>
                
                @if($plan['name'] !== 'Team')
                <x-ui.button href="{{ route('billing.checkout.page') }}" variant="primary">
                    Upgrade Plan
                </x-ui.button>
                @endif
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 rounded-lg border border-gray-200 hover:border-emerald-300 transition cursor-pointer">
                    <h4 class="font-semibold text-gray-900 mb-1">Upgrade</h4>
                    <p class="text-sm text-gray-600">Get more features</p>
                </div>
                <div class="p-4 rounded-lg border border-gray-200 hover:border-emerald-300 transition cursor-pointer">
                    <h4 class="font-semibold text-gray-900 mb-1">Payment Method</h4>
                    <p class="text-sm text-gray-600">Update card details</p>
                </div>
                <div class="p-4 rounded-lg border border-gray-200 hover:border-emerald-300 transition cursor-pointer">
                    <h4 class="font-semibold text-gray-900 mb-1">Invoices</h4>
                    <p class="text-sm text-gray-600">View payment history</p>
                </div>
            </div>
        </x-ui.card>
    </div>
</div>