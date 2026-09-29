<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\PurchaseItem;
use App\Models\StockDeposit;
use App\Models\StockMovement;
use App\Services\ActivityLogService;
use App\Services\AuditLogService;
use App\Support\WeightedAverageCost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Rebuilds items.weighted_avg_cost from the stock ledger with the correct rules:
 *  - zero-cost opening deposits / purchase lines are valued at an owner-approved opening cost (--opening-costs)
 *  - purchase_cancel_out removes the cancelled quantity at its purchase cost
 *  - stock_deposit_in blends into the WAC
 *  - cancellation_in (invoice cancel / edit / delete) re-enters at the WAC the invoice was sold at
 *  - sales_return_in re-enters, purchase_return_out leaves, at the movement's recorded unit cost
 *  - S (stock before each movement) is read from stock_movements.stock_before
 * These mirror the runtime rules in PurchaseService / StockService / InvoiceService / ReturnService.
 * Optionally re-costs historical invoice lines with the WAC in force when each sale was booked.
 *
 * Dry-run by default (no row locks taken). Idempotent (targets are derived from source documents, never
 * from the stored WAC). Never deletes rows. Every change is written to audit_logs and a CSV report.
 *
 * An item is UNRESOLVED (skipped entirely, exit code 1) when stock exists or is sold while the replayed WAC
 * is still 0 (e.g. stock_adjustment_in or legacy current_stock before the first costed inflow) and no
 * --opening-costs value is given for it. A zero cost is never written to invoice_items.cost_price.
 *
 * --apply requires the application to be in maintenance mode (`php artisan down`) or --force:
 * it locks items first and then invoices, the reverse of InvoiceService (invoice -> items), so it
 * must not run concurrently with sales.
 */
final class RecalculateInventoryCostsCommand extends Command
{
    protected $signature = 'inventory:recost
        {--apply : Write the changes (default is a dry-run). Requires `php artisan down` first}
        {--force : Allow --apply while the application is NOT in maintenance mode (not recommended)}
        {--items= : Comma-separated item ids (default: every item that has stock movements)}
        {--exclude= : Comma-separated item ids to leave untouched}
        {--opening-costs= : JSON file {"item_id": "unit_cost"} used where an opening deposit / purchase line has cost 0}
        {--recost-invoices : Also rewrite invoice_items.cost_price and invoices.total_cost}
        {--fix-deposit-costs : Also write the opening cost into zero-cost stock_deposits.cost_price and their movement unit_cost}
        {--report= : CSV path (default storage/app/cost-fix/recost_<timestamp>.csv)}';

    protected $description = 'Recalculate weighted average cost (and optionally historical COGS) from the stock ledger. Dry-run by default; --apply requires php artisan down (or --force)';

    private const INVOICE_CHUNK = 200;

    /** Movement types that change quantity but must not change the WAC. */
    private const WAC_NEUTRAL = [
        'stock_adjustment_in', 'stock_adjustment_out', 'waste_out',
        'transfer_in', 'transfer_out', 'transfer_reversal_in', 'transfer_reversal_out',
    ];

    public function handle(AuditLogService $audit, ActivityLogService $activity): int
    {
        $apply = (bool) $this->option('apply');
        $openingCosts = $this->loadOpeningCosts($this->option('opening-costs'));
        $excluded = array_map('intval', array_filter(explode(',', (string) $this->option('exclude'))));
        $itemIds = array_values(array_diff($this->resolveItemIds($this->option('items')), $excluded));
        if ($excluded !== []) {
            $this->line('Excluded items (left untouched): '.implode(', ', $excluded));
        }
        $runId = now()->format('Ymd_His');

        if ($apply && ! $this->option('force') && ! app()->isDownForMaintenance()) {
            $this->error('--apply requires maintenance mode: run `php artisan down` first (or pass --force).');

            return self::FAILURE;
        }

        // Open the report before touching the database so a bad path fails fast.
        $reportPath = $this->option('report') ?: storage_path("app/cost-fix/recost_{$runId}.csv");
        try {
            File::ensureDirectoryExists(dirname($reportPath));
            $reportHandle = @fopen($reportPath, 'w');
        } catch (\Throwable) {
            $reportHandle = false;
        }
        if ($reportHandle === false) {
            $this->error("Cannot open report file for writing: {$reportPath}");

            return self::FAILURE;
        }

        $this->warn($apply ? 'APPLY mode: changes will be written.' : 'DRY-RUN: nothing will be written, no locks taken (use --apply).');

        $report = DB::transaction(function () use ($apply, $openingCosts, $itemIds, $audit, $runId) {
            // APPLY: lock every affected item row first (ascending id). DRY-RUN: plain reads, no locks.
            $itemQuery = Item::withTrashed()->whereIn('id', $itemIds)->orderBy('id');
            $items = ($apply ? $itemQuery->lockForUpdate() : $itemQuery)->get()->keyBy('id');

            $itemRows = [];
            $saleCosts = [];   // [invoice_id][item_id] => WAC at the time the sale was booked
            $unresolved = [];

            foreach ($items as $item) {
                try {
                    $result = $this->replayItem($item, $openingCosts[(string) $item->id] ?? null);
                } catch (RuntimeException $e) {
                    $result = $this->unresolved($e->getMessage());
                }

                if ($result['unresolved'] !== null) {
                    $unresolved[$item->id] = $result['unresolved'];

                    continue; // never write a partial result for an item
                }

                foreach ($result['sales'] as $invoiceId => $wac) {
                    $saleCosts[$invoiceId][$item->id] = $wac;
                }

                $newWac = $result['wac'];
                $newCostPrice = bccomp((string) $item->cost_price, '0.000', 3) > 0
                    ? (string) $item->cost_price
                    : ($result['last_purchase_cost'] ?? $newWac);

                $itemRows[] = [
                    'item_id' => $item->id,
                    'name' => trim((string) $item->name),
                    'wac_old' => (string) $item->weighted_avg_cost,
                    'wac_new' => $newWac,
                    'cost_price_old' => (string) $item->cost_price,
                    'cost_price_new' => $newCostPrice,
                    'source' => $result['source'],
                    'changed' => bccomp((string) $item->weighted_avg_cost, $newWac, 3) !== 0
                        || bccomp((string) $item->cost_price, $newCostPrice, 3) !== 0,
                ];

                if ($apply && end($itemRows)['changed']) {
                    $old = ['weighted_avg_cost' => (string) $item->weighted_avg_cost, 'cost_price' => (string) $item->cost_price];
                    $item->weighted_avg_cost = $newWac;
                    $item->cost_price = $newCostPrice;
                    $item->save();
                    $audit->log('inventory_cost_recalculated', $item, $old, [
                        'weighted_avg_cost' => $newWac, 'cost_price' => $newCostPrice,
                        'source' => $result['source'], 'run_id' => $runId,
                    ]);
                }

                if ($apply && $this->option('fix-deposit-costs')) {
                    $this->fixZeroCostDeposits($item, $result['deposit_costs']);
                }
            }

            $invoiceRows = $this->option('recost-invoices')
                ? $this->recostInvoices($saleCosts, array_keys($unresolved), $apply, $audit, $runId)
                : [];

            return compact('itemRows', 'invoiceRows', 'unresolved');
        });

        $this->printReport($report);
        $this->writeCsv($reportHandle, $report);
        $this->info("CSV report: {$reportPath}");

        if ($apply) {
            $activity->logSystem(
                action: 'inventory_cost_recalculated',
                description: "تصحيح تكلفة المخزون (متوسط مرجح) — تشغيل {$runId}",
                properties: [
                    'run_id' => $runId,
                    'items_changed' => count(array_filter($report['itemRows'], fn ($r) => $r['changed'])),
                    'invoice_lines_changed' => count($report['invoiceRows']),
                ],
            );
        }

        return $report['unresolved'] === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{wac:string, sales:array<int,string>, source:string, unresolved:?string,
     *               last_purchase_cost:?string, deposit_costs:array<int,string>}
     */
    private function replayItem(Item $item, ?string $openingCost): array
    {
        $movements = StockMovement::where('item_id', $item->id)->orderBy('id')->get();

        $wac = '0.000';
        $sales = [];
        $source = 'exact';
        $lastPurchaseCost = null;
        $depositCosts = [];
        $purchaseLines = []; // purchase_id => queue of line costs (same item may appear twice in one purchase)

        foreach ($movements as $m) {
            $qty = (string) $m->quantity;
            $stockBefore = (string) $m->stock_before;

            // Stock that exists (or is sold) while the replayed WAC is still 0 has no known cost.
            // Runtime falls back to cost_price; the replay cannot, so it needs an explicit opening cost.
            $holdsStock = bccomp($stockBefore, '0.000', 3) > 0 || $m->movement_type === 'sales_out';
            if ($holdsStock && bccomp($wac, '0.000', 3) <= 0) {
                if ($openingCost === null) {
                    return $this->unresolved("stock at zero cost before the first costed inflow (movement #{$m->id} {$m->movement_type}) and no opening cost given");
                }
                $wac = $openingCost;
                $source = 'opening-cost';
            }

            switch ($m->movement_type) {
                case 'stock_deposit_in':
                    $cost = (string) (StockDeposit::withTrashed()->whereKey($m->source_id)->value('cost_price') ?? '0.000');
                    if (bccomp($cost, '0.000', 3) <= 0) {
                        if ($openingCost === null) {
                            return $this->unresolved("zero-cost deposit #{$m->source_id} and no opening cost given");
                        }
                        $cost = $openingCost;
                        $source = 'opening-cost';
                        $depositCosts[(int) $m->source_id] = $cost;
                    }
                    $wac = WeightedAverageCost::add($stockBefore, $wac, $qty, $cost);
                    break;

                case 'purchase_in':
                case 'purchase_restore_in':
                case 'purchase_cancel_out':
                    $cost = $this->purchaseLineCost($purchaseLines, (int) $m->source_id, $item->id, $m->movement_type, $qty);
                    if (bccomp($cost, '0.000', 3) <= 0) {
                        if ($openingCost === null) {
                            return $this->unresolved("zero-cost purchase #{$m->source_id} and no opening cost given");
                        }
                        $cost = $openingCost;
                        $source = 'opening-cost';
                    }
                    if ($m->movement_type === 'purchase_cancel_out') {
                        $wac = WeightedAverageCost::remove($stockBefore, $wac, $qty, $cost);
                    } else {
                        $wac = WeightedAverageCost::add($stockBefore, $wac, $qty, $cost);
                        $lastPurchaseCost = $cost;
                    }
                    break;

                case 'sales_out':
                    if ($m->source_type === Invoice::class) {
                        $sales[(int) $m->source_id] = $wac;
                    }
                    break;

                case 'cancellation_in':
                    // Goods come back at the cost they were sold at.
                    $cost = ($m->source_type === Invoice::class && isset($sales[(int) $m->source_id]))
                        ? $sales[(int) $m->source_id]
                        : (string) $m->unit_cost;
                    if (bccomp($cost, '0.000', 3) <= 0) {
                        $cost = $wac;
                    }
                    $wac = WeightedAverageCost::add($stockBefore, $wac, $qty, $cost);
                    break;

                case 'sales_return_in':
                case 'purchase_return_out':
                    $cost = (string) $m->unit_cost;
                    if (bccomp($cost, '0.000', 3) <= 0) {
                        $cost = $wac;
                    }
                    $wac = $m->movement_type === 'sales_return_in'
                        ? WeightedAverageCost::add($stockBefore, $wac, $qty, $cost)
                        : WeightedAverageCost::remove($stockBefore, $wac, $qty, $cost);
                    break;

                default:
                    if (! in_array($m->movement_type, self::WAC_NEUTRAL, true)) {
                        $this->warn("Item {$item->id}: unknown movement type [{$m->movement_type}] #{$m->id} treated as WAC-neutral");
                    }
            }
        }

        if ($movements->isEmpty()) {
            $wac = (string) $item->weighted_avg_cost;
        }

        return [
            'wac' => $wac, 'sales' => $sales, 'source' => $source, 'unresolved' => null,
            'last_purchase_cost' => $lastPurchaseCost, 'deposit_costs' => $depositCosts,
        ];
    }

    /**
     * Landed unit cost of the purchase line (incl. soft-deleted lines). Lines are loaded once per purchase
     * and matched by quantity; repeated movements of the same type (cancel -> restore -> cancel) cycle
     * through the matching lines instead of exhausting a queue.
     */
    private function purchaseLineCost(array &$cache, int $purchaseId, int $itemId, string $type, string $qty): string
    {
        if (! isset($cache[$purchaseId])) {
            $cache[$purchaseId] = [
                'lines' => PurchaseItem::withTrashed()
                    ->where('purchase_id', $purchaseId)->where('item_id', $itemId)
                    ->orderBy('id')->get(['quantity', 'cost_price'])
                    ->map(fn ($l) => ['qty' => (string) $l->quantity, 'cost' => (string) $l->cost_price])->all(),
                'counters' => [],
            ];
        }

        $lines = $cache[$purchaseId]['lines'];
        if ($lines === []) {
            throw new RuntimeException("no purchase line for purchase #{$purchaseId}, item #{$itemId}");
        }

        $matching = array_values(array_filter($lines, fn ($l) => bccomp($l['qty'], $qty, 3) === 0)) ?: $lines;
        $counter = $cache[$purchaseId]['counters'][$type] ?? 0;
        $cache[$purchaseId]['counters'][$type] = $counter + 1;

        return $matching[$counter % count($matching)]['cost'];
    }

    private function unresolved(string $reason): array
    {
        return ['wac' => '0.000', 'sales' => [], 'source' => 'unresolved', 'unresolved' => $reason,
            'last_purchase_cost' => null, 'deposit_costs' => []];
    }

    /** @param array<int, array<int, string>> $saleCosts */
    private function recostInvoices(array $saleCosts, array $skipItemIds, bool $apply, AuditLogService $audit, string $runId): array
    {
        $rows = [];
        $invoiceIds = array_keys($saleCosts);
        sort($invoiceIds);

        foreach (array_chunk($invoiceIds, self::INVOICE_CHUNK) as $chunk) {
            $invoiceQuery = Invoice::whereIn('id', $chunk)->where('status', 'confirmed')->orderBy('id');
            foreach (($apply ? $invoiceQuery->lockForUpdate() : $invoiceQuery)->get() as $invoice) {
                $this->recostInvoice($invoice, $saleCosts, $skipItemIds, $apply, $audit, $runId, $rows);
            }
        }

        return $rows;
    }

    /** Re-costs one invoice; lines whose target is unknown or zero are kept as they are. */
    private function recostInvoice(Invoice $invoice, array $saleCosts, array $skipItemIds, bool $apply, AuditLogService $audit, string $runId, array &$rows): void
    {
        $lineQuery = InvoiceItem::where('invoice_id', $invoice->id)->orderBy('id');
        $lines = ($apply ? $lineQuery->lockForUpdate() : $lineQuery)->get();
        $changedLines = [];
        $newTotalCost = '0.000';

        foreach ($lines as $line) {
            $target = $saleCosts[$invoice->id][$line->item_id] ?? null;
            if ($target === null || in_array($line->item_id, $skipItemIds, true) || bccomp($target, '0.000', 3) <= 0) {
                $target = (string) $line->cost_price; // item not recalculated: keep as is
            } elseif (bccomp((string) $line->cost_price, $target, 3) !== 0) {
                $changedLines[] = ['line_id' => $line->id, 'item_id' => $line->item_id, 'old' => (string) $line->cost_price, 'new' => $target];
                $rows[] = [
                    'invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => (string) $invoice->invoice_date?->toDateString(), 'line_id' => $line->id,
                    'item_id' => $line->item_id, 'quantity' => (string) $line->quantity,
                    'cost_old' => (string) $line->cost_price, 'cost_new' => $target,
                    'cogs_delta' => bcmul((string) $line->quantity, bcsub($target, (string) $line->cost_price, 3), 3),
                ];
            }
            $newTotalCost = bcadd($newTotalCost, bcmul((string) $line->quantity, $target, 3), 3);
        }

        if (! $apply || ($changedLines === [] && bccomp((string) $invoice->total_cost, $newTotalCost, 3) === 0)) {
            return;
        }

        // Query-builder updates on purpose: keep updated_at so the invoice is not shown as "edited".
        foreach ($changedLines as $c) {
            DB::table('invoice_items')->where('id', $c['line_id'])->update(['cost_price' => $c['new']]);
        }
        $oldTotalCost = (string) $invoice->total_cost;
        DB::table('invoices')->where('id', $invoice->id)->update(['total_cost' => $newTotalCost]);

        $audit->log('invoice_cost_recalculated', $invoice,
            ['total_cost' => $oldTotalCost, 'lines' => array_map(fn ($c) => [$c['line_id'] => $c['old']], $changedLines)],
            ['total_cost' => $newTotalCost, 'lines' => array_map(fn ($c) => [$c['line_id'] => $c['new']], $changedLines), 'run_id' => $runId],
        );
    }

    /** @param array<int,string> $depositCosts deposit_id => opening cost */
    private function fixZeroCostDeposits(Item $item, array $depositCosts): void
    {
        foreach ($depositCosts as $depositId => $cost) {
            $deposit = StockDeposit::withTrashed()->whereKey($depositId)->lockForUpdate()->first();
            if ($deposit === null || bccomp((string) $deposit->cost_price, '0.000', 3) > 0) {
                continue; // idempotent
            }
            DB::table('stock_deposits')->where('id', $depositId)->update(['cost_price' => $cost]);
            DB::table('stock_movements')
                ->where('source_type', StockDeposit::class)->where('source_id', $depositId)
                ->update(['unit_cost' => $cost]);
        }
    }

    private function loadOpeningCosts(?string $path): array
    {
        if ($path === null) {
            return [];
        }
        if (! File::exists($path)) {
            throw new RuntimeException("Opening-costs file not found: {$path}");
        }
        $data = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        foreach ($data as $itemId => $cost) {
            if (! is_numeric($cost) || bccomp((string) $cost, '0.000', 3) <= 0) {
                throw new RuntimeException("Invalid opening cost for item {$itemId}: must be > 0");
            }
            $data[$itemId] = bcadd((string) $cost, '0', 3);
        }

        return $data;
    }

    private function resolveItemIds(?string $option): array
    {
        if ($option) {
            return array_map('intval', array_filter(explode(',', $option)));
        }

        return StockMovement::query()->distinct()->orderBy('item_id')->pluck('item_id')->map(fn ($id) => (int) $id)->all();
    }

    private function printReport(array $report): void
    {
        $this->table(
            ['item', 'name', 'WAC old', 'WAC new', 'cost_price old', 'cost_price new', 'source', 'changed'],
            array_map(fn ($r) => [$r['item_id'], $r['name'], $r['wac_old'], $r['wac_new'], $r['cost_price_old'],
                $r['cost_price_new'], $r['source'], $r['changed'] ? 'YES' : ''], $report['itemRows']),
        );

        foreach ($report['unresolved'] as $itemId => $reason) {
            $this->error("Item {$itemId} skipped: {$reason}");
        }

        if ($report['invoiceRows'] !== []) {
            $delta = array_reduce($report['invoiceRows'], fn ($c, $r) => bcadd($c, $r['cogs_delta'], 3), '0.000');
            $invoices = count(array_unique(array_column($report['invoiceRows'], 'invoice_id')));
            $this->info(count($report['invoiceRows'])." invoice lines / {$invoices} invoices to re-cost; COGS delta = {$delta} (profit reduced by this amount)");
        }
    }

    /** @param resource $fh */
    private function writeCsv($fh, array $report): void
    {
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['type', 'item_id', 'name', 'wac_old', 'wac_new', 'cost_price_old', 'cost_price_new', 'source',
            'invoice_id', 'invoice_number', 'invoice_date', 'line_id', 'quantity', 'cost_old', 'cost_new', 'cogs_delta']);
        foreach ($report['itemRows'] as $r) {
            fputcsv($fh, ['item', $r['item_id'], $r['name'], $r['wac_old'], $r['wac_new'], $r['cost_price_old'], $r['cost_price_new'], $r['source']]);
        }
        foreach ($report['invoiceRows'] as $r) {
            fputcsv($fh, ['invoice_line', $r['item_id'], '', '', '', '', '', '', $r['invoice_id'], $r['invoice_number'],
                $r['invoice_date'], $r['line_id'], $r['quantity'], $r['cost_old'], $r['cost_new'], $r['cogs_delta']]);
        }
        fclose($fh);
    }
}
