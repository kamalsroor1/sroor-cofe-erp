<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-08 / POS-18 (tenant DB): per-line price audit. The server-resolved list price and its tier,
 * and the user who approved a below-minimum price (the permission holder or the PIN approver).
 * All NULL on historical lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_items')) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'list_unit_price')) {
                $table->decimal('list_unit_price', 12, 3)->nullable()->after('unit_price');
            }
            if (! Schema::hasColumn('invoice_items', 'price_source')) {
                $table->string('price_source', 20)->nullable()->after('list_unit_price');
            }
            if (! Schema::hasColumn('invoice_items', 'override_approved_by')) {
                $table->unsignedBigInteger('override_approved_by')->nullable()->index()->after('price_source');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoice_items')) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_items', 'override_approved_by')) {
                $table->dropIndex(['override_approved_by']);
                $table->dropColumn('override_approved_by');
            }
            foreach (['price_source', 'list_unit_price'] as $column) {
                if (Schema::hasColumn('invoice_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
