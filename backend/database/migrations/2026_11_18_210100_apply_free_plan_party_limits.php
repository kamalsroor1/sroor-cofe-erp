<?php

declare(strict_types=1);

use App\Services\Entitlements\TenantEntitlementService;
use App\Support\PublicCatalogCache;
use App\Support\TenantCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN-11 (central DB, data only): the catalog `free` plan caps customers at 50 and suppliers at
 * 20 (docs/03-architecture/free-plan-and-self-signup-2026-11-18.md section 3), on an installation
 * that already has the row. A fresh install / test DB gets the same values from the idempotent
 * PlansAndFeaturesSeeder (Database\Seeders\Catalog\PlanCatalog); every other plan stays NULL
 * (unlimited).
 *
 * - Each column is written only while it is still NULL, so a value a super-admin already chose
 *   is never overwritten.
 * - down() clears a column only when it still holds the value written here.
 * - Tenants on the plan get their entitlements cache flushed.
 * Literal values on purpose: a migration must not change when the catalog does.
 */
return new class extends Migration
{
    private const SLUG = 'free';

    /** @var array<string, int> */
    private const VALUES = [
        'max_customers' => 50,
        'max_suppliers' => 20,
    ];

    public function up(): void
    {
        if (! $this->ready()) {
            return;
        }

        $planId = DB::transaction(function (): ?int {
            $row = DB::table('plans')->where('slug', self::SLUG)->lockForUpdate()->first();
            if ($row === null) {
                return null;
            }

            $changes = [];
            foreach (self::VALUES as $column => $value) {
                if ($row->{$column} === null) {
                    $changes[$column] = $value;
                }
            }

            if ($changes === []) {
                return null;
            }

            DB::table('plans')->where('id', $row->id)->update($changes + ['updated_at' => now()]);

            return (int) $row->id;
        });

        $this->flushCaches($planId);
    }

    public function down(): void
    {
        if (! $this->ready()) {
            return;
        }

        $planId = DB::transaction(function (): ?int {
            $row = DB::table('plans')->where('slug', self::SLUG)->lockForUpdate()->first();
            if ($row === null) {
                return null;
            }

            $changes = [];
            foreach (self::VALUES as $column => $value) {
                if ($row->{$column} !== null && (int) $row->{$column} === $value) {
                    $changes[$column] = null;
                }
            }

            if ($changes === []) {
                return null;
            }

            DB::table('plans')->where('id', $row->id)->update($changes + ['updated_at' => now()]);

            return (int) $row->id;
        });

        $this->flushCaches($planId);
    }

    private function ready(): bool
    {
        return Schema::hasTable('plans')
            && Schema::hasColumn('plans', 'max_customers')
            && Schema::hasColumn('plans', 'max_suppliers');
    }

    private function flushCaches(?int $planId): void
    {
        if ($planId === null) {
            return;
        }

        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'plan_id')) {
            DB::table('tenants')->where('plan_id', $planId)->orderBy('id')->pluck('id')
                ->each(fn (mixed $id) => TenantCache::bumpFor((string) $id, TenantEntitlementService::CACHE_NAMESPACE));
        }

        PublicCatalogCache::forget();
    }
};
