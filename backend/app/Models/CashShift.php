<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }
}
