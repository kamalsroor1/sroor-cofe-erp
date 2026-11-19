<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SHOP-02: one item of a stocktake session.
 *
 * @property int $id
 * @property int $stock_count_id
 * @property int $item_id
 * @property string $system_qty
 * @property string|null $counted_qty
 * @property string|null $system_qty_at_post
 * @property string|null $difference
 * @property string $unit_cost
 * @property int|null $stock_movement_id
 * @property int|null $counted_by
 * @property Carbon|null $counted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StockCountLine extends Model
{
    protected $fillable = [
        'stock_count_id',
        'item_id',
        'system_qty',
        'counted_qty',
        'system_qty_at_post',
        'difference',
        'unit_cost',
        'stock_movement_id',
        'counted_by',
        'counted_at',
    ];

    protected function casts(): array
    {
        return [
            'system_qty' => 'decimal:3',
            'counted_qty' => 'decimal:3',
            'system_qty_at_post' => 'decimal:3',
            'difference' => 'decimal:3',
            'unit_cost' => 'decimal:3',
            'counted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StockCount, $this>
     */
    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }
}
