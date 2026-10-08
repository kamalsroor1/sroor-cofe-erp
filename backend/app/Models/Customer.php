<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $tax_number
 * @property string $price_tier
 * @property string $current_balance
 * @property bool $is_active
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'address',
        'tax_number',
        'price_tier',
        'current_balance',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'current_balance' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('invoice_date');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('payment_date');
    }

    /**
     * @return HasMany<ReturnDocument, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(ReturnDocument::class, 'customer_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Determine if customer can be safely deleted or if financial history prevents it
     */
    public function canBeDeleted(): bool
    {
        return empty($this->getDeletionBlockers());
    }

    /**
     * Get list of reasons preventing deletion of this customer
     */
    public function getDeletionBlockers(): array
    {
        $blockers = [];

        if (bccomp((string) $this->current_balance, '0.000', 3) != 0) {
            $blockers[] = 'يوجد رصيد / مديونية غير مسواة على العميل ('.number_format((float) $this->current_balance, 2).' ج.م)';
        }

        $invoicesCount = $this->invoices()->count();
        if ($invoicesCount > 0) {
            $blockers[] = "مسجل له {$invoicesCount} فاتورة مبيعات";
        }

        $paymentsCount = $this->payments()->count();
        if ($paymentsCount > 0) {
            $blockers[] = "مسجل له {$paymentsCount} سند قبض";
        }

        $returnsCount = $this->returns()->count();
        if ($returnsCount > 0) {
            $blockers[] = "مسجل له {$returnsCount} حركة مرتجع";
        }

        return $blockers;
    }
}
