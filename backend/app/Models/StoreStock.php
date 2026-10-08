<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $store_id
 * @property int $item_id
 * @property string $quantity
 * @property string $min_stock
 * @property string|null $custom_selling_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class StoreStock extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'item_id',
        'quantity',
        'min_stock',
        'custom_selling_price',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'min_stock' => 'decimal:3',
        'custom_selling_price' => 'decimal:3',
    ];

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /**
     * Get the effective selling price for this item in this store.
     * Returns custom_selling_price if set, otherwise falls back to master item selling_price.
     */
    public function getEffectiveSellingPriceAttribute(): string
    {
        if ($this->custom_selling_price !== null && bccomp((string) $this->custom_selling_price, '0.000', 3) > 0) {
            return (string) $this->custom_selling_price;
        }

        return (string) ($this->item?->selling_price ?? '0.000');
    }

    /**
     * Scope for low stock
     */
    public function scopeLowStock($query)
    {
        return $query->whereColumn('quantity', '<=', 'min_stock');
    }

    /**
     * Check if current quantity is at or below minimum threshold
     */
    public function isLowStock(): bool
    {
        return bccomp((string) $this->quantity, (string) $this->min_stock, 3) <= 0;
    }
}
