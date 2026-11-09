<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PRD-10: quotations.manage has no routes, model or screen, so it is hidden from the plan
 * catalog (plan_features.is_public = false). The key and every plans.features value stay
 * untouched, so stored plan data remains valid.
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

        DB::table('plan_features')->where('key', 'quotations.manage')->update(['is_public' => $public]);
    }
};
