<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN-EDIT (central): an add-on a plan INCLUDES (plan_addon.included_quantity > 0) can be
 * included only with one billing cycle, e.g. onboarding free with a YEARLY Enterprise
 * subscription. NULL = included whatever the cycle. Display / support data: entitlements never
 * read it (only service add-ons without features or limits may be included, UpdatePlanRequest).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plan_addon') || Schema::hasColumn('plan_addon', 'included_billing_cycle')) {
            return;
        }

        Schema::table('plan_addon', function (Blueprint $table): void {
            $table->string('included_billing_cycle', 20)->nullable()->after('included_quantity');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('plan_addon') || ! Schema::hasColumn('plan_addon', 'included_billing_cycle')) {
            return;
        }

        Schema::table('plan_addon', function (Blueprint $table): void {
            $table->dropColumn('included_billing_cycle');
        });
    }
};
