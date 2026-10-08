<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use App\Http\Requests\Pos\Concerns\ResolvesTargetStore;
use App\Models\StorePosSetting;
use App\Services\Pos\ScaleBarcodeConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POSB-2: PUT /api/v1/stores/{store}/pos-settings — partial update (only sent keys change).
 * settings.manage + access to {store}.
 */
class UpdateStorePosSettingsRequest extends FormRequest
{
    use ResolvesTargetStore;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', [StorePosSetting::class, $this->targetStore()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scale_barcode_enabled' => ['sometimes', 'boolean'],
            'scale_prefixes' => ['sometimes', 'array', 'min:1', 'max:20'],
            // Strings, not integers: leading zeros are significant ("02").
            'scale_prefixes.*' => ['required', 'string', 'regex:/^\d{1,3}$/', 'distinct'],
            'scale_plu_length' => ['sometimes', 'integer', 'between:4,6'],
            'scale_value_type' => ['sometimes', 'string', Rule::in(ScaleBarcodeConfig::VALUE_TYPES)],
            'scale_value_length' => ['sometimes', 'integer', 'between:4,6'],
            'scale_weight_divisor' => ['sometimes', 'integer', Rule::in(ScaleBarcodeConfig::DIVISORS)],
            'scale_price_divisor' => ['sometimes', 'integer', Rule::in(ScaleBarcodeConfig::DIVISORS)],
            'scale_check_digit' => ['sometimes', 'boolean'],
            'max_discount_percent' => ['sometimes', 'nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:100'],
        ];
    }
}
