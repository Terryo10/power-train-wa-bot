<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class PaymentTransaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reference',
        'order_id',
        'payment_method_id',
        'amount',
        'currency',
        'status',
        'gateway_reference',
        'poll_url',
        'customer_phone',
        'customer_email',
        'gateway_response',
        'paid_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'json',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the order that owns the transaction.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the payment method that owns the transaction.
     */
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * Generate a unique reference number.
     */
    public static function generateReference()
    {
        return 'TXN-' . strtoupper(uniqid());
    }

    /**
     * Mark the transaction as complete.
     */
    public function markAsCompleted($gatewayReference = null)
    {
        $this->status = 'completed';
        $this->gateway_reference = $gatewayReference ?? $this->gateway_reference;
        $this->paid_at = now();
        $this->save();

        // Also update order payment status
        $this->updateOrderPaymentStatus();

        return $this;
    }

    /**
     * Mark the transaction as failed.
     */
    public function markAsFailed()
    {
        $this->status = 'failed';
        $this->save();

        return $this;
    }

    /**
     * Update the order's payment status based on transactions.
     */
    protected function updateOrderPaymentStatus()
    {
        $order = $this->order;
        $completedTransactionsAmount = $order->paymentTransactions()
            ->where('status', 'completed')
            ->sum('amount');

        $orderTotal = $order->getTotalAmount();

        if ($completedTransactionsAmount >= $orderTotal) {
            $order->payment_status = 'paid';
        } elseif ($completedTransactionsAmount > 0) {
            $order->payment_status = 'partially_paid';
        } else {
            $order->payment_status = 'unpaid';
        }

        $order->save();
    }
}
