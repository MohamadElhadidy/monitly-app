@extends('layouts.app')

@section('title', 'Payment Successful')

@section('content')
<div class="max-w-xl mx-auto py-20 text-center space-y-6">

    <h1 class="text-3xl font-bold text-green-600">Payment Successful</h1>

    <p class="text-gray-600">
        Your subscription is being activated.
        This may take a few seconds.
    </p>

    <a href="{{ route('billing.index') }}"
       class="inline-block bg-black text-white px-6 py-2 rounded-lg">
        Go to Billing
    </a>

</div>
@endsection