<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS-16 / entry_sequence (tenant DB): persists the 1-based order in which each invoice line was
 * entered in the cart, so invoice details and thermal/A4 prints preserve the cashier's entry order.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_items')) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'entry_sequence')) {
                $table->unsignedInteger('entry_sequence')->nullable()->after('item_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoice_items')) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_items', 'entry_sequence')) {
                $table->dropColumn('entry_sequence');
            }
        });
    }
};
