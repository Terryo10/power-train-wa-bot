<?php

namespace App\Services;

use App\Models\EcocashConfig;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\PaypalConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Base Payment Service
 */
abstract class PaymentService
{
    /**
     * Create a new payment transaction for an order.
     */
    public function createTransaction(Order $order, string $methodCode, array $additionalData = [])
    {
        $paymentMethod = PaymentMethod::where('code', $methodCode)->firstOrFail();
        
        $transaction = new PaymentTransaction([
            'reference' => PaymentTransaction::generateReference(),
            'order_id' => $order->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => $order->getRemainingAmount(),
            'currency' => 'USD', // You might want to make this configurable
            'status' => 'pending',
            'customer_phone' => $additionalData['customer_phone'] ?? $order->customer_phone,
            'customer_email' => $additionalData['customer_email'] ?? null,
        ]);
        
        $transaction->save();
        
        return $transaction;
    }
}






