<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRAL (#232 spec B, UI §1): the shop's "renew too" choice on a plan-change request (upgrade +
 * one full cycle of the new plan after the current paid end). The console issues with it unless the
 * operator overrides `with_renewal`. Existing rows = false (upgrade only, the old behaviour).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('plan_change_requests', 'renew')) {
            return;
        }

        Schema::table('plan_change_requests', function (Blueprint $table): void {
            $table->boolean('renew')->default(false)->after('billing_cycle');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('plan_change_requests', 'renew')) {
            return;
        }

        Schema::table('plan_change_requests', function (Blueprint $table): void {
            $table->dropColumn('renew');
        });
    }
};
