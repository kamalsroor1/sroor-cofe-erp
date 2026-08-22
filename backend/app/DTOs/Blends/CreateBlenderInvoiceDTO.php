<?php

declare(strict_types=1);

namespace App\DTOs\Blends;

final class CreateBlenderInvoiceDTO
{
    public function __construct(
        public readonly string $blend_name,
        public readonly int $customer_id,
        public readonly array $components,
        public readonly int $target_weight_grams = 250,
        public readonly string $roast_type = 'وسط',
        public readonly string $grind_level = 'تركي ناعم',
        public readonly float $cardamom_grams = 0,
        public readonly ?string $notes = null,
        public readonly ?int $store_id = null,
    ) {}

    public static function fromArray(array $data, ?int $storeId = null): self
    {
        return new self(
            blend_name: (string)$data['blend_name'],
            customer_id: (int)$data['customer_id'],
            components: (array)($data['components'] ?? []),
            target_weight_grams: (int)($data['target_weight_grams'] ?? 250),
            roast_type: (string)($data['roast_type'] ?? 'وسط'),
            grind_level: (string)($data['grind_level'] ?? 'تركي ناعم'),
            cardamom_grams: (float)($data['cardamom_grams'] ?? 0),
            notes: isset($data['notes']) && $data['notes'] !== '' ? (string)$data['notes'] : null,
            store_id: isset($data['store_id']) ? (int)$data['store_id'] : $storeId,
        );
    }
}
