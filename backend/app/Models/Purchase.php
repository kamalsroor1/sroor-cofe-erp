<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $purchase_number
 * @property int $supplier_id
 * @property int|null $user_id
 * @property Carbon|null $purchase_date
 * @property string $status
 * @property string $payment_status
 * @property string $subtotal
 * @property string $discount_amount
 * @property string $net_total
 * @property string $paid_amount
 * @property string $remaining_amount
 * @property string|null $supplier_invoice_ref
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $store_id
 * @property Carbon|null $deleted_at
 * @property string $additional_expenses_total
 */
class Purchase extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'purchase_number',
        'supplier_id',
        'user_id',
        'store_id',
        'purchase_date',
        'status',
        'payment_status',
        'subtotal',
        'discount_amount',
        'additional_expenses_total',
        'net_total',
        'paid_amount',
        'remaining_amount',
        'supplier_invoice_ref',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'subtotal' => 'decimal:3',
            'discount_amount' => 'decimal:3',
            'additional_expenses_total' => 'decimal:3',
            'net_total' => 'decimal:3',
            'paid_amount' => 'decimal:3',
            'remaining_amount' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
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
     * @return HasMany<PurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'purchase_id');
    }

    /**
     * @return MorphMany<AdditionalExpense, $this>
     */
    public function additionalExpenses(): MorphMany
    {
        return $this->morphMany(AdditionalExpense::class, 'document');
    }

    /**
     * @return MorphMany<StockMovement, $this>
     */
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }
}
