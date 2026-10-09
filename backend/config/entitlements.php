<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Tenant entitlements (ENTI-2.2)
|--------------------------------------------------------------------------
|
| App\Services\Entitlements\TenantEntitlementService is the single source of truth:
| features = plan ∪ active add-ons ∪ super-admin overrides, limits = plan + Σ add-ons
| (NULL = unlimited). Pennant (config/pennant.php, store "array") is an API layer on top
| of it only (Q-E1).
|
*/

return [

    /*
     | Legacy feature key => canonical key. Read in both directions: asking for the legacy
     | key answers for the canonical one, and a legacy key stored in plans.features or
     | tenants.enabled_features grants the canonical one (ENTI-1.8 renamed the data; this
     | keeps old callers and old rows working).
     */
    'aliases' => [
        'blender.access' => 'mixes.manage',
    ],

    /*
     | Feature keys defined in Pennant (Feature::active('reports.advanced')). Mirrors
     | Database\Seeders\Catalog\FeatureCatalog::keys(); TenantEntitlementServiceTest fails
     | when the two drift apart. Aliases above are defined too.
     */
    'features' => [
        'pos.access',
        'invoices.create',
        'invoices.edit',
        'whatsapp.share',
        'quotations.manage',
        'pos.offline',
        'items.manage',
        'items.movements',
        'transfers.manage',
        'mixes.manage',
        'purchases.manage',
        'purchases.reorder',
        'expenses.manage',
        'payments.manage',
        'shifts.manage',
        'treasury.view',
        'returns.manage',
        'reports.basic',
        'reports.advanced',
        'reports.export',
        'audit.logs',
        'printing.thermal',
        'printing.a4',
        'telegram.notifications',
        'api.access',
        'custom.domain',
    ],

    /*
     | Seconds a resolved entitlement set stays cached. Correctness does not depend on it:
     | every subscription / add-on / plan / override change bumps the tenant's version
     | (TenantCache::bumpFor), which makes the old entry unreachable at once.
     */
    'cache_ttl' => (int) env('ENTITLEMENTS_CACHE_TTL', 3600),
];
