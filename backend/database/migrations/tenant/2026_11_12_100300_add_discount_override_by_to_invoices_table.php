<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS-07 (tenant DB): the user who approved a total discount above the store's cap (the permission
 * holder or the manager-PIN approver). NULL when the discount was within the cap, and on history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices') || Schema::hasColumn('invoices', 'discount_override_by')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_override_by')->nullable()->index()->after('discount_amount');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices') || ! Schema::hasColumn('invoices', 'discount_override_by')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['discount_override_by']);
            $table->dropColumn('discount_override_by');
        });
    }
};
