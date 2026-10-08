<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int $item_id
 * @property string $quantity
 * @property string $cost_price
 * @property string $unit_price
 * @property string $discount_amount
 * @property string $total_price
 */
class InvoiceItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_id',
        'item_id',
        'quantity',
        'cost_price',
        'unit_price',
        'discount_amount',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'cost_price' => 'decimal:3',
            'unit_price' => 'decimal:3',
            'discount_amount' => 'decimal:3',
            'total_price' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function getProfitAttribute(): string
    {
        $lineCost = bcmul($this->quantity, $this->cost_price, 3);

        return bcsub($this->total_price, $lineCost, 3);
    }
}
