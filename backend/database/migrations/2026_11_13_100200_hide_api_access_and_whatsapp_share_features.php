<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PRD-10 follow-up (owner decision, central DB): api.access and whatsapp.share are hidden from
 * the plan catalog exactly like quotations.manage (plan_features.is_public = false), and
 * quotations.manage is no longer a core feature (is_core = false). Keys, plans.features values
 * and tenant overrides are untouched, so stored data stays valid and nobody loses access.
 */
return new class extends Migration
{
    private const HIDDEN = ['api.access', 'whatsapp.share'];

    public function up(): void
    {
        if (! $this->ready()) {
            return;
        }

        DB::table('plan_features')->whereIn('key', self::HIDDEN)->update(['is_public' => false]);
        DB::table('plan_features')->where('key', 'quotations.manage')->update(['is_core' => false, 'is_public' => false]);
    }

    public function down(): void
    {
        if (! $this->ready()) {
            return;
        }

        DB::table('plan_features')->whereIn('key', self::HIDDEN)->update(['is_public' => true]);
        DB::table('plan_features')->where('key', 'quotations.manage')->update(['is_core' => true]);
    }

    private function ready(): bool
    {
        return Schema::hasTable('plan_features')
            && Schema::hasColumn('plan_features', 'is_public')
            && Schema::hasColumn('plan_features', 'is_core');
    }
};
