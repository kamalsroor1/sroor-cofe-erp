<?php

declare(strict_types=1);

namespace App\DTOs\Reports;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class ReportFilterDTO
{
    public function __construct(
        public readonly string $tab = 'sales',
        public readonly string $period = 'this_month',
        public readonly string $from_date = '',
        public readonly string $to_date = '',
        public readonly ?int $store_id = null,
        public readonly string $treasury_method = 'all',
        public readonly string $stock_filter = 'all',
    ) {}

    /**
     * @param  CarbonInterface|null  $now  "now" on the tenant clock (SETG-2, TenantClock::now()). Presets such as
     *                                     today / yesterday / this_month resolve against it. Defaults to the app clock.
     */
    public static function fromArray(array $data, ?int $headerStoreId = null, ?CarbonInterface $now = null): self
    {
        $now = CarbonImmutable::instance($now ?? now());
        $today = $now->toDateString();

        $period = (string) ($data['period'] ?? $data['preset'] ?? 'this_month');
        $fromDate = (string) ($data['from_date'] ?? $data['from'] ?? '');
        $toDate = (string) ($data['to_date'] ?? $data['to'] ?? $today);

        if ($fromDate === '') {
            if ($period === 'today') {
                $fromDate = $today;
                $toDate = $today;
            } elseif ($period === 'yesterday') {
                $fromDate = $now->subDay()->toDateString();
                $toDate = $now->subDay()->toDateString();
            } elseif ($period === 'this_week') {
                $fromDate = $now->startOfWeek()->toDateString();
                $toDate = $today;
            } elseif ($period === 'this_year') {
                $fromDate = $now->startOfYear()->toDateString();
                $toDate = $today;
            } else { // this_month
                $fromDate = $now->startOfMonth()->toDateString();
                $toDate = $today;
            }
        }

        $rawStore = $data['store_id'] ?? null;
        $storeId = ($rawStore !== null && $rawStore !== '' && $rawStore !== 'all') ? (int) $rawStore : $headerStoreId;

        return new self(
            tab: (string) ($data['tab'] ?? 'sales'),
            period: $period,
            from_date: $fromDate,
            to_date: $toDate,
            store_id: $storeId,
            treasury_method: (string) ($data['treasury_method'] ?? 'all'),
            stock_filter: (string) ($data['stock_filter'] ?? 'all'),
        );
    }
}
