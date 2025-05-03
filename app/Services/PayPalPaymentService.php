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
 * PayPal Payment Service
 */
use App\Services\PaymentService;
// Ensure the correct namespace for PaypalConfig

class PayPalPaymentService extends PaymentService
{
    protected $config;
    
    /**
     * Create a new PayPal payment service instance.
     */
    public function __construct()
    {
        $this->config = PaypalConfig::where('is_active', true)->first();
        
        if (!$this->config) {
            throw new \Exception('PayPal configuration is not available.');
        }
    }
    
    /**
     * Get PayPal API base URL.
     */
    protected function getApiUrl()
    {
        return $this->config->sandbox_mode
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }
    
    /**
     * Get PayPal access token.
     */
    protected function getAccessToken()
    {
        try {
            $response = Http::withBasicAuth($this->config->client_id, $this->config->client_secret)
                ->asForm()
                ->post($this->getApiUrl() . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);
            
            $data = $response->json();
            
            if (isset($data['access_token'])) {
                return $data['access_token'];
            }
            
            throw new \Exception('Failed to get PayPal access token: ' . ($data['error_description'] ?? 'Unknown error'));
        } catch (\Exception $e) {
            Log::error('PayPal access token retrieval failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create a PayPal order.
     */
    public function createPayPalOrder(Order $order, string $customerEmail = null)
    {
        // Create a transaction
        $transaction = $this->createTransaction($order, 'paypal', [
            'customer_email' => $customerEmail,
        ]);
        
        try {
            $accessToken = $this->getAccessToken();
            
            $response = Http::withToken($accessToken)
                ->post($this->getApiUrl() . '/v2/checkout/orders', [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'reference_id' => $transaction->reference,
                            'description' => "Payment for Order #{$order->id}",
                            'amount' => [
                                'currency_code' => $transaction->currency,
                                'value' => number_format($transaction->amount, 2, '.', ''),
                            ],
                        ],
                    ],
                    'application_context' => [
                        'return_url' => $this->config->return_url . '?transaction_id=' . $transaction->id,
                        'cancel_url' => $this->config->cancel_url . '?transaction_id=' . $transaction->id,
                    ],
                ]);
            
            $result = $response->json();
            
            // Update transaction with response
            $transaction->gateway_response = $result;
            
            if (isset($result['id'])) {
                $transaction->gateway_reference = $result['id'];
                $transaction->status = 'processing';
                $transaction->save();
                
                // Find the approval URL
                $approvalUrl = collect($result['links'] ?? [])
                    ->firstWhere('rel', 'approve')['href'] ?? null;
                
                if (!$approvalUrl) {
                    throw new \Exception('No approval URL found in PayPal response.');
                }
                
                return [
                    'success' => true,
                    'transaction' => $transaction,
                    'approval_url' => $approvalUrl,
                ];
            } else {
                $transaction->status = 'failed';
                $transaction->save();
                
                return [
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to create PayPal order.',
                    'transaction' => $transaction,
                ];
            }
        } catch (\Exception $e) {
            Log::error('PayPal order creation failed: ' . $e->getMessage());
            
            $transaction->status = 'failed';
            $transaction->gateway_response = ['error' => $e->getMessage()];
            $transaction->save();
            
            return [
                'success' => false,
                'message' => 'Failed to communicate with PayPal: ' . $e->getMessage(),
                'transaction' => $transaction,
            ];
        }
    }
    
    /**
     * Capture a PayPal payment.
     */
    public function capturePayment(PaymentTransaction $transaction, string $paypalOrderId)
    {
        try {
            $accessToken = $this->getAccessToken();
            
            $response = Http::withToken($accessToken)
                ->post($this->getApiUrl() . "/v2/checkout/orders/{$paypalOrderId}/capture");
            
            $result = $response->json();
            
            // Update transaction with capture response
            $transaction->gateway_response = array_merge(
                $transaction->gateway_response ?? [], 
                ['capture' => $result]
            );
            
            if (isset($result['status']) && $result['status'] === 'COMPLETED') {
                $transaction->markAsCompleted($paypalOrderId);
                
                // Notify customer via WhatsApp
                app(WhatsAppService::class)->sendMessage(
                    $transaction->order->customer_phone,
                    "Your PayPal payment of \${$transaction->amount} for Order #{$transaction->order->id} has been confirmed. Thank you!"
                );
                
                return [
                    'success' => true,
                    'status' => 'completed',
                    'message' => 'Payment completed successfully.',
                    'transaction' => $transaction,
                ];
            } else {
                $transaction->status = 'failed';
                $transaction->save();
                
                // Notify customer via WhatsApp
                app(WhatsAppService::class)->sendMessage(
                    $transaction->order->customer_phone,
                    "Your PayPal payment for Order #{$transaction->order->id} was not successful. Please try again or contact support."
                );
                
                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => $result['message'] ?? 'PayPal payment capture failed.',
                    'transaction' => $transaction,
                ];
            }
        } catch (\Exception $e) {
            Log::error('PayPal payment capture failed: ' . $e->getMessage());
            
            $transaction->status = 'failed';
            $transaction->gateway_response = array_merge(
                $transaction->gateway_response ?? [], 
                ['capture_error' => $e->getMessage()]
            );
            $transaction->save();
            
            return [
                'success' => false,
                'message' => 'Failed to capture PayPal payment: ' . $e->getMessage(),
                'transaction' => $transaction,
            ];
        }
    }
    
    /**
     * Handle PayPal webhook events.
     */
    public function handleWebhook(array $data)
    {
        try {
            // Verify webhook signature (implementation depends on PayPal webhook setup)
            // PayPal sends a verification header that should be checked here
            
            if ($data['event_type'] === 'PAYMENT.CAPTURE.COMPLETED') {
                $paypalOrderId = $data['resource']['supplementary_data']['related_ids']['order_id'] ?? null;
                
                if (!$paypalOrderId) {
                    Log::warning('PayPal webhook missing order ID');
                    return false;
                }
                
                // Find the transaction
                $transaction = PaymentTransaction::where('gateway_reference', $paypalOrderId)->first();
                
                if (!$transaction) {
                    Log::warning('PayPal webhook for unknown transaction: ' . $paypalOrderId);
                    return false;
                }
                
                // Update transaction
                $transaction->markAsCompleted($paypalOrderId);
                
                return true;
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('PayPal webhook processing failed: ' . $e->getMessage());
            return false;
        }
    }
}