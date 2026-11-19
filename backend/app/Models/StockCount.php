<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockCountStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * SHOP-02: a stocktake session of one store.
 *
 * @property int $id
 * @property string $count_number
 * @property int $store_id
 * @property int|null $open_store_id
 * @property int|null $category_id
 * @property StockCountStatus $status
 * @property bool $is_blind
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $posted_by
 * @property Carbon|null $posted_at
 * @property int|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StockCount extends Model
{
    protected $fillable = [
        'count_number',
        'store_id',
        'open_store_id',
        'category_id',
        'status',
        'is_blind',
        'notes',
        'created_by',
        'posted_by',
        'posted_at',
        'cancelled_by',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => StockCountStatus::class,
            'is_blind' => 'boolean',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /**
     * @return HasMany<StockCountLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by')->withTrashed();
    }
}
