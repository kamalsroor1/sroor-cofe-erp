<?php

declare(strict_types=1);

namespace App\Services\Pos;

/**
 * POSB-2: immutable scale-label parser settings of one store. Keys mirror the
 * `store_pos_settings` columns (and the shared vectors file) so the same array can be
 * passed around the backend, the API and the Phase 2 frontend parser.
 */
final class ScaleBarcodeConfig
{
    public const VALUE_TYPE_WEIGHT = 'weight';

    public const VALUE_TYPE_PRICE = 'price';

    public const VALUE_TYPES = [self::VALUE_TYPE_WEIGHT, self::VALUE_TYPE_PRICE];

    /** Divisors are powers of ten so bcdiv() at scale 3 is always exact. */
    public const DIVISORS = [1, 10, 100, 1000, 10000];

    public const DEFAULT_PREFIXES = ['20', '21', '22', '23', '24', '25', '26', '27', '28', '29'];

    /**
     * @param  list<string>  $prefixes
     */
    public function __construct(
        public readonly bool $enabled = false,
        public readonly array $prefixes = self::DEFAULT_PREFIXES,
        public readonly int $pluLength = 5,
        public readonly string $valueType = self::VALUE_TYPE_WEIGHT,
        public readonly int $valueLength = 5,
        public readonly int $weightDivisor = 1000,
        public readonly int $priceDivisor = 100,
        public readonly bool $checkDigit = true,
    ) {}

    public static function defaults(): self
    {
        return new self;
    }

    /**
     * @param  array<string, mixed>  $data  keys as in toArray(); missing keys keep defaults
     */
    public static function fromArray(array $data): self
    {
        $defaults = self::defaults();

        $prefixes = $data['scale_prefixes'] ?? null;
        $prefixes = is_array($prefixes)
            ? array_values(array_map(static fn ($p): string => (string) $p, $prefixes))
            : $defaults->prefixes;

        return new self(
            enabled: (bool) ($data['scale_barcode_enabled'] ?? $defaults->enabled),
            prefixes: $prefixes,
            pluLength: (int) ($data['scale_plu_length'] ?? $defaults->pluLength),
            valueType: (string) ($data['scale_value_type'] ?? $defaults->valueType),
            valueLength: (int) ($data['scale_value_length'] ?? $defaults->valueLength),
            weightDivisor: (int) ($data['scale_weight_divisor'] ?? $defaults->weightDivisor),
            priceDivisor: (int) ($data['scale_price_divisor'] ?? $defaults->priceDivisor),
            checkDigit: (bool) ($data['scale_check_digit'] ?? $defaults->checkDigit),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides): self
    {
        return self::fromArray(array_merge($this->toArray(), $overrides));
    }

    /**
     * @return array{scale_barcode_enabled: bool, scale_prefixes: list<string>, scale_plu_length: int, scale_value_type: string, scale_value_length: int, scale_weight_divisor: int, scale_price_divisor: int, scale_check_digit: bool}
     */
    public function toArray(): array
    {
        return [
            'scale_barcode_enabled' => $this->enabled,
            'scale_prefixes' => $this->prefixes,
            'scale_plu_length' => $this->pluLength,
            'scale_value_type' => $this->valueType,
            'scale_value_length' => $this->valueLength,
            'scale_weight_divisor' => $this->weightDivisor,
            'scale_price_divisor' => $this->priceDivisor,
            'scale_check_digit' => $this->checkDigit,
        ];
    }
}
