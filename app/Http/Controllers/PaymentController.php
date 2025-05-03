<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Services\BankTransferPaymentService;
use App\Services\CashOnDeliveryPaymentService;
use App\Services\EcocashPaymentService;
use App\Services\PayPalPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Show payment options for an order.
     *
     * @param int $orderId
     * @return \Illuminate\View\View
     */
    public function showPaymentOptions($orderId)
    {
        $order = Order::findOrFail($orderId);
        
        // Don't show payment options for fully paid orders
        if ($order->isFullyPaid()) {
            return redirect()->route('orders.show', $order)
                ->with('message', 'This order has already been paid in full.');
        }
        
        // Get active payment methods
        $paymentMethods = PaymentMethod::active()
            ->orderBy('display_order')
            ->get();
        
        return view('payments.options', [
            'order' => $order,
            'paymentMethods' => $paymentMethods,
            'remainingAmount' => $order->getRemainingAmount(),
        ]);
    }
    
    /**
     * Process EcoCash payment.
     *
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processEcocash(Request $request, $orderId)
    {
        $request->validate([
            'phone_number' => 'required|string|min:9',
        ]);
        
        $order = Order::findOrFail($orderId);
        
        if ($order->isFullyPaid()) {
            return redirect()->route('orders.show', $order)
                ->with('message', 'This order has already been paid in full.');
        }
        
        try {
            $ecocashService = new EcocashPaymentService();
            $result = $ecocashService->initiatePayment($order, $request->phone_number);
            
            if ($result['success']) {
                // Store transaction ID in session for status checking
                session(['ecocash_transaction_id' => $result['transaction']->id]);
                
                return view('payments.ecocash.process', [
                    'order' => $order,
                    'transaction' => $result['transaction'],
                    'poll_url' => $result['poll_url'],
                ]);
            } else {
                return redirect()->back()
                    ->with('error', $result['message'] ?? 'Failed to initiate EcoCash payment.')
                    ->withInput();
            }
        } catch (\Exception $e) {
            Log::error('EcoCash payment processing failed: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to process EcoCash payment: ' . $e->getMessage())
                ->withInput();
        }
    }
    
    /**
     * Check EcoCash payment status.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkEcocashStatus(Request $request)
    {
        $transactionId = $request->input('transaction_id') ?? session('ecocash_transaction_id');
        
        if (!$transactionId) {
            return response()->json([
                'success' => false,
                'message' => 'No transaction ID provided.',
            ]);
        }
        
        try {
            $transaction = PaymentTransaction::findOrFail($transactionId);
            $ecocashService = new EcocashPaymentService();
            $result = $ecocashService->checkPaymentStatus($transaction);
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('EcoCash status check failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment status: ' . $e->getMessage(),
            ]);
        }
    }
    
    /**
     * EcoCash payment callback.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function ecocashCallback(Request $request)
    {
        try {
            $ecocashService = new EcocashPaymentService();
            $result = $ecocashService->handleCallback($request->all());
            
            if ($result) {
                return response('OK', 200);
            } else {
                return response('Failed to process callback', 500);
            }
        } catch (\Exception $e) {
            Log::error('EcoCash callback processing failed: ' . $e->getMessage());
            return response('Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Process PayPal payment.
     *
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processPaypal(Request $request, $orderId)
    {
        $request->validate([
            'email' => 'nullable|email',
        ]);
        
        $order = Order::findOrFail($orderId);
        
        if ($order->isFullyPaid()) {
            return redirect()->route('orders.show', $order)
                ->with('message', 'This order has already been paid in full.');
        }
        
        try {
            $paypalService = new PayPalPaymentService();
            $result = $paypalService->createPayPalOrder($order, $request->email);
            
            if ($result['success']) {
                // Redirect to PayPal for payment
                return redirect($result['approval_url']);
            } else {
                return redirect()->back()
                    ->with('error', $result['message'] ?? 'Failed to initiate PayPal payment.')
                    ->withInput();
            }
        } catch (\Exception $e) {
            Log::error('PayPal payment processing failed: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to process PayPal payment: ' . $e->getMessage())
                ->withInput();
        }
    }
    
    /**
     * PayPal payment return handler.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function paypalReturn(Request $request)
    {
        $transactionId = $request->input('transaction_id');
        $token = $request->input('token');
        $payerId = $request->input('PayerID');
        
        if (!$transactionId || !$token || !$payerId) {
            return redirect()->route('home')
                ->with('error', 'Invalid PayPal return data.');
        }
        
        try {
            $transaction = PaymentTransaction::findOrFail($transactionId);
            $order = $transaction->order;
            
            $paypalService = new PayPalPaymentService();
            $result = $paypalService->capturePayment($transaction, $transaction->gateway_reference);
            
            if ($result['success']) {
                return redirect()->route('orders.show', $order)
                    ->with('success', 'Payment completed successfully!');
            } else {
                return redirect()->route('payments.options', $order)
                    ->with('error', $result['message'] ?? 'Failed to complete PayPal payment.');
            }
        } catch (\Exception $e) {
            Log::error('PayPal return processing failed: ' . $e->getMessage());
            
            return redirect()->route('home')
                ->with('error', 'Failed to process PayPal payment: ' . $e->getMessage());
        }
    }
    
    /**
     * PayPal payment cancel handler.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function paypalCancel(Request $request)
    {
        $transactionId = $request->input('transaction_id');
        
        if (!$transactionId) {
            return redirect()->route('home')
                ->with('message', 'Payment was cancelled.');
        }
        
        try {
            $transaction = PaymentTransaction::findOrFail($transactionId);
            $order = $transaction->order;
            
            // Mark the transaction as failed
            $transaction->status = 'failed';
            $transaction->gateway_response = array_merge(
                $transaction->gateway_response ?? [], 
                ['cancelled_by_user' => true]
            );
            $transaction->save();
            
            return redirect()->route('payments.options', $order)
                ->with('message', 'Payment was cancelled. Please try again or choose another payment method.');
        } catch (\Exception $e) {
            Log::error('PayPal cancel processing failed: ' . $e->getMessage());
            
            return redirect()->route('home')
                ->with('error', 'Failed to process PayPal cancellation: ' . $e->getMessage());
        }
    }
    
    /**
     * PayPal webhook handler.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function paypalWebhook(Request $request)
    {
        try {
            $paypalService = new PayPalPaymentService();
            $result = $paypalService->handleWebhook($request->all());
            
            if ($result) {
                return response('OK', 200);
            } else {
                return response('Failed to process webhook', 500);
            }
        } catch (\Exception $e) {
            Log::error('PayPal webhook processing failed: ' . $e->getMessage());
            return response('Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Process Cash on Delivery payment.
     *
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processCod(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);
        
        if ($order->isFullyPaid()) {
            return redirect()->route('orders.show', $order)
                ->with('message', 'This order has already been paid in full.');
        }
        
        try {
            $codService = new CashOnDeliveryPaymentService();
            $result = $codService->createCodPayment($order);
            
            if ($result['success']) {
                return redirect()->route('orders.show', $order)
                    ->with('success', 'Your order has been confirmed with Cash on Delivery payment.');
            } else {
                return redirect()->back()
                    ->with('error', $result['message'] ?? 'Failed to set up Cash on Delivery payment.');
            }
        } catch (\Exception $e) {
            Log::error('COD payment processing failed: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to process Cash on Delivery payment: ' . $e->getMessage());
        }
    }
    
    /**
     * Process Bank Transfer payment.
     *
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processBankTransfer(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);
        
        if ($order->isFullyPaid()) {
            return redirect()->route('orders.show', $order)
                ->with('message', 'This order has already been paid in full.');
        }
        
        try {
            $bankService = new BankTransferPaymentService();
            $result = $bankService->createBankTransferPayment($order);
            
            if ($result['success']) {
                return redirect()->route('orders.show', $order)
                    ->with('success', 'Bank transfer instructions have been sent to your WhatsApp number.');
            } else {
                return redirect()->back()
                    ->with('error', $result['message'] ?? 'Failed to set up Bank Transfer payment.');
            }
        } catch (\Exception $e) {
            Log::error('Bank Transfer payment processing failed: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to process Bank Transfer payment: ' . $e->getMessage());
        }
    }
}