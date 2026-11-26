<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRAL (#232 spec D): end of the tenant's UNSETTLED 48-hour temporary-access window, copied
 * from billing_invoices.temporary_access_ends_at when the window opens and cleared when it is
 * settled. The lifecycle policy reads it from the tenant row on every request (no extra query);
 * the invoice columns stay the record. A past value means the window expired unsettled.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tenants', 'temporary_access_ends_at')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            $table->timestamp('temporary_access_ends_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tenants', 'temporary_access_ends_at')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('temporary_access_ends_at');
        });
    }
};
