@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">EcoCash Payment in Progress</h4>
                </div>
                <div class="card-body text-center">
                    <div class="mb-4">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <h5>EcoCash Payment Processing</h5>
                        <p>Please check your phone for the EcoCash prompt and enter your PIN to complete the payment.</p>
                        <p class="text-muted">Transaction Reference: {{ $transaction->reference }}</p>
                    </div>

                    <div id="payment-status" class="alert alert-info">
                        Waiting for payment confirmation...
                    </div>

                    <div class="d-flex justify-content-center mt-4">
                        <a href="{{ route('payments.options', $order->id) }}" class="btn btn-secondary me-2">
                            Cancel
                        </a>
                        <button id="check-status-btn" class="btn btn-primary">
                            Check Status
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Check payment status automatically every 5 seconds
        let statusInterval = setInterval(checkPaymentStatus, 5000);
        
        // Also check when button is clicked
        document.getElementById('check-status-btn').addEventListener('click', function() {
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Checking...';
            checkPaymentStatus();
        });

        function checkPaymentStatus() {
            fetch('{{ route("payments.ecocash.check-status") }}?transaction_id={{ $transaction->id }}')
                .then(response => response.json())
                .then(data => {
                    const statusDiv = document.getElementById('payment-status');
                    const checkBtn = document.getElementById('check-status-btn');
                    
                    if (data.success) {
                        if (data.status === 'completed') {
                            clearInterval(statusInterval);
                            statusDiv.className = 'alert alert-success';
                            statusDiv.innerHTML = 'Payment completed successfully! Redirecting...';
                            setTimeout(() => {
                                window.location.href = '/orders/{{ $order->id }}';
                            }, 2000);
                        } else if (data.status === 'processing') {
                            statusDiv.className = 'alert alert-info';
                            statusDiv.innerHTML = 'Payment is still being processed. Please wait...';
                        } else if (data.status === 'failed') {
                            clearInterval(statusInterval);
                            statusDiv.className = 'alert alert-danger';
                            statusDiv.innerHTML = 'Payment failed: ' + data.message;
                        }
                    } else {
                        statusDiv.className = 'alert alert-warning';
                        statusDiv.innerHTML = 'Error checking payment status: ' + data.message;
                    }
                    
                    checkBtn.disabled = false;
                    checkBtn.innerHTML = 'Check Status';
                })
                .catch(error => {
                    console.error('Error checking payment status:', error);
                    const statusDiv = document.getElementById('payment-status');
                    statusDiv.className = 'alert alert-danger';
                    statusDiv.innerHTML = 'Error checking payment status. Please try again.';
                    
                    const checkBtn = document.getElementById('check-status-btn');
                    checkBtn.disabled = false;
                    checkBtn.innerHTML = 'Check Status';
                });
        }
    });
</script>
@endsection