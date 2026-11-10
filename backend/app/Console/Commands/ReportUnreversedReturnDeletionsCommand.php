<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Returns\ReverseDeletedReturnAction;
use App\Models\Item;
use App\Models\ReturnDocument;
use App\Models\ReturnItem;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * BE-02 data correction. Before DeleteReturnAction reversed anything, deleting a return only
 * soft-deleted the document: its stock movement, average cost and party balance stayed. This lists,
 * per tenant, soft-deleted returns that were never reversed (they made stock moves when created,
 * `sales_return_in` / `purchase_return_out`, and have no `sales_return_cancel_out` /
 * `purchase_return_cancel_in` movement), with their stock effect and party balance effect.
 *
 * DRY-RUN by default: every return is run through the real reversal inside a rolled-back
 * transaction, so the status ("would reverse" or "blocked: ...") is exact and nothing is written.
 * `--fix` reverses each return for real, in its own transaction, with the same logic as
 * DeleteReturnAction (ReturnReversalService). A return whose reversal would make stock negative
 * (or whose item no longer exists) is skipped and reported, never forced.
 */
final class ReportUnreversedReturnDeletionsCommand extends Command
{
    /** Movements a return writes when created; a return without them never touched stock. */
    private const CREATION_MOVEMENTS = ['sales_return_in', 'purchase_return_out'];

    protected $signature = 'returns:report-unreversed-deletions
        {--tenant= : Limit to a single tenant id}
        {--fix : Reverse the listed returns (stock, average cost, party balance)}';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('console.unreversed_return_deletions.description'));
    }

    public function handle(ReverseDeletedReturnAction $reverse): int
    {
        $fix = (bool) $this->option('fix');
        $query = Tenant::query();
        $tenantId = $this->option('tenant');

        if ($tenantId === null || $tenantId === '') {
            $query->provisioned();
        } else {
            $query->whereKey($tenantId);

            if (! (clone $query)->exists()) {
                $this->error((string) __('console.unreversed_return_deletions.tenant_not_found', ['tenant' => $tenantId]));

                return self::FAILURE;
            }
        }

        $rows = [];
        $tenants = 0;
        $found = 0;
        $done = 0;
        $blocked = 0;

        foreach ($query->cursor() as $tenant) {
            try {
                /** @var list<array{id: int, number: string, type: string, deleted_at: string, stock: string, balance: string, status: string, ok: bool}> $returns */
                $returns = $tenant->run(fn (): array => $this->process($reverse, $fix));
            } catch (Throwable $e) {
                $this->warn((string) __('console.unreversed_return_deletions.tenant_failed', [
                    'tenant' => $tenant->getTenantKey(),
                    'error' => $e->getMessage(),
                ]));

                continue;
            }

            $tenants++;

            foreach ($returns as $return) {
                $found++;
                $return['ok'] ? $done++ : $blocked++;
                $rows[] = [$tenant->getTenantKey(), $return['id'], $return['number'], $return['type'], $return['deleted_at'], $return['stock'], $return['balance'], $return['status']];
            }
        }

        if ($tenants === 0) {
            $this->info((string) __('console.unreversed_return_deletions.no_tenants'));

            return self::SUCCESS;
        }

        if ($rows !== []) {
            $this->table([
                __('console.unreversed_return_deletions.col_tenant'),
                __('console.unreversed_return_deletions.col_id'),
                __('console.unreversed_return_deletions.col_number'),
                __('console.unreversed_return_deletions.col_type'),
                __('console.unreversed_return_deletions.col_deleted_at'),
                __('console.unreversed_return_deletions.col_stock'),
                __('console.unreversed_return_deletions.col_balance'),
                __('console.unreversed_return_deletions.col_status'),
            ], $rows);
        }

        $this->info((string) __($fix ? 'console.unreversed_return_deletions.fixed' : 'console.unreversed_return_deletions.dry_run', [
            'count' => $found,
            'ok' => $done,
            'blocked' => $blocked,
            'total' => $tenants,
        ]));

        return self::SUCCESS;
    }

    /**
     * @return list<array{id: int, number: string, type: string, deleted_at: string, stock: string, balance: string, status: string, ok: bool}>
     */
    private function process(ReverseDeletedReturnAction $reverse, bool $fix): array
    {
        $returns = ReturnDocument::onlyTrashed()
            ->whereHas('stockMovements', fn ($q) => $q->whereIn('movement_type', self::CREATION_MOVEMENTS))
            ->whereDoesntHave('stockMovements', fn ($q) => $q->whereIn('movement_type', ['sales_return_cancel_out', 'purchase_return_cancel_in']))
            ->orderBy('id')
            ->get();

        $rows = [];

        foreach ($returns as $return) {
            $row = [
                'id' => (int) $return->id,
                'number' => (string) $return->return_number,
                'type' => (string) __($return->return_type === 'purchase_return' ? 'console.unreversed_return_deletions.type_purchase' : 'console.unreversed_return_deletions.type_sales'),
                'deleted_at' => (string) $return->deleted_at?->toDateTimeString(),
                'stock' => $this->stockEffect($return),
                'balance' => '-',
                'status' => '',
                'ok' => true,
            ];

            try {
                $result = $reverse->execute((int) $return->id, dryRun: ! $fix);

                if (! $result['reversed']) {
                    // Reversed by a concurrent run between the query and the lock: nothing to do.
                    continue;
                }

                if ($result['party_balance_before'] !== null) {
                    $row['balance'] = $result['party_balance_before'].' -> '.$result['party_balance_after'];
                }
                $row['status'] = (string) __($fix ? 'console.unreversed_return_deletions.status_reversed' : 'console.unreversed_return_deletions.status_would_reverse');
            } catch (ValidationException $e) {
                $row['ok'] = false;
                $row['status'] = (string) __('console.unreversed_return_deletions.status_blocked', [
                    'reason' => (string) collect($e->errors())->flatten()->first(),
                ]);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /** "Item: -7.250" per item: what reversing does to the stock (sales return takes out, purchase return puts back). */
    private function stockEffect(ReturnDocument $return): string
    {
        $sign = $return->return_type === 'purchase_return' ? '+' : '-';
        $totals = [];

        foreach (ReturnItem::query()->where('return_id', $return->id)->orderBy('id')->get(['item_id', 'quantity']) as $line) {
            $totals[$line->item_id] = bcadd($totals[$line->item_id] ?? '0.000', (string) $line->quantity, 3);
        }

        $names = Item::withTrashed()->whereIn('id', array_keys($totals))->pluck('name', 'id');

        $parts = [];
        foreach ($totals as $itemId => $quantity) {
            $parts[] = ($names[$itemId] ?? '#'.$itemId).': '.$sign.$quantity;
        }

        return implode('; ', $parts);
    }
}
