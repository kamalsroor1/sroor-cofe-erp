<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\TenantAccessLevel;
use App\Enums\TenantStatus;
use App\Exceptions\TenantLifecycleException;
use App\Models\Tenant;
use App\Support\Tenancy\TenantLifecyclePolicy;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * IDEN-3.5 / SEC-01 / BE-05: enforces the subscription lifecycle on the tenant API.
 *
 * Runs from ResolveApiTenancy right after tenancy is initialised on EVERY resolution path
 * (already initialised, X-Tenant header, host), so a client cannot dodge it by choosing
 * how it names the tenant. It reads the DERIVED status (TenantLifecyclePolicy::evaluate at
 * "now"), never the raw column, so enforcement does not depend on the daily sweep having run.
 *
 *  - full access (trial, active, past_due, and the grace days after a trial / paid period)
 *    passes;
 *  - read_only: GET / HEAD / OPTIONS pass, writes get 423 `subscription.read_only`
 *    (except WRITE_ALLOWLIST);
 *  - suspended / cancelled / archived: 403 `subscription.access_blocked` on everything
 *    except BLOCKED_ALLOWLIST (logout, /auth/me and the public shell endpoints the SPA
 *    needs to display the blocked state). Unauthenticated callers get the generic 403
 *    (no status revealed); the tenant web pages (routes/tenant.php) keep only the SPA
 *    shell (`login`, `tenant.spa`).
 *
 * Central (super-admin) routes never pass through here: they live in routes/central.php.
 */
final class EnsureTenantActive
{
    /** @var list<string> writes a read-only tenant may still make (session handling, branch switch) */
    public const WRITE_ALLOWLIST = [
        'api.auth.login',
        'api.auth.quick_login',
        'api.auth.logout',
        'api.stores.switch',
        // a tenant that turns read-only mid-shift must still be able to close the drawer
        'api.shifts.close',
        // password change must stay possible
        'api.profile.update',
        // SELL-01: a user forced to change their password must be able to, even read-only
        'api.auth.change_password',
        // pure calculation, writes nothing
        'api.coffee_blender.calculate',
        // Owner policy: unpaid -> read_only may still pay; suspended/archived are fully blocked (BLOCKED_ALLOWLIST).
        // PRD-04: a read-only shop must still be able to pay (receipt upload) and ask for a renewal invoice
        'api.billing.invoices.payments.store',
        'api.billing.renewal_invoice',
        'api.billing.plan_change_requests.store',
        'api.billing.plan_change_requests.cancel',
    ];

    /** @var list<string> routes a blocked tenant may still call so the SPA can show the state */
    public const BLOCKED_ALLOWLIST = ['api.auth.logout', 'api.auth.me', ...ResolveApiTenancy::CENTRAL_SAFE_ROUTES];

    /** @var list<string> web routes (routes/tenant.php) a blocked tenant may still load: the SPA shell that displays the state */
    public const WEB_BLOCKED_ALLOWLIST = ['login', 'tenant.spa'];

    public function __construct(private readonly TenantLifecyclePolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = function_exists('tenancy') && tenancy()->initialized ? tenancy()->tenant : null;

        if (! $tenant instanceof Tenant) {
            return $next($request);
        }

        $decision = $this->policy->evaluate($tenant->lifecycleSnapshot(), CarbonImmutable::now());

        return match ($decision->access()) {
            TenantAccessLevel::Full => $next($request),
            TenantAccessLevel::ReadOnly => $this->isWrite($request) && ! $request->routeIs(self::WRITE_ALLOWLIST)
                ? TenantLifecycleException::readOnly()->render($request)
                : $next($request),
            TenantAccessLevel::Blocked => $this->blocked($request, $next, $decision->effectiveStatus),
        };
    }

    private function blocked(Request $request, Closure $next, TenantStatus $status): Response
    {
        if (! $request->is('api/*')) {
            // Tenant web pages (print / export): only the SPA shell stays reachable.
            if ($request->routeIs(self::WEB_BLOCKED_ALLOWLIST)) {
                return $next($request);
            }

            abort(Response::HTTP_FORBIDDEN, (string) __('subscription.errors.access_blocked_generic'));
        }

        if ($request->routeIs(self::BLOCKED_ALLOWLIST)) {
            return $next($request);
        }

        // The specific status is revealed to authenticated callers only.
        $exception = $this->hasValidToken($request)
            ? TenantLifecycleException::blocked($status)
            : TenantLifecycleException::blockedGeneric();

        return $exception->render($request);
    }

    /** A live tenant-DB Sanctum token (tenancy is already initialised here). */
    private function hasValidToken(Request $request): bool
    {
        $token = $request->bearerToken() ?: $request->header('X-API-TOKEN');
        if (! is_string($token) || $token === '') {
            return false;
        }

        $accessToken = PersonalAccessToken::findToken($token);

        return $accessToken !== null && ($accessToken->expires_at === null || $accessToken->expires_at->isFuture());
    }

    private function isWrite(Request $request): bool
    {
        return ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
    }
}
