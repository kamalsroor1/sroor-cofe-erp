<?php

declare(strict_types=1);

use App\Support\Database\OnlineMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BE-11: invoice / purchase payment vouchers were created without payments.store_id, so the
 * per-branch treasury could not tell whose drawer they belong to. Copy the store of the linked
 * invoice / purchase onto the voucher where it is still empty. Unlinked vouchers (on-account
 * receipts, supplier payments) stay NULL: Payment::scopeForStore resolves them by owner.
 * Only fills NULLs, so it is idempotent and never overwrites a store set by PaymentService.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'store_id')) {
            return;
        }

        // MIG-01: id-range chunks, each its own short transaction (was one UPDATE over the whole table).
        foreach ([['invoice_id', 'invoices'], ['purchase_id', 'purchases']] as [$column, $parent]) {
            if (! Schema::hasColumn('payments', $column) || ! Schema::hasTable($parent)) {
                continue;
            }

            OnlineMigration::forEachIdRange('payments', function (int $from, int $to) use ($column, $parent): void {
                DB::table('payments')
                    ->whereBetween('id', [$from, $to])
                    ->whereNull('store_id')
                    ->whereNotNull($column)
                    ->whereExists(fn ($q) => $q->select(DB::raw('1'))->from($parent)
                        ->whereColumn("{$parent}.id", "payments.{$column}")->whereNotNull("{$parent}.store_id"))
                    ->update(['store_id' => DB::raw("(select {$parent}.store_id from {$parent} where {$parent}.id = payments.{$column})")]);
            });
        }
    }

    public function down(): void
    {
        // Data backfill: not reversible (a backfilled voucher is indistinguishable from one written with a store).
    }
};
