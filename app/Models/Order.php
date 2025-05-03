<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_name',
        'customer_phone',
        'delivery_address',
        'status',
        'driver_id',
        'payment_status',
    ];

    /**
     * The status values an order can have.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DELIVERED = 'delivered';

    /**
     * The payment status values an order can have.
     */
    public const PAYMENT_STATUS_UNPAID = 'unpaid';
    public const PAYMENT_STATUS_PARTIALLY_PAID = 'partially_paid';
    public const PAYMENT_STATUS_PAID = 'paid';

    /**
     * Get the items for the order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the driver associated with the order.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * Get the payment transactions for the order.
     */
    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /**
     * Scope a query to only include pending orders.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include in-progress orders.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * Scope a query to only include delivered orders.
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    /**
     * Scope a query to only include unpaid orders.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', self::PAYMENT_STATUS_UNPAID);
    }

    /**
     * Scope a query to only include paid orders.
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', self::PAYMENT_STATUS_PAID);
    }

    /**
     * Calculate the total amount for this order.
     */
    public function getTotalAmount()
    {
        return $this->items->sum(function ($item) {
            return $item->quantity * $item->unit_price;
        });
    }

    /**
     * Calculate the total paid amount for this order.
     */
    public function getTotalPaidAmount()
    {
        return $this->paymentTransactions()
            ->where('status', 'completed')
            ->sum('amount');
    }

    /**
     * Calculate the remaining amount to be paid.
     */
    public function getRemainingAmount()
    {
        return max(0, $this->getTotalAmount() - $this->getTotalPaidAmount());
    }

    /**
     * Check if the order is fully paid.
     */
    public function isFullyPaid()
    {
        return $this->payment_status === self::PAYMENT_STATUS_PAID;
    }

    /**
     * Mark this order as in progress.
     */
    public function markAsInProgress()
    {
        $this->status = self::STATUS_IN_PROGRESS;
        $this->save();
        
        return $this;
    }

    /**
     * Mark this order as delivered.
     */
    public function markAsDelivered()
    {
        $this->status = self::STATUS_DELIVERED;
        $this->save();
        
        return $this;
    }
}