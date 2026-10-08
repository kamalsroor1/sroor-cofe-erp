<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $document_type
 * @property int $document_id
 * @property string $title
 * @property string $amount
 * @property string $allocation_method
 * @property string $paid_by
 * @property int|null $payment_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class AdditionalExpense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'document_type',
        'document_id',
        'title',
        'amount',
        'allocation_method', // by_quantity, by_value, equal
        'paid_by',           // supplier_account, treasury_cash, treasury_instapay, treasury_e_wallet
        'payment_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function document(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get Arabic label for allocation method
     */
    public function getAllocationMethodLabelAttribute(): string
    {
        return match ($this->allocation_method) {
            'by_quantity' => 'حسب الوزن / الكمية',
            'by_value' => 'حسب قيمة الصنف',
            'equal' => 'بالتساوي على البنود',
            default => 'حسب الكمية',
        };
    }

    /**
     * Get Arabic label for paid_by method
     */
    public function getPaidByLabelAttribute(): string
    {
        return match ($this->paid_by) {
            'customer_account' => 'مضاف على حساب العميل بالفاتورة',
            'supplier_account' => 'مضاف لحساب المورد بالفاتورة',
            'treasury_cash' => 'مدفوع كاش نقدًا من الخزينة',
            'treasury_instapay' => 'مدفوع عبر إنستاباي (سند صرف)',
            'treasury_e_wallet' => 'مدفوع من المحفظة الذكية (سند صرف)',
            default => 'مضاف لحساب الفاتورة',
        };
    }
}
