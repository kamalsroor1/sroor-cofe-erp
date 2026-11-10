<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SEC-06 follow-up (owner decision, central DB): nothing enforces reports.advanced yet, so it is
 * hidden from the plan catalog (plan_features.is_public = false), like the not-enforced plan
 * limits (Plan::HIDDEN_LIMIT_COLUMNS). The key, plans.features values and tenant overrides stay.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->setPublic(false);
    }

    public function down(): void
    {
        $this->setPublic(true);
    }

    private function setPublic(bool $public): void
    {
        if (! Schema::hasTable('plan_features') || ! Schema::hasColumn('plan_features', 'is_public')) {
            return;
        }

        DB::table('plan_features')->where('key', 'reports.advanced')->update(['is_public' => $public]);
    }
};
