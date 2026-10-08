<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'company_name',
        'phone',
        'address',
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

    public function purchases()
    {
        return $this->hasMany(Purchase::class)->latest('purchase_date');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'supplier_id')->latest('payment_date');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Determine if supplier can be safely deleted or if financial history prevents it
     */
    public function canBeDeleted(): bool
    {
        return empty($this->getDeletionBlockers());
    }

    /**
     * Get list of reasons preventing deletion of this supplier
     */
    public function getDeletionBlockers(): array
    {
        $blockers = [];

        if (bccomp((string) $this->current_balance, '0.000', 3) != 0) {
            $blockers[] = 'يوجد رصيد مستحق للمورد ('.number_format((float) $this->current_balance, 2).' ج.م)';
        }

        $purchasesCount = $this->purchases()->count();
        if ($purchasesCount > 0) {
            $blockers[] = "مسجل له {$purchasesCount} فاتورة مشتريات وتوريد";
        }

        $paymentsCount = $this->payments()->count();
        if ($paymentsCount > 0) {
            $blockers[] = "مسجل له {$paymentsCount} سند صرف";
        }

        return $blockers;
    }
}
