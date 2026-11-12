<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUG-06: the close computed the cash expenses and the cash supplier payments of the shift but
 * stored neither (cash_shifts had no column for them), so a closed shift's Z report showed 0
 * expenses and no supplier line while its stored expected balance did subtract both.
 *
 * Already-closed shifts keep 0 here; `shifts:recalculate-totals` fills them from the shift window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_shifts', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_shifts', 'total_expenses')) {
                $table->decimal('total_expenses', 12, 3)->default(0)->after('total_payments_collected');
            }
            if (! Schema::hasColumn('cash_shifts', 'total_supplier_paid')) {
                $table->decimal('total_supplier_paid', 12, 3)->default(0)->after('total_expenses');
            }
        });
    }

    public function down(): void
    {
        foreach (['total_supplier_paid', 'total_expenses'] as $column) {
            if (Schema::hasColumn('cash_shifts', $column)) {
                Schema::table('cash_shifts', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
