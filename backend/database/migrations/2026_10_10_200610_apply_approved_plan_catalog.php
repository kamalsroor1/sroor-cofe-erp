<?php

declare(strict_types=1);

use Database\Seeders\Catalog\CatalogWriter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ENTI-1.8 (central): ONE-TIME data migration to the approved catalog.
 *
 * REQUIRES A CENTRAL DATABASE BACKUP BEFORE DEPLOY (phase-1-plan ENTI-1.8).
 *
 * up():
 * 1. renames the feature key `blender.access` -> `mixes.manage` in plan_features.key, in the
 *    keys of plans.features and in tenants.enabled_features;
 * 2. on an installation that already has catalog plans (free/basic/pro/enterprise), writes
 *    the approved prices (VAT excluded), founder prices, limits (NULL = unlimited), trial days,
 *    feature matrix, the 26-key feature registry (18 core, pos.offline and custom.domain
 *    hidden) and the add-on catalog with its plan availability. Super-admin custom plans
 *    (other slugs) and feature keys the catalog does not know are left untouched.
 *    Existing subscriptions are NOT repriced: subscriptions.amount is the frozen price of the
 *    paid term (Q-E5, price_locked is set by the OPS-12 backfill).
 * 3. on an empty database (fresh install, test databases) nothing is written: the idempotent
 *    PlansAndFeaturesSeeder creates the catalog.
 *
 * down(): reverses the key rename only (mixes.manage -> blender.access in the same three
 * places). The previous prices/limits/descriptions are NOT restored on purpose: they were the
 * pre-approval values and a rollback must never silently reprice plans; restore them from
 * the pre-deploy backup if really needed. New catalog rows (features, add-ons) are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->catalogTablesExist()) {
            return;
        }

        (new CatalogWriter(DB::connection()))->applyApproved();
    }

    public function down(): void
    {
        if (! $this->catalogTablesExist()) {
            return;
        }

        (new CatalogWriter(DB::connection()))->restoreLegacyFeatureKeys();
    }

    private function catalogTablesExist(): bool
    {
        $schema = DB::connection()->getSchemaBuilder();

        foreach (['plans', 'plan_features', 'addons', 'plan_addon'] as $table) {
            if (! $schema->hasTable($table)) {
                return false;
            }
        }

        return true;
    }
};
