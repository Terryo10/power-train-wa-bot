<?php

namespace App\Services;

use App\Models\EcocashConfig;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\PaypalConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\BankTransferDetail;

/**
 * Bank Transfer Payment Service
 */
class BankTransferPaymentService extends PaymentService
{
    /**
     * Create a Bank Transfer payment.
     */
    public function createBankTransferPayment(Order $order)
    {
        // Create a transaction
        $transaction = $this->createTransaction($order, 'bank_transfer');
        $transaction->status = 'pending';
        $transaction->save();

        // Get bank details
        $bankDetails = BankTransferDetail::where('is_active', true)->first();

        if (!$bankDetails) {
            return [
                'success' => false,
                'message' => 'Bank transfer details are not available.',
            ];
        }

        // Format bank details message
        $message = "Your Order #{$order->id} for \${$transaction->amount} has been confirmed.\n\n" .
            "Please transfer the amount to the following account:\n" .
            "Bank: {$bankDetails->bank_name}\n" .
            "Account Name: {$bankDetails->account_name}\n" .
            "Account Number: {$bankDetails->account_number}\n";

        if ($bankDetails->branch_code) {
            $message .= "Branch Code: {$bankDetails->branch_code}\n";
        }

        if ($bankDetails->swift_code) {
            $message .= "SWIFT/BIC: {$bankDetails->swift_code}\n";
        }

        $message .= "\nPlease use your Order Number #{$order->id} as the payment reference.\n\n";

        if ($bankDetails->instructions) {
            $message .= "Additional Instructions:\n{$bankDetails->instructions}\n\n";
        }

        $message .= "Once you've made the payment, please reply with 'PAID' and we will verify your payment.";

        // Send bank details to customer
        app(WhatsAppService::class)->sendMessage($order->customer_phone, $message);

        return [
            'success' => true,
            'transaction' => $transaction,
            'message' => 'Bank transfer instructions have been sent to your WhatsApp number.',
            'bank_details' => $bankDetails,
        ];
    }

    /**
     * Manually verify a bank transfer payment.
     */
    public function verifyPayment(PaymentTransaction $transaction)
    {
        $transaction->markAsCompleted();

        // Send confirmation to customer
        app(WhatsAppService::class)->sendMessage(
            $transaction->order->customer_phone,
            "Your bank transfer payment of \${$transaction->amount} for Order #{$transaction->order->id} has been verified. Thank you!"
        );

        return [
            'success' => true,
            'transaction' => $transaction,
            'message' => 'Payment has been verified and marked as completed.',
        ];
    }
}
