<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PosQuickKeyColor;
use App\Enums\PosQuickKeyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * POSB-6 (tenant DB): one quick key on a store's POS grid (page 1-5, position 0-59).
 * Not soft-deleted: the whole layout of a store is replaced on every save.
 *
 * @property int $id
 * @property int $store_id
 * @property int $page
 * @property int $position
 * @property PosQuickKeyType $type
 * @property int|null $item_id
 * @property int|null $category_id
 * @property string|null $label
 * @property PosQuickKeyColor|null $color
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Store $store
 * @property-read Item|null $item
 * @property-read Category|null $category
 */
class PosQuickKey extends Model
{
    public const MAX_PAGES = 5;

    public const MAX_POSITION = 59;

    public const MAX_KEYS = 300;

    protected $fillable = [
        'store_id',
        'page',
        'position',
        'type',
        'item_id',
        'category_id',
        'label',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'page' => 'integer',
            'position' => 'integer',
            'type' => PosQuickKeyType::class,
            'item_id' => 'integer',
            'category_id' => 'integer',
            'color' => PosQuickKeyColor::class,
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
