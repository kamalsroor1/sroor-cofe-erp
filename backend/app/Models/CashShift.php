<?php

namespace App\Models;

use App\Enums\ShiftMode;
use App\Policies\ShiftPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $shift_number
 * @property string $status
 * @property string $shift_mode
 * @property string|null $open_guard
 * @property Carbon|null $opened_at
 * @property Carbon|null $closed_at
 * @property string $opening_cash_balance
 * @property string $total_cash_sales
 * @property string $total_credit_sales
 * @property string $total_payments_collected
 * @property string $total_expenses
 * @property string $total_supplier_paid
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
// BUG-03: bound explicitly - auto-discovery looks for CashShiftPolicy, so ShiftPolicy was never
// consulted and every non-admin got 404 on the Z report and on closing a shift by id.
#[UsePolicy(ShiftPolicy::class)]
class CashShift extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'store_id',
        'shift_number',
        'status',
        'shift_mode',
        'open_guard',
        'opened_at',
        'closed_at',
        'opening_cash_balance',
        'total_cash_sales',
        'total_credit_sales',
        'total_payments_collected',
        'total_expenses',
        'total_supplier_paid',
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
        'total_expenses' => 'decimal:3',
        'total_supplier_paid' => 'decimal:3',
        'total_refunds' => 'decimal:3',
        'expected_cash_balance' => 'decimal:3',
        'actual_cash_balance' => 'decimal:3',
        'cash_difference' => 'decimal:3',
    ];

    /**
     * SHOP-06 / BE-19, SHIFT-01: `open_guard` (UNIQUE) is set by ShiftService only while a shift is
     * open (OpenShiftResolver::guardKey: the branch, or the branch and the cashier); it is cleared
     * whenever the shift stops being open (closed, or deleted while open), so the branch / cashier
     * can open the next shift.
     */
    protected static function booted(): void
    {
        static::saving(function (CashShift $shift): void {
            if ($shift->status !== 'open' && $shift->open_guard !== null) {
                $shift->open_guard = null;
            }
        });

        static::deleting(function (CashShift $shift): void {
            if ($shift->open_guard !== null) {
                $shift->open_guard = null;
                $shift->saveQuietly();
            }
        });
    }

    /** SHIFT-01: opened in a per-cashier branch (its own drawer; legacy rows are 'shared'). */
    public function isPerCashier(): bool
    {
        return $this->shift_mode === ShiftMode::PerCashier->value;
    }

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
