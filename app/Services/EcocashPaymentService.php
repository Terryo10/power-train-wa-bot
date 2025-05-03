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
 * EcoCash Payment Service
 */
class EcocashPaymentService extends PaymentService
{
    protected $config;

    /**
     * Create a new EcoCash payment service instance.
     */
    public function __construct()
    {
        $this->config = EcocashConfig::where('is_active', true)->first();

        if (!$this->config) {
            throw new \Exception('EcoCash configuration is not available.');
        }
    }

    /**
     * Initiate an EcoCash payment.
     */
    public function initiatePayment(Order $order, string $phoneNumber)
    {
        // Ensure the phone number starts with the correct prefix
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);

        // Create a transaction
        $transaction = $this->createTransaction($order, 'ecocash', [
            'customer_phone' => $phoneNumber,
        ]);

        try {
            // Call the EcoCash API to initiate payment
            $response = Http::post('https://www.paynow.co.zw/interface/initiatetransaction', [
                'id' => $this->config->integration_id,
                'reference' => $transaction->reference,
                'amount' => $transaction->amount,
                'additionalinfo' => "Payment for Order #{$order->id}",
                'returnurl' => $this->config->return_url,
                'resulturl' => $this->config->result_url,
                'status' => 'Message',
                'authemail' => 'youremail@domain.com', // This should be configurable
                'hash' => $this->createHash([
                    $this->config->integration_id,
                    $transaction->reference,
                    $transaction->amount,
                    "Payment for Order #{$order->id}",
                    $this->config->return_url,
                    $this->config->result_url,
                    'Message',
                    'youremail@domain.com',
                ]),
            ]);

            $result = $response->json();

            // Update transaction with response
            $transaction->gateway_response = $result;

            if ($result['status'] === 'Ok') {
                $transaction->poll_url = $result['pollurl'];
                $transaction->status = 'processing';
                $transaction->save();

                // Initiate mobile payment
                $mobileResponse = Http::post($result['browserurl'], [
                    'phone' => $phoneNumber,
                    'method' => 'ecocash',
                ]);

                $mobileResult = $mobileResponse->json();

                // Update transaction with mobile payment response
                $transaction->gateway_response = array_merge(
                    $transaction->gateway_response,
                    ['mobile_response' => $mobileResult]
                );
                $transaction->save();

                return [
                    'success' => true,
                    'transaction' => $transaction,
                    'poll_url' => $transaction->poll_url,
                ];
            } else {
                $transaction->status = 'failed';
                $transaction->save();

                return [
                    'success' => false,
                    'message' => $result['error'] ?? 'Failed to initiate EcoCash payment.',
                    'transaction' => $transaction,
                ];
            }
        } catch (\Exception $e) {
            Log::error('EcoCash payment initiation failed: ' . $e->getMessage());

            $transaction->status = 'failed';
            $transaction->gateway_response = ['error' => $e->getMessage()];
            $transaction->save();

            return [
                'success' => false,
                'message' => 'Failed to communicate with EcoCash: ' . $e->getMessage(),
                'transaction' => $transaction,
            ];
        }
    }

    /**
     * Check the status of an EcoCash payment.
     */
    public function checkPaymentStatus(PaymentTransaction $transaction)
    {
        if (!$transaction->poll_url) {
            return [
                'success' => false,
                'message' => 'No poll URL available for this transaction.',
            ];
        }

        try {
            $response = Http::get($transaction->poll_url);
            $result = $response->json();

            // Update transaction with status response
            $transaction->gateway_response = array_merge(
                $transaction->gateway_response ?? [],
                ['status_check' => $result]
            );

            if (isset($result['status']) && $result['status'] === 'Paid') {
                $transaction->markAsCompleted($result['paynowreference'] ?? null);

                return [
                    'success' => true,
                    'status' => 'completed',
                    'message' => 'Payment completed successfully.',
                    'transaction' => $transaction,
                ];
            } elseif (isset($result['status']) && in_array($result['status'], ['Cancelled', 'Failed'])) {
                $transaction->markAsFailed();

                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => 'Payment was not successful.',
                    'transaction' => $transaction,
                ];
            } else {
                // Still processing
                return [
                    'success' => true,
                    'status' => 'processing',
                    'message' => 'Payment is still being processed.',
                    'transaction' => $transaction,
                ];
            }
        } catch (\Exception $e) {
            Log::error('EcoCash payment status check failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to check payment status: ' . $e->getMessage(),
                'transaction' => $transaction,
            ];
        }
    }

    /**
     * Handle EcoCash webhook callback.
     */
    public function handleCallback(array $data)
    {
        try {
            // Verify the hash
            $expectedHash = $this->createHash([
                $data['reference'],
                $data['paynowreference'],
                $data['amount'],
                $data['status'],
                $data['pollurl'],
                $data['hash'],
            ]);

            if ($expectedHash !== $data['hash']) {
                Log::warning('EcoCash callback hash validation failed');
                return false;
            }

            // Find the transaction
            $transaction = PaymentTransaction::where('reference', $data['reference'])->first();

            if (!$transaction) {
                Log::warning('EcoCash callback for unknown transaction: ' . $data['reference']);
                return false;
            }

            // Update transaction with callback data
            $transaction->gateway_response = array_merge(
                $transaction->gateway_response ?? [],
                ['callback' => $data]
            );

            if ($data['status'] === 'Paid') {
                $transaction->markAsCompleted($data['paynowreference']);

                // Notify customer via WhatsApp
                app(WhatsAppService::class)->sendMessage(
                    $transaction->order->customer_phone,
                    "Your payment of \${$transaction->amount} for Order #{$transaction->order->id} has been confirmed. Thank you!"
                );

                return true;
            } elseif (in_array($data['status'], ['Cancelled', 'Failed'])) {
                $transaction->markAsFailed();

                // Notify customer via WhatsApp
                app(WhatsAppService::class)->sendMessage(
                    $transaction->order->customer_phone,
                    "Your payment for Order #{$transaction->order->id} was not successful. Please try again or contact support."
                );

                return true;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('EcoCash callback processing failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Format phone number for EcoCash.
     */
    protected function formatPhoneNumber($phoneNumber)
    {
        // Remove any non-numeric characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Remove leading 0 if present
        if (substr($phoneNumber, 0, 1) === '0') {
            $phoneNumber = substr($phoneNumber, 1);
        }

        // Add the country code prefix if not already present
        if (substr($phoneNumber, 0, strlen($this->config->phone_prefix)) !== $this->config->phone_prefix) {
            $phoneNumber = $this->config->phone_prefix . $phoneNumber;
        }

        return $phoneNumber;
    }

    /**
     * Create hash for EcoCash API.
     */
    protected function createHash(array $values)
    {
        $string = implode('', $values) . $this->config->integration_key;
        return strtoupper(hash('sha512', $string));
    }
}
