<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'category',
        'type',
        'variant',
        'price',
        'description',
    ];

    /**
     * Get the order items for the product.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Scope a query to only include load products.
     */
    public function scopeLoads($query)
    {
        return $query->where('category', 'load');
    }

    /**
     * Scope a query to only include building material products.
     */
    public function scopeBuildingMaterials($query)
    {
        return $query->where('category', 'building_material');
    }

    /**
     * Scope a query to filter by product type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to filter by product variant.
     */
    public function scopeOfVariant($query, $variant)
    {
        return $query->where('variant', $variant);
    }
}