<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change handed back to the customer on a cash (or split) POS sale.
 * Payment rows sum to net_total once cash lines are reduced, so the change must be
 * persisted on the invoice itself; an idempotent replay returns the stored value.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoices', 'change_amount')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('change_amount', 12, 3)->default(0)->after('remaining_amount');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('invoices', 'change_amount')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('change_amount');
        });
    }
};
