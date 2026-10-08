<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $shift_number
 * @property string $status
 * @property Carbon|null $opened_at
 * @property Carbon|null $closed_at
 * @property string $opening_cash_balance
 * @property string $total_cash_sales
 * @property string $total_credit_sales
 * @property string $total_payments_collected
 * @property string $total_refunds
 * @property string $expected_cash_balance
 * @property string $actual_cash_balance
 * @property string $cash_difference
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $store_id
 * @property Carbon|null $deleted_at
 */
class CashShift extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'store_id',
        'shift_number',
        'status',
        'opened_at',
        'closed_at',
        'opening_cash_balance',
        'total_cash_sales',
        'total_credit_sales',
        'total_payments_collected',
        'total_refunds',
        'expected_cash_balance',
        'actual_cash_balance',
        'cash_difference',
        'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_cash_balance' => 'decimal:3',
        'total_cash_sales' => 'decimal:3',
        'total_credit_sales' => 'decimal:3',
        'total_payments_collected' => 'decimal:3',
        'total_refunds' => 'decimal:3',
        'expected_cash_balance' => 'decimal:3',
        'actual_cash_balance' => 'decimal:3',
        'cash_difference' => 'decimal:3',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }
}
