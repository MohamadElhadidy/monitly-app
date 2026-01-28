<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new 
#[Layout('layouts.app')]
class extends Component {
    public $priceId;
    
    public function mount($priceId = null)
    {
        $this->priceId = $priceId ?? request('price_id');
    }
}
?>

<div class="py-12">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-8 text-center">
            <h1 class="text-2xl font-bold mb-4">Complete Your Subscription</h1>
            <button onclick="openPaddleCheckout()" class="px-6 py-3 bg-blue-600 text-white rounded-lg">
                Proceed to Payment
            </button>
        </div>
    </div>
    
    @push('scripts')
<script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
<script>
    Paddle.Initialize({
        token: "{{ config('services.paddle.client_side_token') }}",
        @if(config('services.paddle.sandbox'))
        environment: "sandbox",
        @endif
    });

    function openPaddleCheckout() {
        Paddle.Checkout.open({
            items: [{ priceId: "{{ $priceId }}", quantity: 1 }],
            customData: { user_id: "{{ auth()->id() }}" },
            successCallback: function() {
                window.location.href = "{{ route('billing.success') }}";
            }
        });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        openPaddleCheckout();
    });
</script>
@endpush
</div>

