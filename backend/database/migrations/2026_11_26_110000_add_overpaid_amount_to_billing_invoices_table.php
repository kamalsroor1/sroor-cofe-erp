<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #232 owner decision 2026-11-26 (CENTRAL DB): an upgrade difference is re-priced at Verify from the
 * verified transfer moment, so the shop may have paid MORE than the final total. The excess is
 * stored here, explicitly, when the invoice is settled (DECIMAL(12,3), 0 = none). What happens to
 * it (refund / credit) is an open owner decision: nothing reads it to move money.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('billing_invoices') || Schema::hasColumn('billing_invoices', 'overpaid_amount')) {
            return;
        }

        Schema::table('billing_invoices', function (Blueprint $table): void {
            $table->decimal('overpaid_amount', 12, 3)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('billing_invoices') || ! Schema::hasColumn('billing_invoices', 'overpaid_amount')) {
            return;
        }

        Schema::table('billing_invoices', function (Blueprint $table): void {
            $table->dropColumn('overpaid_amount');
        });
    }
};
