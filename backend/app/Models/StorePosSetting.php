<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Pos\ScaleBarcodeConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * POSB-2 (tenant DB): POS settings of one store — scale-label parser + max discount percent.
 * Not soft-deleted: it is configuration owned by the store row (cascade on store delete).
 *
 * @property int $id
 * @property int $store_id
 * @property bool $scale_barcode_enabled
 * @property list<string>|null $scale_prefixes
 * @property int $scale_plu_length
 * @property string $scale_value_type
 * @property int $scale_value_length
 * @property int $scale_weight_divisor
 * @property int $scale_price_divisor
 * @property bool $scale_check_digit
 * @property string|null $max_discount_percent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Store $store
 */
class StorePosSetting extends Model
{
    protected $fillable = [
        'store_id',
        'scale_barcode_enabled',
        'scale_prefixes',
        'scale_plu_length',
        'scale_value_type',
        'scale_value_length',
        'scale_weight_divisor',
        'scale_price_divisor',
        'scale_check_digit',
        'max_discount_percent',
    ];

    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'scale_barcode_enabled' => 'boolean',
            'scale_prefixes' => 'array',
            'scale_plu_length' => 'integer',
            'scale_value_length' => 'integer',
            'scale_weight_divisor' => 'integer',
            'scale_price_divisor' => 'integer',
            'scale_check_digit' => 'boolean',
            'max_discount_percent' => 'decimal:3',
        ];
    }

    /**
     * Unsaved instance carrying the defaults, for stores that never saved settings.
     */
    public static function defaultsFor(int $storeId): self
    {
        $setting = new self;
        $setting->forceFill(array_merge(
            ScaleBarcodeConfig::defaults()->toArray(),
            ['store_id' => $storeId, 'max_discount_percent' => null],
        ));

        return $setting;
    }

    /**
     * The store's settings, or the defaults (not persisted) when it has none.
     */
    public static function forStore(int $storeId): self
    {
        return self::query()->where('store_id', $storeId)->first() ?? self::defaultsFor($storeId);
    }

    public function scaleConfig(): ScaleBarcodeConfig
    {
        return ScaleBarcodeConfig::fromArray([
            'scale_barcode_enabled' => $this->scale_barcode_enabled,
            'scale_prefixes' => $this->scale_prefixes ?? ScaleBarcodeConfig::DEFAULT_PREFIXES,
            'scale_plu_length' => $this->scale_plu_length,
            'scale_value_type' => $this->scale_value_type,
            'scale_value_length' => $this->scale_value_length,
            'scale_weight_divisor' => $this->scale_weight_divisor,
            'scale_price_divisor' => $this->scale_price_divisor,
            'scale_check_digit' => $this->scale_check_digit,
        ]);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
