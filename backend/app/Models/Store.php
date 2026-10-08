<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'type', // retail_shop, wholesale_van, main_warehouse
        'phone',
        'address',
        'is_active',
        'is_main',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_main' => 'boolean',
    ];

    /**
     * Scope for active stores
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for wholesale distribution vans
     */
    public function scopeVans($query)
    {
        return $query->where('type', 'wholesale_van');
    }

    /**
     * Scope for retail shops
     */
    public function scopeShops($query)
    {
        return $query->where('type', 'retail_shop');
    }

    /**
     * Get the main store/warehouse
     */
    public static function getMainStore(): ?self
    {
        return static::where('is_main', true)->first() ?? static::first();
    }

    /**
     * Store Inventory Stocks
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(StoreStock::class);
    }

    /**
     * Assigned Users / Cashiers / Drivers
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_user')->withTimestamps();
    }

    /**
     * Invoices issued from this store
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Purchases received at this store
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * Expenses recorded in this store
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Cash shifts in this store
     */
    public function cashShifts(): HasMany
    {
        return $this->hasMany(CashShift::class);
    }

    /**
     * Transfers sent from this store
     */
    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'from_store_id');
    }

    /**
     * Transfers received at this store
     */
    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'to_store_id');
    }

    /**
     * Determine if store can be safely deleted or if operational records prevent it
     */
    public function canBeDeleted(): bool
    {
        return empty($this->getDeletionBlockers());
    }

    /**
     * Get list of reasons preventing deletion of this store
     */
    public function getDeletionBlockers(): array
    {
        $blockers = [];

        if ($this->is_main) {
            $blockers[] = 'هو الفرع والمخزن الرئيسي للمنشأة';
        }

        $hasStock = $this->stocks()->where('quantity', '>', 0)->exists();
        if ($hasStock) {
            $blockers[] = 'يوجد رصيد بضاعة مخزني حالي داخل هذا الفرع';
        }

        $invoicesCount = $this->invoices()->count();
        if ($invoicesCount > 0) {
            $blockers[] = "مسجل به {$invoicesCount} فاتورة مبيعات";
        }

        $purchasesCount = $this->purchases()->count();
        if ($purchasesCount > 0) {
            $blockers[] = "مسجل به {$purchasesCount} فاتورة مشتريات وتوريد";
        }

        $transfersCount = $this->outgoingTransfers()->count() + $this->incomingTransfers()->count();
        if ($transfersCount > 0) {
            $blockers[] = "مسجل به {$transfersCount} إذن تحويل وشحن بضاعة";
        }

        $expensesCount = $this->expenses()->count();
        if ($expensesCount > 0) {
            $blockers[] = "مسجل به {$expensesCount} سند مصروفات";
        }

        return $blockers;
    }
}
