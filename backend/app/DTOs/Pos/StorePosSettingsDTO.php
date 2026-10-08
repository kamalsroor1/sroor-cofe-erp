<?php

declare(strict_types=1);

namespace App\DTOs\Pos;

/**
 * POSB-2: validated partial update of a store's POS settings. Only keys present in the
 * request are applied (`provided`), so `max_discount_percent: null` (clear the limit) is
 * distinguishable from "not sent".
 */
final class StorePosSettingsDTO
{
    private const KEYS = [
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

    /**
     * @param  list<string>|null  $scalePrefixes
     * @param  list<string>  $provided
     */
    public function __construct(
        public readonly ?bool $scaleBarcodeEnabled = null,
        public readonly ?array $scalePrefixes = null,
        public readonly ?int $scalePluLength = null,
        public readonly ?string $scaleValueType = null,
        public readonly ?int $scaleValueLength = null,
        public readonly ?int $scaleWeightDivisor = null,
        public readonly ?int $scalePriceDivisor = null,
        public readonly ?bool $scaleCheckDigit = null,
        public readonly ?string $maxDiscountPercent = null,
        public readonly array $provided = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data  FormRequest::validated()
     */
    public static function fromArray(array $data): self
    {
        $provided = array_values(array_filter(self::KEYS, static fn (string $key): bool => array_key_exists($key, $data)));

        $prefixes = isset($data['scale_prefixes']) && is_array($data['scale_prefixes'])
            ? array_values(array_map(static fn ($p): string => (string) $p, $data['scale_prefixes']))
            : null;

        $maxDiscount = $data['max_discount_percent'] ?? null;
        $maxDiscount = $maxDiscount === null || $maxDiscount === ''
            ? null
            : bcadd((string) $maxDiscount, '0', 3);

        return new self(
            scaleBarcodeEnabled: isset($data['scale_barcode_enabled']) ? (bool) $data['scale_barcode_enabled'] : null,
            scalePrefixes: $prefixes,
            scalePluLength: isset($data['scale_plu_length']) ? (int) $data['scale_plu_length'] : null,
            scaleValueType: isset($data['scale_value_type']) ? (string) $data['scale_value_type'] : null,
            scaleValueLength: isset($data['scale_value_length']) ? (int) $data['scale_value_length'] : null,
            scaleWeightDivisor: isset($data['scale_weight_divisor']) ? (int) $data['scale_weight_divisor'] : null,
            scalePriceDivisor: isset($data['scale_price_divisor']) ? (int) $data['scale_price_divisor'] : null,
            scaleCheckDigit: isset($data['scale_check_digit']) ? (bool) $data['scale_check_digit'] : null,
            maxDiscountPercent: $maxDiscount,
            provided: $provided,
        );
    }

    /**
     * Column => value for the provided keys only.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $all = [
            'scale_barcode_enabled' => $this->scaleBarcodeEnabled,
            'scale_prefixes' => $this->scalePrefixes,
            'scale_plu_length' => $this->scalePluLength,
            'scale_value_type' => $this->scaleValueType,
            'scale_value_length' => $this->scaleValueLength,
            'scale_weight_divisor' => $this->scaleWeightDivisor,
            'scale_price_divisor' => $this->scalePriceDivisor,
            'scale_check_digit' => $this->scaleCheckDigit,
            'max_discount_percent' => $this->maxDiscountPercent,
        ];

        return array_intersect_key($all, array_flip($this->provided));
    }
}
