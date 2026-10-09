<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $invoice_number
 * @property string|null $client_uuid
 * @property int $customer_id
 * @property int|null $user_id
 * @property int|null $store_id
 * @property Carbon|null $invoice_date
 * @property string $payment_type
 * @property string|null $payment_method
 * @property string $status
 * @property string $payment_status
 * @property string $subtotal
 * @property string $discount_type
 * @property string $discount_value
 * @property string $discount_amount
 * @property string $shipping_cost
 * @property string $net_total
 * @property string $paid_amount
 * @property string $remaining_amount
 * @property string $change_amount
 * @property string $total_cost
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'client_uuid',
        'customer_id',
        'user_id',
        'store_id',
        'invoice_date',
        'payment_type',
        'payment_method',
        'status',
        'payment_status',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount_amount',
        'shipping_cost',
        'net_total',
        'paid_amount',
        'remaining_amount',
        'change_amount',
        'total_cost',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'subtotal' => 'decimal:3',
            'discount_value' => 'decimal:3',
            'discount_amount' => 'decimal:3',
            'shipping_cost' => 'decimal:3',
            'net_total' => 'decimal:3',
            'paid_amount' => 'decimal:3',
            'remaining_amount' => 'decimal:3',
            'change_amount' => 'decimal:3',
            'total_cost' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
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

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return MorphMany<AdditionalExpense, $this>
     */
    public function additionalExpenses(): MorphMany
    {
        return $this->morphMany(AdditionalExpense::class, 'document');
    }

    /**
     * @return HasMany<ReturnDocument, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(ReturnDocument::class, 'invoice_id');
    }

    /**
     * @return MorphMany<StockMovement, $this>
     */
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    public function scopePendingDebts(Builder $query): Builder
    {
        return $query->where('status', 'confirmed')
            ->whereIn('payment_status', ['unpaid', 'partially_paid']);
    }

    public function getProfitAttribute(): string
    {
        return bcsub($this->net_total, $this->total_cost, 3);
    }

    public function getProfitMarginPercentageAttribute(): string
    {
        if (bccomp($this->net_total, '0.000', 3) <= 0) {
            return '0.0';
        }

        return bcmul(bcdiv($this->profit, $this->net_total, 4), '100', 1);
    }
}
