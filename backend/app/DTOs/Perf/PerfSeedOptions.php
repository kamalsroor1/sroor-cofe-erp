<?php

declare(strict_types=1);

namespace App\DTOs\Perf;

/**
 * PERF-04: the shape of a `tenant:seed-perf` dataset. Every count is exact; the same options
 * always produce the same rows (the generator is arithmetic, no random source).
 */
final class PerfSeedOptions
{
    public const MAX_INVOICES = 1_000_000;

    public const MAX_ITEMS = 100_000;

    public const MAX_STORES = 50;

    public const MAX_DAYS = 3_650;

    public function __construct(
        public readonly int $invoices,
        public readonly int $items,
        public readonly int $stores,
        public readonly int $days,
        public readonly int $customers,
        public readonly int $suppliers,
    ) {}

    /**
     * @param  array{invoices?: int|string|null, items?: int|string|null, stores?: int|string|null, days?: int|string|null, customers?: int|string|null, suppliers?: int|string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        $invoices = self::int($data['invoices'] ?? null, 5_000);
        $customers = self::int($data['customers'] ?? null, max(20, min(2_000, intdiv($invoices, 25))));

        return new self(
            invoices: $invoices,
            items: self::int($data['items'] ?? null, 500),
            stores: self::int($data['stores'] ?? null, 3),
            days: self::int($data['days'] ?? null, 365),
            customers: $customers,
            suppliers: self::int($data['suppliers'] ?? null, 20),
        );
    }

    /**
     * Validation problems as translation keys with their parameters (empty when valid).
     *
     * @return list<array{key: string, params: array<string, int|string>}>
     */
    public function problems(): array
    {
        $problems = [];
        $ranges = [
            'invoices' => [0, self::MAX_INVOICES, $this->invoices],
            'items' => [1, self::MAX_ITEMS, $this->items],
            'stores' => [1, self::MAX_STORES, $this->stores],
            'days' => [1, self::MAX_DAYS, $this->days],
            'customers' => [1, 100_000, $this->customers],
            'suppliers' => [1, 10_000, $this->suppliers],
        ];

        foreach ($ranges as $option => [$min, $max, $value]) {
            if ($value < $min || $value > $max) {
                $problems[] = ['key' => 'console.perf_seed.invalid_option', 'params' => ['option' => $option, 'min' => $min, 'max' => $max]];
            }
        }

        return $problems;
    }

    /**
     * @return array{invoices: int, items: int, stores: int, days: int, customers: int, suppliers: int}
     */
    public function toArray(): array
    {
        return [
            'invoices' => $this->invoices,
            'items' => $this->items,
            'stores' => $this->stores,
            'days' => $this->days,
            'customers' => $this->customers,
            'suppliers' => $this->suppliers,
        ];
    }

    private static function int(int|string|null $value, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return is_int($value) ? $value : (ctype_digit($value) ? (int) $value : -1);
    }
}
