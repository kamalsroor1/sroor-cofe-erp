<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN-10 (central): `tenants.signup_source` = how the tenant was created
 * (App\Enums\TenantSignupSource): `self_signup` (public signup form) or `console` (an
 * operator, the default). A self-signup trial that ends unpaid falls back to the platform's
 * free plan instead of going read-only.
 *
 * Backfill: every tenant an APPROVED signup request points at (signup_requests.tenant_id) was
 * created from the public form, so it is `self_signup`; every other row keeps `console`.
 * Literal values on purpose: a migration must not change when the enum does.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        if (! Schema::hasColumn('tenants', 'signup_source')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->string('signup_source', 20)->nullable()->default('console')->index();
            });
        }

        DB::table('tenants')->whereNull('signup_source')->update(['signup_source' => 'console']);

        if (Schema::hasTable('signup_requests') && Schema::hasColumn('signup_requests', 'tenant_id')) {
            $ids = DB::table('signup_requests')
                ->where('status', 'approved')
                ->whereNotNull('tenant_id')
                ->pluck('tenant_id')
                ->unique()
                ->values();

            foreach ($ids->chunk(500) as $chunk) {
                DB::table('tenants')->whereIn('id', $chunk->all())->update(['signup_source' => 'self_signup']);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasColumn('tenants', 'signup_source')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex(['signup_source']);
            $table->dropColumn('signup_source');
        });
    }
};
