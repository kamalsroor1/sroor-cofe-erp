<?php

namespace App\Models;

use App\Services\Settings\TenantSettings;
use App\Support\WeightUnits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property bool $is_weighted POSB-2: sold by weight (scale labels, fractional qty); explicit, not inferred from the unit
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $category
 * @property string $unit
 * @property string $current_stock
 * @property string $cost_price
 * @property string $weighted_avg_cost
 * @property string $selling_price
 * @property string $min_stock_level
 * @property bool $is_active
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string $min_selling_price floor: 0 = no floor
 * @property string|null $wholesale_price BE-08: NULL/0 = not set (wholesale customers pay retail)
 * @property int|null $category_id
 * @property string|null $image
 * @property int $pos_sort_order
 * @property bool $is_pos_pinned
 * @property int $pos_sales_count
 */
class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'image',
        'category',
        'category_id',
        'unit',
        'current_stock',
        'cost_price',
        'min_selling_price',
        'wholesale_price',
        'weighted_avg_cost',
        'selling_price',
        'min_stock_level',
        'is_active',
        'notes',
        'pos_sort_order',
        'is_pos_pinned',
        'pos_sales_count',
        'is_weighted',
    ];

    protected $appends = [
        'price_retail',
        'price_wholesale',
    ];

    public function getPriceRetailAttribute(): string
    {
        return (string) ($this->selling_price ?? '0.000');
    }

    /**
     * BE-08: the wholesale tier price. The dedicated `wholesale_price` column; NULL/0 = not set, so it
     * falls back to retail. It is never the minimum selling price (that is only the floor).
     */
    public function getPriceWholesaleAttribute(): string
    {
        $wholesale = (string) ($this->getAttribute('wholesale_price') ?? '0.000');

        return bccomp($wholesale, '0', 3) > 0 ? $wholesale : (string) ($this->selling_price ?? '0.000');
    }

    protected function casts(): array
    {
        return [
            'current_stock' => 'decimal:3',
            'cost_price' => 'decimal:3',
            'min_selling_price' => 'decimal:3',
            'wholesale_price' => 'decimal:3',
            'weighted_avg_cost' => 'decimal:3',
            'selling_price' => 'decimal:3',
            'min_stock_level' => 'decimal:3',
            'is_active' => 'boolean',
            'pos_sort_order' => 'integer',
            'is_pos_pinned' => 'boolean',
            'pos_sales_count' => 'integer',
            'is_weighted' => 'boolean',
        ];
    }

    /**
     * Sold by weight: the explicit flag, or a weight unit (kg, g, ...) on items created before the flag existed.
     */
    public function isSoldByWeight(): bool
    {
        return (bool) $this->is_weighted || WeightUnits::matches($this->unit);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function categoryRel(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest();
    }

    /**
     * @return HasMany<StockDeposit, $this>
     */
    public function stockDeposits(): HasMany
    {
        return $this->hasMany(StockDeposit::class);
    }

    /**
     * @return HasMany<ReturnItem, $this>
     */
    public function returnItems(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }

    /**
     * @return HasMany<StoreStock, $this>
     */
    public function storeStocks(): HasMany
    {
        return $this->hasMany(StoreStock::class);
    }

    /**
     * @return HasMany<StockTransferItem, $this>
     */
    public function stockTransferItems(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    /**
     * PERF-02: preload everything getDeletionBlockers() needs in the page query itself (one
     * correlated COUNT / EXISTS per blocker), so a list never runs those queries per row. Counts
     * go through the relations, so they apply the same soft-delete scopes as the per-row path.
     *
     * @param  Builder<Item>  $query
     * @return Builder<Item>
     */
    public function scopeWithDeletionBlockers(Builder $query): Builder
    {
        return $query
            ->withCount(['invoiceItems', 'purchaseItems', 'stockMovements', 'returnItems', 'stockTransferItems'])
            ->withExists(['storeStocks as has_positive_store_stock' => fn (Builder $q) => $q->where('quantity', '>', 0)]);
    }

    public function getStockInStore(?int $storeId): string
    {
        if (! $storeId) {
            return (string) $this->current_stock;
        }

        $stock = $this->relationLoaded('storeStocks')
            ? $this->storeStocks->firstWhere('store_id', $storeId)
            : $this->storeStocks()->where('store_id', $storeId)->first();

        return $stock ? (string) $stock->quantity : '0.000';
    }

    public function getEffectivePriceForStore(?int $storeId): string
    {
        if (! $storeId) {
            return (string) $this->selling_price;
        }

        $stock = $this->relationLoaded('storeStocks')
            ? $this->storeStocks->firstWhere('store_id', $storeId)
            : $this->storeStocks()->where('store_id', $storeId)->first();

        if ($stock && $stock->custom_selling_price !== null && bccomp((string) $stock->custom_selling_price, '0.000', 3) > 0) {
            return (string) $stock->custom_selling_price;
        }

        return (string) $this->selling_price;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * SETG-10: active items at or below their own minimum, or — when they have none
     * (NULL or <= 0) — at or below the tenant's low_stock_default_threshold.
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return app(TenantSettings::class)->whereLowStock($query)
            ->where('is_active', true);
    }

    /**
     * Same rule as scopeLowStock() / TenantSettings::whereLowStock(), for one row.
     */
    public function isLowStock(?string $defaultThreshold = null): bool
    {
        $min = (string) $this->min_stock_level;
        $threshold = bccomp($min, '0', 3) > 0
            ? $min
            : ($defaultThreshold ?? app(TenantSettings::class)->lowStockDefaultThreshold());

        return bccomp((string) $this->current_stock, $threshold, 3) <= 0;
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

        if (bccomp((string) $this->current_stock, '0.000', 3) > 0) {
            $blockers[] = 'يوجد رصيد بضاعة متبقي بالمخزن ('.number_format((float) $this->current_stock, 3)." {$this->unit})";
        }

        // PERF-02: the values preloaded by scopeWithDeletionBlockers() when present, else one query each.
        $hasStoreStock = array_key_exists('has_positive_store_stock', $this->attributes)
            ? (bool) $this->attributes['has_positive_store_stock']
            : $this->storeStocks()->where('quantity', '>', 0)->exists();
        if ($hasStoreStock) {
            $blockers[] = 'يوجد رصيد متوفر في أحد الفروع أو عربات التوزيع';
        }

        $invoicesCount = $this->preloadedCount('invoice_items_count') ?? $this->invoiceItems()->count();
        if ($invoicesCount > 0) {
            $blockers[] = "مسجل به {$invoicesCount} فاتورة مبيعات معتمدة";
        }

        $purchasesCount = $this->preloadedCount('purchase_items_count') ?? $this->purchaseItems()->count();
        if ($purchasesCount > 0) {
            $blockers[] = "مسجل به {$purchasesCount} فاتورة مشتريات وتوريد";
        }

        $movementsCount = $this->preloadedCount('stock_movements_count') ?? $this->stockMovements()->count();
        if ($movementsCount > 0) {
            $blockers[] = "يوجد له {$movementsCount} حركة مخزنية مسجلة";
        }

        $returnsCount = $this->preloadedCount('return_items_count') ?? $this->returnItems()->count();
        if ($returnsCount > 0) {
            $blockers[] = "مرتبط بـ {$returnsCount} حركة مرتجعات";
        }

        $transfersCount = $this->preloadedCount('stock_transfer_items_count') ?? $this->stockTransferItems()->count();
        if ($transfersCount > 0) {
            $blockers[] = "مرتبط بـ {$transfersCount} إذن تحويل بين الفروع";
        }

        return $blockers;
    }

    /**
     * A withCount() column of this row, or null when the query did not preload it.
     */
    private function preloadedCount(string $attribute): ?int
    {
        return array_key_exists($attribute, $this->attributes) ? (int) $this->attributes[$attribute] : null;
    }
}
