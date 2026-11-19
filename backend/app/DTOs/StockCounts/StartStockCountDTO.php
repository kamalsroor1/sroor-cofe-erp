<?php

declare(strict_types=1);

namespace App\DTOs\StockCounts;

/**
 * SHOP-02: start a stocktake session in one store (the active store), optionally limited to a category.
 */
final readonly class StartStockCountDTO
{
    public function __construct(
        public int $storeId,
        public ?int $categoryId,
        public bool $isBlind,
        public ?string $notes,
        public ?int $userId,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            storeId: (int) $data['store_id'],
            categoryId: isset($data['category_id']) && $data['category_id'] !== '' ? (int) $data['category_id'] : null,
            isBlind: (bool) ($data['is_blind'] ?? false),
            notes: isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'store_id' => $this->storeId,
            'category_id' => $this->categoryId,
            'is_blind' => $this->isBlind,
            'notes' => $this->notes,
            'user_id' => $this->userId,
        ];
    }
}
