<?php

declare(strict_types=1);

namespace App\DTOs\StockCounts;

/**
 * SHOP-02: counted quantities of a session (draft save). A null quantity clears a count.
 */
final readonly class SaveStockCountLinesDTO
{
    /**
     * @param  list<array{item_id: int, counted_qty: string|null}>  $lines
     */
    public function __construct(
        public int $stockCountId,
        public array $lines,
        public ?int $userId,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(int $stockCountId, array $data): self
    {
        $lines = [];
        foreach ((array) ($data['lines'] ?? []) as $line) {
            $raw = $line['counted_qty'] ?? null;
            $lines[] = [
                'item_id' => (int) $line['item_id'],
                'counted_qty' => $raw === null || $raw === '' ? null : bcadd((string) $raw, '0', 3),
            ];
        }

        return new self(
            stockCountId: $stockCountId,
            lines: $lines,
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
            'lines' => $this->lines,
            'user_id' => $this->userId,
        ];
    }
}
