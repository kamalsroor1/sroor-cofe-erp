<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Exceptions\CentralConnectionNotConfiguredException;

/**
 * Pins a model to the CENTRAL database connection.
 *
 * When stancl/tenancy initializes a tenant it switches the default connection to the
 * tenant database, so a central model without this trait would silently query the
 * tenant DB (missing table at best, cross-tenant leak at worst). Every central model
 * (plans, plan_features, subscriptions, billing_*, …) must use this trait; the guard
 * test of ENTI-1.9 enforces it.
 *
 * There is deliberately NO fallback to config('database.default'): inside a tenant
 * request that is the tenant connection. An empty `tenancy.database.central_connection`
 * is a configuration error and fails loudly.
 */
trait UsesCentralConnection
{
    public function getConnectionName(): ?string
    {
        $connection = config('tenancy.database.central_connection');

        if (! is_string($connection) || trim($connection) === '') {
            throw CentralConnectionNotConfiguredException::forModel(static::class);
        }

        return $connection;
    }
}
