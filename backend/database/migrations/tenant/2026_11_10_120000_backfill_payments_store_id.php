<?php

declare(strict_types=1);

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

        if (Schema::hasColumn('payments', 'invoice_id') && Schema::hasTable('invoices')) {
            DB::table('payments')
                ->whereNull('store_id')
                ->whereNotNull('invoice_id')
                ->whereExists(fn ($q) => $q->select(DB::raw('1'))->from('invoices')
                    ->whereColumn('invoices.id', 'payments.invoice_id')->whereNotNull('invoices.store_id'))
                ->update(['store_id' => DB::raw('(select invoices.store_id from invoices where invoices.id = payments.invoice_id)')]);
        }

        if (Schema::hasColumn('payments', 'purchase_id') && Schema::hasTable('purchases')) {
            DB::table('payments')
                ->whereNull('store_id')
                ->whereNotNull('purchase_id')
                ->whereExists(fn ($q) => $q->select(DB::raw('1'))->from('purchases')
                    ->whereColumn('purchases.id', 'payments.purchase_id')->whereNotNull('purchases.store_id'))
                ->update(['store_id' => DB::raw('(select purchases.store_id from purchases where purchases.id = payments.purchase_id)')]);
        }
    }

    public function down(): void
    {
        // Data backfill: not reversible (a backfilled voucher is indistinguishable from one written with a store).
    }
};
