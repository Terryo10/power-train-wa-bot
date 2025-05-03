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
 * Cash on Delivery Payment Service
 */
class CashOnDeliveryPaymentService extends PaymentService
{
    /**
     * Create a Cash on Delivery payment.
     */
    public function createCodPayment(Order $order)
    {
        // Create a transaction
        $transaction = $this->createTransaction($order, 'cod');
        $transaction->status = 'processing'; // COD payments are pending until delivery
        $transaction->save();
        
        // Send confirmation to customer
        app(WhatsAppService::class)->sendMessage(
            $order->customer_phone,
            "Your Order #{$order->id} has been confirmed with Cash on Delivery payment. " .
            "Please have \${$transaction->amount} ready when the driver delivers your order."
        );
        
        return [
            'success' => true,
            'transaction' => $transaction,
            'message' => 'Cash on Delivery payment has been set up. Please have the exact amount ready for the driver.',
        ];
    }
    
    /**
     * Mark a COD payment as completed.
     */
    public function markAsCompleted(PaymentTransaction $transaction)
    {
        $transaction->markAsCompleted();
        
        // Send confirmation to customer
        app(WhatsAppService::class)->sendMessage(
            $transaction->order->customer_phone,
            "Your payment of \${$transaction->amount} for Order #{$transaction->order->id} has been marked as received. Thank you!"
        );
        
        return [
            'success' => true,
            'transaction' => $transaction,
            'message' => 'Payment has been marked as completed.',
        ];
    }
}