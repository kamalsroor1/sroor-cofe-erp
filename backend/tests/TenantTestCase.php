<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Store;
use Illuminate\Auth\AuthManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Stancl\Tenancy\Contracts\Tenant;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\InteractsWithTenants;

/**
 * IDEN-4.8 base class for multi-tenant tests (database-per-tenant).
 *
 * Unlike Tests\TestCase it does NOT copy the tenant migrations into the central
 * database: the central DB only has central tables, and every tenant gets its own
 * database through createTenant(). Anything that silently relies on tenant tables
 * existing in central context fails here, as it would in production.
 *
 * Every HTTP call behaves like a fresh PHP-FPM request:
 *  - tenancy is ended before the request (the request resolves its own tenant from
 *    X-Tenant / Host) and the test's previous context is restored afterwards;
 *  - auth guards are forgotten after the request, so the next request never reuses
 *    the previously authenticated user (actingAs() therefore applies to one request);
 *  - for api/* calls the in-memory session store is flushed afterwards (API routes do
 *    not start a session in production, so nothing written there can survive).
 *
 * New tests written by any track extend this class (phase-1-plan §3.1); migrating the
 * existing suite onto it is QA-4.
 */
abstract class TenantTestCase extends BaseTestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    /**
     * Obvious dummy phone for fixtures (see Tests\Unit\NoHardcodedRealPhonesTest).
     * Same value as Tests\TestCase::ADMIN_PHONE so migrated tests keep compiling.
     */
    protected const ADMIN_PHONE = '01000000500';

    /**
     * Pin the RefreshDatabase transaction to the central connection by name. With the
     * default (null) the rollback would resolve the *current* default connection, which
     * is the tenant connection if a test failed while tenancy was initialized.
     *
     * @return array<int, string>
     */
    protected function connectionsToTransact(): array
    {
        return [(string) config('tenancy.database.central_connection', config('database.default'))];
    }

    /**
     * @param  string  $method
     * @param  string  $uri
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $cookies
     * @param  array<string, mixed>  $files
     * @param  array<string, mixed>  $server
     * @param  string|null  $content
     * @return TestResponse<Response>
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        /** @var Tenant|null $previous */
        $previous = tenancy()->initialized ? tenancy()->tenant : null;

        $this->endTenancy();

        try {
            return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        } finally {
            $this->endTenancy();
            $this->resetPerRequestState($uri);

            if ($previous !== null) {
                tenancy()->initialize($previous);
            }
        }
    }

    /**
     * QA-4 helper for migrated suites: keep $tenant initialized for the rest of the test
     * (fixtures and DB assertions then hit the tenant database) and send `X-Tenant` with
     * every request, so Bearer tokens minted inside the tenant resolve there. Requests
     * still resolve their own tenant: call() ends tenancy around each one and restores
     * it afterwards. createTenant() ends tenancy, so call this again after creating
     * another tenant.
     */
    protected function useTenantForTest(Tenant $tenant): void
    {
        tenancy()->initialize($tenant);
        $this->withHeaders(['X-Tenant' => (string) $tenant->getTenantKey()]);
    }

    /**
     * The harness main store of the CURRENT tenant, updated with a test's own fixture
     * attributes (name, code…) instead of creating a second `is_main` store.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function adoptMainStore(array $attributes = []): Store
    {
        if (! tenancy()->initialized) {
            throw new \RuntimeException('adoptMainStore() needs an initialized tenant (useTenantForTest()).');
        }

        $store = Store::query()->where('is_main', true)->orderBy('id')->firstOrFail();
        $store->update(array_merge($attributes, ['is_main' => true]));

        return $store->refresh();
    }

    private function resetPerRequestState(string $uri): void
    {
        $this->app->make(AuthManager::class)->forgetGuards();

        $path = ltrim((string) parse_url($uri, PHP_URL_PATH), '/');
        if (str_starts_with($path, 'api/') && $this->app->bound('session.store')) {
            $this->app->make('session.store')->flush();
        }
    }
}
