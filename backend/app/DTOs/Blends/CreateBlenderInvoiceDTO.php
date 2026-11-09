<?php

declare(strict_types=1);

namespace App\DTOs\Blends;

use App\Enums\BlendGrind;
use App\Enums\BlendRoast;

final class CreateBlenderInvoiceDTO
{
    public function __construct(
        public readonly string $blend_name,
        public readonly int $customer_id,
        public readonly array $components,
        public readonly int $target_weight_grams = 250,
        public readonly string $roast_type = 'medium',
        public readonly string $grind_level = 'turkish',
        public readonly float $cardamom_grams = 0,
        public readonly ?string $notes = null,
        public readonly ?int $store_id = null,
    ) {}

    public static function fromArray(array $data, ?int $storeId = null): self
    {
        return new self(
            blend_name: (string) $data['blend_name'],
            customer_id: (int) $data['customer_id'],
            components: (array) ($data['components'] ?? []),
            target_weight_grams: (int) ($data['target_weight_grams'] ?? 250),
            roast_type: self::normalize(BlendRoast::fromInput($data['roast_type'] ?? null), $data['roast_type'] ?? null, BlendRoast::Medium->value),
            grind_level: self::normalize(BlendGrind::fromInput($data['grind_level'] ?? null), $data['grind_level'] ?? null, BlendGrind::Turkish->value),
            cardamom_grams: (float) ($data['cardamom_grams'] ?? 0),
            notes: isset($data['notes']) && $data['notes'] !== '' ? (string) $data['notes'] : null,
            store_id: isset($data['store_id']) ? (int) $data['store_id'] : $storeId,
        );
    }

    /** Known codes and legacy Arabic labels become the code; other text is kept; empty uses the default. */
    private static function normalize(?\BackedEnum $known, mixed $raw, string $default): string
    {
        if ($known !== null) {
            return (string) $known->value;
        }

        $raw = trim((string) $raw);

        return $raw !== '' ? $raw : $default;
    }
}
