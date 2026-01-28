<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new
#[Layout('layouts.app')]
class extends Component {
    public function mounted()
    {
        // Optionally refresh user data after successful payment
        auth()->user()->refresh();
    }
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <h2 class="text-2xl font-bold text-gray-900">Subscription Successful!</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8 py-12">
        <x-ui.card class="text-center">
            <!-- Success Icon -->
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-100 mb-6">
                <svg class="h-10 w-10 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <h3 class="text-2xl font-bold text-gray-900 mb-3">Welcome to Your New Plan!</h3>
            <p class="text-gray-600 mb-8">
                Your subscription has been activated successfully. You now have access to all the features of your plan.
            </p>

            <!-- Next Steps -->
            <div class="bg-gray-50 rounded-lg p-6 mb-8 text-left">
                <h4 class="font-semibold text-gray-900 mb-4">What's Next?</h4>
                <ul class="space-y-3">
                    <li class="flex items-start gap-3">
                        <svg class="h-5 w-5 text-emerald-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Create Your Monitors</p>
                            <p class="text-xs text-gray-600">Start monitoring your websites and APIs</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="h-5 w-5 text-emerald-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Set Up Notifications</p>
                            <p class="text-xs text-gray-600">Configure email, Slack, or webhook alerts</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="h-5 w-5 text-emerald-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Explore Features</p>
                            <p class="text-xs text-gray-600">Check out SLA reports and team collaboration</p>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <x-ui.button href="{{ route('monitors.index') }}" variant="primary">
                    <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Create Your First Monitor
                </x-ui.button>
                <x-ui.button href="{{ route('dashboard') }}" variant="secondary">
                    Go to Dashboard
                </x-ui.button>
            </div>

            <!-- Receipt Notice -->
            <div class="mt-8 pt-8 border-t border-gray-200">
                <p class="text-sm text-gray-600">
                    <svg class="inline h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    A receipt has been sent to your email address.
                </p>
            </div>
        </x-ui.card>
    </div>
</div>