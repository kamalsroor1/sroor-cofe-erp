<?php

declare(strict_types=1);

namespace App\DTOs\StockCounts;

/**
 * SHOP-02: post a session. `zeroUncounted` treats every line left uncounted as counted zero;
 * otherwise uncounted lines are left untouched.
 */
final readonly class PostStockCountDTO
{
    public function __construct(
        public int $stockCountId,
        public bool $zeroUncounted,
        public ?int $userId,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(int $stockCountId, array $data): self
    {
        return new self(
            stockCountId: $stockCountId,
            zeroUncounted: (bool) ($data['zero_uncounted'] ?? false),
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'stock_count_id' => $this->stockCountId,
            'zero_uncounted' => $this->zeroUncounted,
            'user_id' => $this->userId,
        ];
    }
}
