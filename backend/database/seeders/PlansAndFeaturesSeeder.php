<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Database\Seeders\Catalog\CatalogWriter;
use Illuminate\Database\Seeder;

/**
 * ENTI-1.8: seeds the approved SaaS catalog (plan features, plans, add-ons and which
 * add-on is offered on which plan) into the CENTRAL database.
 *
 * Safe to run on every deploy and any number of times: it only creates what is missing and
 * merges missing keys (new feature keys into plans.features, a missing name_key). It never
 * overwrites a price, limit, flag or name that a super-admin edited. The approved prices
 * reach an existing installation once, through the data migration
 * 2026_10_10_200610_apply_approved_plan_catalog.
 *
 * The values live in Database\Seeders\Catalog\{FeatureCatalog, PlanCatalog, AddonCatalog};
 * names and descriptions in lang/{ar,en}/plans.php. Prices exclude VAT.
 */
class PlansAndFeaturesSeeder extends Seeder
{
    public function run(): void
    {
        // The central connection, pinned by UsesCentralConnection even if a tenant is initialised.
        (new CatalogWriter((new Plan)->getConnection()))->seedMissing();
    }
}
