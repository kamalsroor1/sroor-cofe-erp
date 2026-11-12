<?php

declare(strict_types=1);

namespace App\DTOs\Customers;

use Carbon\CarbonImmutable;

/**
 * PERF-01: the period and page of a customer statement.
 *
 * Without from/to (and without period=all) the statement shows the last DEFAULT_DAYS days of
 * the tenant business calendar, with the balance before them carried forward. period=all is
 * the whole history (still paged).
 */
final class CustomerStatementFilterDTO
{
    public const DEFAULT_DAYS = 90;

    public const DEFAULT_PER_PAGE = 100;

    public const MAX_PER_PAGE = 200;

    public function __construct(
        public readonly ?string $fromDate,
        public readonly ?string $toDate,
        public readonly bool $allHistory,
        public readonly int $page = 1,
        public readonly int $perPage = self::DEFAULT_PER_PAGE,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated input (from_date|from, to_date|to, period, page, per_page)
     * @param  string  $today  tenant business date (Y-m-d)
     */
    public static function fromArray(array $data, string $today): self
    {
        $from = self::date($data['from_date'] ?? null) ?? self::date($data['from'] ?? null);
        $to = self::date($data['to_date'] ?? null) ?? self::date($data['to'] ?? null);
        $all = ($data['period'] ?? null) === 'all';

        if ($from === null && $to === null && ! $all) {
            $from = CarbonImmutable::parse($today)->subDays(self::DEFAULT_DAYS - 1)->toDateString();
            $to = $today;
        }

        return new self(
            fromDate: $from,
            toDate: $to,
            allHistory: $all && $from === null && $to === null,
            page: max(1, (int) ($data['page'] ?? 1)),
            perPage: max(1, min(self::MAX_PER_PAGE, (int) ($data['per_page'] ?? self::DEFAULT_PER_PAGE))),
        );
    }

    /**
     * @return array{from_date: ?string, to_date: ?string, all_history: bool, page: int, per_page: int}
     */
    public function toArray(): array
    {
        return [
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
            'all_history' => $this->allHistory,
            'page' => $this->page,
            'per_page' => $this->perPage,
        ];
    }

    private static function date(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? CarbonImmutable::parse($value)->toDateString() : null;
    }
}
