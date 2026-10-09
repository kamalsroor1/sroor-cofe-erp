<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ENTI-1.10 (central, CTO 2026-10-09 Q12): the append-only credits ledger.
 *
 * One row per movement of a tenant's credits for one credits add-on (`addon_key`):
 *  - `delta` DECIMAL(12,3), positive (allowance, top_up) or negative (usage, expiry);
 *  - `reason` string(20): allowance | top_up | usage | expiry
 *    (App\Models\TenantCreditLedgerEntry::REASONS);
 *  - `billing_invoice_id` = the subscription invoice that sold a top_up, NULL otherwise.
 * The balance is never stored: it is Σ delta (bcmath, App\Services\Billing\CreditBalanceService).
 *
 * Append-only: no `updated_at`; the model refuses update/delete, and production grants the
 * app user no UPDATE/DELETE on this table (see the IDEN-1.15 / OPS-1 grants contract).
 * Hence `tenant_id` has NO foreign key (like billing_invoices: a financial record must
 * outlive the tenant row, and a cascade would be a DELETE) and the invoice FK restricts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_credit_ledger')) {
            return;
        }

        Schema::create('tenant_credit_ledger', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('addon_key', 100);
            $table->decimal('delta', 12, 3);
            $table->string('reason', 20);
            $table->foreignId('billing_invoice_id')->nullable()->constrained('billing_invoices')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'addon_key', 'id']);
            $table->index(['tenant_id', 'created_at']);
            $table->index('reason');
        });
    }

    /**
     * Refuses to drop a ledger that holds movements: it is financial history (the balance is
     * Σ delta), so a rollback must never erase it silently. An empty table is dropped.
     */
    public function down(): void
    {
        if (! Schema::hasTable('tenant_credit_ledger')) {
            return;
        }

        if (DB::table('tenant_credit_ledger')->exists()) {
            throw new RuntimeException(
                'Refusing to drop tenant_credit_ledger: it contains credit movements (append-only financial ledger). '
                .'Export and archive the rows first, then empty the table deliberately before rolling back.'
            );
        }

        Schema::drop('tenant_credit_ledger');
    }
};
