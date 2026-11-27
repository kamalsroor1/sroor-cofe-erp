<?php

declare(strict_types=1);

use App\Services\Entitlements\TenantEntitlementService;
use App\Support\PublicCatalogCache;
use App\Support\TenantCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BIL-002 (central DB, data only): rename legacy catalog `free` plan rows left with the old
 * trial name («التجربة» / "Trial") to «المجانية» and ensure `name_key` points to
 * `plans.plans.free.name` so console, tenant, and public endpoints stay consistent.
 */
return new class extends Migration
{
    private const SLUG = 'free';

    private const NAME_KEY = 'plans.plans.free.name';

    private const LEGACY_NAMES = ['التجربة', 'Trial', 'trial', 'Free'];

    private const NEW_NAME = 'المجانية';

    private const NEW_DESCRIPTION = 'مجانية على طول لمحل صغير: كاشير ومخزون ومشتريات وخزينة، بحدود صغيرة.';

    public function up(): void
    {
        if (! Schema::hasTable('plans')) {
            return;
        }

        $planId = DB::transaction(function (): ?int {
            $row = DB::table('plans')->where('slug', self::SLUG)->lockForUpdate()->first();
            if ($row === null) {
                return null;
            }

            $updates = [];

            if (Schema::hasColumn('plans', 'name_key') && ($row->name_key ?? null) !== self::NAME_KEY) {
                $updates['name_key'] = self::NAME_KEY;
            }

            $currentName = trim((string) ($row->name ?? ''));
            if ($currentName === '' || in_array($currentName, self::LEGACY_NAMES, true)) {
                $updates['name'] = self::NEW_NAME;
            }

            $currentDescription = trim((string) ($row->description ?? ''));
            if (Schema::hasColumn('plans', 'description') && ($currentDescription === '' || str_contains($currentDescription, 'تجربة') || str_contains($currentDescription, 'تجريبية') || str_contains(strtolower($currentDescription), 'trial'))) {
                $updates['description'] = self::NEW_DESCRIPTION;
            }

            if ($updates === []) {
                return null;
            }

            $updates['updated_at'] = now();
            DB::table('plans')->where('id', $row->id)->update($updates);

            return (int) $row->id;
        });

        if ($planId === null) {
            return;
        }

        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'plan_id')) {
            DB::table('tenants')->where('plan_id', $planId)->orderBy('id')->pluck('id')
                ->each(fn (mixed $id) => TenantCache::bumpFor((string) $id, TenantEntitlementService::CACHE_NAMESPACE));
        }

        PublicCatalogCache::forget();
    }

    public function down(): void
    {
        // Data correction migration; intentionally not reverting «المجانية» back to «التجربة».
    }
};
