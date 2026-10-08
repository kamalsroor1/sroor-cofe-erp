<?php

declare(strict_types=1);

namespace App\Exceptions;

use LogicException;

/**
 * Thrown when a central model resolves its connection while
 * `tenancy.database.central_connection` is empty.
 *
 * Falling back to the default connection would be silent and dangerous: while a tenant is
 * initialized the default connection IS the tenant database, so central rows (plans,
 * subscriptions, billing, media…) would be read from / written to the wrong database.
 * This is a deployment/configuration error, never a user flow, so the message is for
 * developers and operators (it is not rendered to end users with APP_DEBUG=false).
 */
final class CentralConnectionNotConfiguredException extends LogicException
{
    public static function forModel(string $model): self
    {
        return new self(
            "Central database connection is not configured: set tenancy.database.central_connection (DB_CONNECTION) before using the central model [{$model}]."
        );
    }
}
