<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\Entitlements\FeatureUnavailableException;
use App\Services\Entitlements\PlanEnforcer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware `feature:<key>[,<key>...]` (SEC-06 / BE-12): the tenant's entitlements
 * (plan + active add-ons + super-admin overrides) must include EVERY listed feature,
 * otherwise 403 `subscription.feature_unavailable` (default deny).
 *
 * Mount it on tenant API routes only, after ApiTokenAuth, so unauthenticated callers get 401
 * first. Gate writes and module screens, never the reads other screens depend on.
 */
final class EnsureTenantFeature
{
    public function __construct(private readonly PlanEnforcer $enforcer) {}

    /**
     * @throws FeatureUnavailableException
     */
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        foreach ($features as $feature) {
            $this->enforcer->assertFeature($feature);
        }

        return $next($request);
    }
}
