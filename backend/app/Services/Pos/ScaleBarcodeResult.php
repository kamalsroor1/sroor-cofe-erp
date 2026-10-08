<?php

declare(strict_types=1);

namespace App\Services\Pos;

/**
 * POSB-2: a decoded scale label. Exactly one of quantity (weight label, kg) or price
 * (price label) is set; both are numeric strings with scale 3.
 */
final class ScaleBarcodeResult
{
    public function __construct(
        public readonly string $prefix,
        public readonly string $plu,
        public readonly string $valueType,
        public readonly ?string $quantity,
        public readonly ?string $price,
    ) {}

    /**
     * @return array{prefix: string, plu: string, value_type: string, quantity: string|null, price: string|null}
     */
    public function toArray(): array
    {
        return [
            'prefix' => $this->prefix,
            'plu' => $this->plu,
            'value_type' => $this->valueType,
            'quantity' => $this->quantity,
            'price' => $this->price,
        ];
    }
}
