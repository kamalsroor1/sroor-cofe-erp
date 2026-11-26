<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRAL (#232 spec A): the ACTUAL transfer date-time of a manual payment, entered by the
 * super-admin at Verify. It starts the paid term of a tenant with no paid term running (not the
 * receipt upload time, not the verify time). Nullable: gateway payments and rows verified before
 * this column existed have none.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('billing_payments', 'transferred_at')) {
            return;
        }

        Schema::table('billing_payments', function (Blueprint $table): void {
            $table->timestamp('transferred_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('billing_payments', 'transferred_at')) {
            return;
        }

        Schema::table('billing_payments', function (Blueprint $table): void {
            $table->dropColumn('transferred_at');
        });
    }
};
