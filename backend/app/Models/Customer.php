<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

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
            'is_active'       => 'boolean',
        ];
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class)->latest('invoice_date');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class)->latest('payment_date');
    }

    public function returns()
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

        if (bccomp((string)$this->current_balance, '0.000', 3) != 0) {
            $blockers[] = "يوجد رصيد / مديونية غير مسواة على العميل (" . number_format((float)$this->current_balance, 2) . " ج.م)";
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
