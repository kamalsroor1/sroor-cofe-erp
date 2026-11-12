<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Shifts\RecalculateClosedShiftTotalsAction;
use App\Models\CashShift;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Throwable;

/**
 * BUG-06 data correction. Shifts closed before the fix stored neither their cash expenses nor
 * their cash supplier payments, so their Z report lines do not add up to the stored expected
 * balance. This recomputes those two columns for every CLOSED shift from the shift's own window
 * and stores them (RecalculateClosedShiftTotalsAction); nothing else on the shift changes.
 *
 * Idempotent: a second run finds nothing to change. `--dry-run` computes and lists, writes nothing.
 * Shifts whose lines still do not add up to the stored expected balance (documents edited after
 * the close) are listed; their expected balance is never rewritten.
 */
final class RecalculateShiftTotalsCommand extends Command
{
    protected $signature = 'shifts:recalculate-totals
        {--tenant= : Limit to a single tenant id}
        {--dry-run : Compute and list, write nothing}';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('console.shift_totals.description'));
    }

    public function handle(RecalculateClosedShiftTotalsAction $recalculate): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $query = Tenant::query();
        $tenantId = $this->option('tenant');

        if ($tenantId === null || $tenantId === '') {
            $query->provisioned();
        } else {
            $query->whereKey($tenantId);

            if (! (clone $query)->exists()) {
                $this->error((string) __('console.shift_totals.tenant_not_found', ['tenant' => $tenantId]));

                return self::FAILURE;
            }
        }

        $rows = [];
        $tenants = 0;
        $failed = 0;
        $checked = 0;
        $changed = 0;
        $inconsistent = [];

        foreach ($query->cursor() as $tenant) {
            try {
                /** @var list<array{id: int, shift_number: string, changed: bool, consistent: bool, total_expenses: string, total_supplier_paid: string, lines_total: string, expected_cash_balance: string}> $results */
                $results = $tenant->run(fn (): array => $this->process($recalculate, $dryRun));
            } catch (Throwable $e) {
                $failed++;
                $this->warn((string) __('console.shift_totals.tenant_failed', [
                    'tenant' => $tenant->getTenantKey(),
                    'error' => $e->getMessage(),
                ]));

                continue;
            }

            $tenants++;

            foreach ($results as $result) {
                $checked++;
                if ($result['changed']) {
                    $changed++;
                }
                if (! $result['consistent']) {
                    $inconsistent[] = $tenant->getTenantKey().':'.$result['shift_number'];
                }
                if ($result['changed'] || ! $result['consistent']) {
                    $rows[] = [
                        $tenant->getTenantKey(),
                        $result['shift_number'],
                        $result['total_expenses'],
                        $result['total_supplier_paid'],
                        $result['lines_total'],
                        $result['expected_cash_balance'],
                        (string) __($result['consistent'] ? 'console.shift_totals.status_ok' : 'console.shift_totals.status_mismatch'),
                    ];
                }
            }
        }

        if ($tenants === 0 && $failed === 0) {
            $this->info((string) __('console.shift_totals.no_tenants'));

            return self::SUCCESS;
        }

        if ($rows !== []) {
            $this->table([
                __('console.shift_totals.col_tenant'),
                __('console.shift_totals.col_shift'),
                __('console.shift_totals.col_expenses'),
                __('console.shift_totals.col_supplier_paid'),
                __('console.shift_totals.col_lines_total'),
                __('console.shift_totals.col_expected'),
                __('console.shift_totals.col_status'),
            ], $rows);
        }

        $this->info((string) __($dryRun ? 'console.shift_totals.dry_run' : 'console.shift_totals.done', [
            'checked' => $checked,
            'changed' => $changed,
            'total' => $tenants,
        ]));

        if ($inconsistent !== []) {
            $this->warn((string) __('console.shift_totals.mismatches', [
                'count' => count($inconsistent),
                'shifts' => implode(', ', $inconsistent),
            ]));
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Each shift in its own short transaction (inside the action), so one tenant never holds
     * locks on all its shifts at once.
     *
     * @return list<array{id: int, shift_number: string, changed: bool, consistent: bool, total_expenses: string, total_supplier_paid: string, lines_total: string, expected_cash_balance: string}>
     */
    private function process(RecalculateClosedShiftTotalsAction $recalculate, bool $dryRun): array
    {
        $results = [];

        // Soft-deleted shifts too: a restored shift must not come back with zero lines.
        $ids = CashShift::withTrashed()
            ->where('status', 'closed')
            ->whereNotNull('opened_at')
            ->whereNotNull('closed_at')
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            $result = $recalculate->execute((int) $id, $dryRun);

            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }
}
