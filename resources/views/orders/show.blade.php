@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Order #{{ $order->id }} Details</h4>
                    
                    <div>
                        @if($order->payment_status !== 'paid')
                            <a href="{{ route('payments.options', $order->id) }}" class="btn btn-light">
                                Pay Now
                            </a>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5>Customer Information</h5>
                            <p><strong>Name:</strong> {{ $order->customer_name }}</p>
                            <p><strong>Phone:</strong> {{ $order->customer_phone }}</p>
                            <p><strong>Delivery Address:</strong> {{ $order->delivery_address }}</p>
                        </div>
                        <div class="col-md-6">
                            <h5>Order Status</h5>
                            <p>
                                <strong>Order Status:</strong>
                                <span class="badge bg-{{ $order->status === 'delivered' ? 'success' : ($order->status === 'in_progress' ? 'primary' : 'warning') }}">
                                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                </span>
                            </p>
                            <p>
                                <strong>Payment Status:</strong>
                                <span class="badge bg-{{ $order->payment_status === 'paid' ? 'success' : ($order->payment_status === 'partially_paid' ? 'warning' : 'danger') }}">
                                    {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                                </span>
                            </p>
                            @if($order->driver)
                                <p><strong>Driver:</strong> {{ $order->driver->name }}</p>
                            @else
                                <p><strong>Driver:</strong> Not assigned yet</p>
                            @endif
                            <p><strong>Created:</strong> {{ $order->created_at->format('M d, Y h:i A') }}</p>
                        </div>
                    </div>

                    <h5 class="mb-3">Order Items</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->items as $item)
                                    <tr>
                                        <td>{{ $item->product->name }}</td>
                                        <td class="text-center">{{ $item->quantity }}</td>
                                        <td class="text-end">${{ number_format($item->unit_price, 2) }}</td>
                                        <td class="text-end">${{ number_format($item->getSubtotal(), 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end">Total:</th>
                                    <th class="text-end">${{ number_format($order->getTotalAmount(), 2) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    @if($order->paymentTransactions->isNotEmpty())
                        <h5 class="mt-4 mb-3">Payment History</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Reference</th>
                                        <th>Method</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($order->paymentTransactions as $transaction)
                                        <tr>
                                            <td>{{ $transaction->reference }}</td>
                                            <td>{{ $transaction->paymentMethod->name }}</td>
                                            <td>${{ number_format($transaction->amount, 2) }}</td>
                                            <td>
                                                <span class="badge bg-{{ 
                                                    $transaction->status === 'completed' ? 'success' : 
                                                    ($transaction->status === 'processing' ? 'warning' : 
                                                    ($transaction->status === 'failed' ? 'danger' : 'secondary')) 
                                                }}">
                                                    {{ ucfirst($transaction->status) }}
                                                </span>
                                            </td>
                                            <td>{{ $transaction->created_at->format('M d, Y h:i A') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="2" class="text-end">Total Paid:</th>
                                        <th colspan="3">${{ number_format($order->getTotalPaidAmount(), 2) }}</th>
                                    </tr>
                                    @if($order->payment_status !== 'paid')
                                        <tr>
                                            <th colspan="2" class="text-end">Remaining:</th>
                                            <th colspan="3">${{ number_format($order->getRemainingAmount(), 2) }}</th>
                                        </tr>
                                    @endif
                                </tfoot>
                            </table>
                        </div>
                    @endif

                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ url()->previous() }}" class="btn btn-secondary">
                            Back
                        </a>
                        
                        @if($order->payment_status !== 'paid')
                            <a href="{{ route('payments.options', $order->id) }}" class="btn btn-primary">
                                Make Payment
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection