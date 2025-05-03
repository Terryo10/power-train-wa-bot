@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Payment Options for Order #{{ $order->id }}</h4>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h5>Order Details</h5>
                        <p><strong>Customer:</strong> {{ $order->customer_name }}</p>
                        <p><strong>Amount Due:</strong> ${{ number_format($remainingAmount, 2) }}</p>
                        <p><strong>Delivery Address:</strong> {{ $order->delivery_address }}</p>
                    </div>

                    <h5 class="mb-3">Select a Payment Method</h5>

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="list-group mb-4">
                        @foreach($paymentMethods as $method)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1">{{ $method->name }}</h5>
                                        <p class="mb-1 text-muted">{{ $method->description }}</p>
                                        @if($method->instructions)
                                            <small class="text-muted">{{ $method->instructions }}</small>
                                        @endif
                                    </div>
                                    
                                    <div>
                                        @if($method->code === 'ecocash')
                                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ecocashModal">
                                                Pay with EcoCash
                                            </button>
                                        @elseif($method->code === 'paypal')
                                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#paypalModal">
                                                Pay with PayPal
                                            </button>
                                        @elseif($method->code === 'bank_transfer')
                                            <form action="{{ route('payments.bank-transfer', $order->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-primary">Get Bank Details</button>
                                            </form>
                                        @elseif($method->code === 'cod')
                                            <form action="{{ route('payments.cod', $order->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-primary">Pay on Delivery</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="text-center">
                        <a href="{{ route('orders.show', $order->id) }}" class="btn btn-secondary">
                            Back to Order
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- EcoCash Modal -->
<div class="modal fade" id="ecocashModal" tabindex="-1" aria-labelledby="ecocashModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('payments.ecocash.process', $order->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="ecocashModalLabel">Pay with EcoCash</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="phone_number" class="form-label">EcoCash Phone Number</label>
                        <input type="tel" class="form-control" id="phone_number" name="phone_number" required 
                            placeholder="e.g. 0771234567">
                        <div class="form-text">
                            Enter the phone number registered with your EcoCash account.
                        </div>
                    </div>
                    <p>You will receive a prompt on your mobile phone to enter your EcoCash PIN.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Continue</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- PayPal Modal -->
<div class="modal fade" id="paypalModal" tabindex="-1" aria-labelledby="paypalModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('payments.paypal.process', $order->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="paypalModalLabel">Pay with PayPal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="email" class="form-label">PayPal Email (Optional)</label>
                        <input type="email" class="form-control" id="email" name="email" 
                            placeholder="your@email.com">
                        <div class="form-text">
                            You can leave this blank if you'll use a different email on PayPal.
                        </div>
                    </div>
                    <p>You will be redirected to PayPal to complete your payment.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Continue to PayPal</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection