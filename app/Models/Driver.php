<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'phone',
        'available',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'available' => 'boolean',
    ];

    /**
     * Get the orders for the driver.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Scope a query to only include available drivers.
     */
    public function scopeAvailable($query)
    {
        return $query->where('available', true);
    }

    /**
     * Mark the driver as available.
     */
    public function markAsAvailable()
    {
        $this->available = true;
        $this->save();
        
        return $this;
    }

    /**
     * Mark the driver as unavailable.
     */
    public function markAsUnavailable()
    {
        $this->available = false;
        $this->save();
        
        return $this;
    }

    /**
     * Get the current active order for the driver.
     */
    public function getCurrentOrder()
    {
        return $this->orders()
            ->where('status', Order::STATUS_IN_PROGRESS)
            ->latest()
            ->first();
    }

    /**
     * Get the count of completed orders for the driver.
     */
    public function getCompletedOrdersCount()
    {
        return $this->orders()
            ->where('status', Order::STATUS_DELIVERED)
            ->count();
    }
}