<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * SHOP-05: the aging engine shared by the customer debt aging and the supplier payables reports.
 *
 * The open balance of a party is its ledger balance (customers.current_balance /
 * suppliers.current_balance, which already nets payments, returns and opening balances). Payments
 * settle the OLDEST documents first (FIFO), so the still-open part of the balance is made of the
 * NEWEST confirmed documents: walking the party's documents newest-first, each one takes
 * min(its net_total, what is left of the balance). That allocated amount is aged by the
 * document's date into 0-30 / 31-60 / 61-90 / 90+ days (as of today).
 *
 * Whatever is left after every document (an opening balance, or a balance older than all the
 * documents) is `unallocated`. With a store filter, only the amounts allocated to that store's
 * documents are shown (the balance itself is company-wide), so `unallocated` is 0 there.
 *
 * Everything runs in SQL: a running SUM() OVER (PARTITION BY party ORDER BY date DESC, id DESC)
 * per document, then one grouped statement per party. Two statements per report whatever the
 * number of documents (page + totals). Window functions: MySQL 8, MariaDB 10.2+, sqlite 3.25+.
 */
final class BalanceAging
{
    public const BUCKETS = ['bucket_0_30', 'bucket_31_60', 'bucket_61_90', 'bucket_90_plus'];

    /**
     * @param  array{party_table: string, doc_table: string, party_fk: string, date_column: string}  $source
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, mixed>, total: int}
     */
    public function report(array $source, string $asOf, ?int $storeId, ?int $page = null, int $perPage = 25): array
    {
        $base = $this->perParty($source, $asOf, $storeId);
        $orderExpr = $storeId === null ? 'parties.current_balance' : 'COALESCE(aged.allocated_store, 0)';

        $rowsQuery = (clone $base)
            ->select('parties.id', 'parties.name', 'parties.phone', 'parties.current_balance')
            ->selectRaw('COALESCE(aged.bucket_0_30, 0) as bucket_0_30')
            ->selectRaw('COALESCE(aged.bucket_31_60, 0) as bucket_31_60')
            ->selectRaw('COALESCE(aged.bucket_61_90, 0) as bucket_61_90')
            ->selectRaw('COALESCE(aged.bucket_90_plus, 0) as bucket_90_plus')
            ->selectRaw('COALESCE(aged.allocated_all, 0) as allocated_all')
            ->selectRaw('aged.oldest_date as oldest_date')
            ->orderByRaw($orderExpr.' DESC')
            ->orderBy('parties.id');

        if ($page !== null) {
            $rowsQuery->forPage($page, $perPage);
        }

        $rows = [];
        foreach ($rowsQuery->get() as $row) {
            $rows[] = $this->shapeRow($row, $storeId);
        }

        $totalsRow = (clone $base)
            ->selectRaw('COUNT(*) as parties_count')
            ->selectRaw('COALESCE(SUM(parties.current_balance), 0) as balance_sum')
            ->selectRaw('COALESCE(SUM(aged.bucket_0_30), 0) as bucket_0_30')
            ->selectRaw('COALESCE(SUM(aged.bucket_31_60), 0) as bucket_31_60')
            ->selectRaw('COALESCE(SUM(aged.bucket_61_90), 0) as bucket_61_90')
            ->selectRaw('COALESCE(SUM(aged.bucket_90_plus), 0) as bucket_90_plus')
            ->selectRaw('COALESCE(SUM(aged.allocated_all), 0) as allocated_all')
            ->first();

        $totals = [
            'bucket_0_30' => Decimal::normalize($this->scalar($totalsRow?->bucket_0_30)),
            'bucket_31_60' => Decimal::normalize($this->scalar($totalsRow?->bucket_31_60)),
            'bucket_61_90' => Decimal::normalize($this->scalar($totalsRow?->bucket_61_90)),
            'bucket_90_plus' => Decimal::normalize($this->scalar($totalsRow?->bucket_90_plus)),
            'unallocated' => $storeId === null
                ? Decimal::sub(Decimal::normalize($this->scalar($totalsRow?->balance_sum)), Decimal::normalize($this->scalar($totalsRow?->allocated_all)))
                : '0.000',
        ];
        $totals['total'] = $this->sumBuckets($totals);

        return [
            'rows' => $rows,
            'totals' => $totals,
            'total' => (int) ($totalsRow->parties_count ?? 0),
        ];
    }

    /**
     * Parties with an open balance (> 0), left-joined to their aged allocations. In store mode,
     * only the parties with something allocated to that store's documents.
     */
    private function perParty(array $source, string $asOf, ?int $storeId): Builder
    {
        $day = CarbonImmutable::parse($asOf)->startOfDay();
        $d30 = $day->subDays(30)->toDateString();
        $d60 = $day->subDays(60)->toDateString();
        $d90 = $day->subDays(90)->toDateString();

        $store = $storeId === null ? '1 = 1' : 'alloc.store_id = ?';
        $storeBindings = $storeId === null ? [] : [$storeId];
        $sumWhere = fn (string $when, array $dates): array => [
            'sql' => "SUM(CASE WHEN {$store} AND {$when} THEN alloc.allocated ELSE 0 END)",
            'bindings' => array_merge($storeBindings, $dates),
        ];

        $buckets = [
            'bucket_0_30' => $sumWhere('alloc.doc_date >= ?', [$d30]),
            'bucket_31_60' => $sumWhere('alloc.doc_date >= ? AND alloc.doc_date < ?', [$d60, $d30]),
            'bucket_61_90' => $sumWhere('alloc.doc_date >= ? AND alloc.doc_date < ?', [$d90, $d60]),
            'bucket_90_plus' => $sumWhere('alloc.doc_date < ?', [$d90]),
            'allocated_store' => $sumWhere('1 = 1', []),
        ];

        $aged = DB::query()
            ->fromSub($this->allocations($source), 'alloc')
            ->groupBy('alloc.party_id')
            ->select('alloc.party_id')
            ->selectRaw('SUM(alloc.allocated) as allocated_all')
            ->selectRaw('MIN(CASE WHEN alloc.allocated > 0 THEN alloc.doc_date END) as oldest_date');
        foreach ($buckets as $alias => $expr) {
            $aged->selectRaw($expr['sql'].' as '.$alias, $expr['bindings']);
        }

        $query = DB::table($source['party_table'].' as parties')
            ->leftJoinSub($aged, 'aged', 'aged.party_id', '=', 'parties.id')
            ->whereNull('parties.deleted_at')
            ->where('parties.current_balance', '>', 0);

        if ($storeId !== null) {
            $query->where('aged.allocated_store', '>', 0);
        }

        return $query;
    }

    /**
     * One row per confirmed document of a party with an open balance: its share of that balance
     * (newest documents first). Not grouped; the caller groups outside it.
     */
    private function allocations(array $source): Builder
    {
        $docs = $source['doc_table'];
        $fk = $source['party_fk'];
        $dateColumn = $source['date_column'];

        $running = DB::table($docs.' as docs')
            ->join($source['party_table'].' as owner', 'owner.id', '=', 'docs.'.$fk)
            ->where('docs.status', 'confirmed')
            ->whereNull('docs.deleted_at')
            ->whereNull('owner.deleted_at')
            ->where('owner.current_balance', '>', 0)
            ->select([
                'docs.id as doc_id',
                'docs.'.$fk.' as party_id',
                'docs.store_id as store_id',
                'docs.'.$dateColumn.' as doc_date',
                'docs.net_total as net_total',
                'owner.current_balance as balance',
            ])
            ->selectRaw(
                'SUM(docs.net_total) OVER (PARTITION BY docs.'.$fk
                .' ORDER BY docs.'.$dateColumn.' DESC, docs.id DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) as cumulative'
            );

        return DB::query()
            ->fromSub($running, 'running')
            ->select('running.party_id', 'running.store_id', 'running.doc_date')
            ->selectRaw(
                'CASE WHEN running.cumulative - running.net_total >= running.balance THEN 0'
                .' WHEN running.cumulative <= running.balance THEN running.net_total'
                .' ELSE running.balance - (running.cumulative - running.net_total) END as allocated'
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeRow(object $row, ?int $storeId): array
    {
        $out = [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'phone' => $row->phone,
            'current_balance' => Decimal::normalize($this->scalar($row->current_balance)),
            'bucket_0_30' => Decimal::normalize($this->scalar($row->bucket_0_30)),
            'bucket_31_60' => Decimal::normalize($this->scalar($row->bucket_31_60)),
            'bucket_61_90' => Decimal::normalize($this->scalar($row->bucket_61_90)),
            'bucket_90_plus' => Decimal::normalize($this->scalar($row->bucket_90_plus)),
            'unallocated' => $storeId === null
                ? Decimal::sub(Decimal::normalize($this->scalar($row->current_balance)), Decimal::normalize($this->scalar($row->allocated_all)))
                : '0.000',
            'oldest_date' => $row->oldest_date !== null ? substr((string) $row->oldest_date, 0, 10) : null,
        ];
        $out['total'] = $this->sumBuckets($out);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function sumBuckets(array $values): string
    {
        $sum = '0.000';
        foreach ([...self::BUCKETS, 'unallocated'] as $key) {
            $sum = Decimal::add($sum, (string) $values[$key]);
        }

        return $sum;
    }

    private function scalar(mixed $value): string
    {
        return $value === null ? '0' : (string) $value;
    }
}
