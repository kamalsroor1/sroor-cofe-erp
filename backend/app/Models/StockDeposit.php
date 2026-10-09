<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $item_id
 * @property int|null $user_id
 * @property string $deposit_type
 * @property string $quantity
 * @property string $cost_price
 * @property string|null $reason
 * @property Carbon|null $deposit_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class StockDeposit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'item_id',
        'user_id',
        'deposit_type',
        'quantity',
        'cost_price',
        'reason',
        'deposit_date',
    ];

    protected function casts(): array
    {
        return [
            'deposit_date' => 'date',
            'quantity' => 'decimal:3',
            'cost_price' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
