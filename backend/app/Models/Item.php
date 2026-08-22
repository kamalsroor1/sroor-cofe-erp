<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'category',
        'category_id',
        'unit',
        'current_stock',
        'cost_price',
        'min_selling_price',
        'weighted_avg_cost',
        'selling_price',
        'min_stock_level',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'current_stock'     => 'decimal:3',
            'cost_price'        => 'decimal:3',
            'min_selling_price' => 'decimal:3',
            'weighted_avg_cost' => 'decimal:3',
            'selling_price'     => 'decimal:3',
            'min_stock_level'   => 'decimal:3',
            'is_active'         => 'boolean',
        ];
    }

    public function categoryRel()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class)->latest();
    }

    public function stockDeposits()
    {
        return $this->hasMany(StockDeposit::class);
    }

    public function returnItems()
    {
        return $this->hasMany(ReturnItem::class);
    }

    public function storeStocks()
    {
        return $this->hasMany(StoreStock::class);
    }

    public function getStockInStore(?int $storeId): string
    {
        if (!$storeId) {
            return (string)$this->current_stock;
        }

        $stock = $this->relationLoaded('storeStocks')
            ? $this->storeStocks->firstWhere('store_id', $storeId)
            : $this->storeStocks()->where('store_id', $storeId)->first();

        return $stock ? (string)$stock->quantity : '0.000';
    }

    public function getEffectivePriceForStore(?int $storeId): string
    {
        if (!$storeId) {
            return (string)$this->selling_price;
        }

        $stock = $this->relationLoaded('storeStocks')
            ? $this->storeStocks->firstWhere('store_id', $storeId)
            : $this->storeStocks()->where('store_id', $storeId)->first();

        if ($stock && $stock->custom_selling_price !== null && bccomp((string)$stock->custom_selling_price, '0.000', 3) > 0) {
            return (string)$stock->custom_selling_price;
        }

        return (string)$this->selling_price;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('current_stock', '<=', 'min_stock_level')
                     ->where('is_active', true);
    }

    public function isLowStock(): bool
    {
        return bccomp($this->current_stock, $this->min_stock_level, 3) <= 0;
    }

    /**
     * Determine if item can be safely deleted or if financial/inventory history prevents it
     */
    public function canBeDeleted(): bool
    {
        return empty($this->getDeletionBlockers());
    }

    /**
     * Get list of reasons preventing deletion of this item
     */
    public function getDeletionBlockers(): array
    {
        $blockers = [];

        if (bccomp((string)$this->current_stock, '0.000', 3) > 0) {
            $blockers[] = "يوجد رصيد بضاعة متبقي بالمخزن (" . number_format((float)$this->current_stock, 3) . " {$this->unit})";
        }

        $hasStoreStock = $this->storeStocks()->where('quantity', '>', 0)->exists();
        if ($hasStoreStock) {
            $blockers[] = "يوجد رصيد متوفر في أحد الفروع أو عربات التوزيع";
        }

        $invoicesCount = $this->invoiceItems()->count();
        if ($invoicesCount > 0) {
            $blockers[] = "مسجل به {$invoicesCount} فاتورة مبيعات معتمدة";
        }

        $purchasesCount = $this->purchaseItems()->count();
        if ($purchasesCount > 0) {
            $blockers[] = "مسجل به {$purchasesCount} فاتورة مشتريات وتوريد";
        }

        $movementsCount = $this->stockMovements()->count();
        if ($movementsCount > 0) {
            $blockers[] = "يوجد له {$movementsCount} حركة مخزنية مسجلة";
        }

        $returnsCount = $this->returnItems()->count();
        if ($returnsCount > 0) {
            $blockers[] = "مرتبط بـ {$returnsCount} حركة مرتجعات";
        }

        $transfersCount = \App\Models\StockTransferItem::where('item_id', $this->id)->count();
        if ($transfersCount > 0) {
            $blockers[] = "مرتبط بـ {$transfersCount} إذن تحويل بين الفروع";
        }

        return $blockers;
    }
}
