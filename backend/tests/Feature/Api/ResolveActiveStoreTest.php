<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\ApiTokenAuth;
use App\Http\Middleware\ResolveActiveStore;
use App\Http\Middleware\ResolveApiTenancy;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ActiveStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TenantTestCase;

/**
 * STOR-1: ResolveActiveStore + ActiveStore. The middleware is not mounted on any production
 * route yet (STOR-2), so a probe route with the real tenant API stack is registered here.
 */
final class ResolveActiveStoreTest extends TenantTestCase
{
    private const PROBE = '/api/v1/__stor1/active-store';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', ResolveApiTenancy::class, ApiTokenAuth::class, 'store.active'])
            ->match(['GET', 'POST'], ltrim(self::PROBE, '/'), fn (ActiveStore $active) => response()->json([
                'store_id' => $active->id(),
                'all' => $active->isAll(),
                'session_store_id' => session('current_store_id'),
            ]));
    }

    private function createStore(Tenant $tenant, string $code, bool $active = true): int
    {
        return $this->inTenant($tenant, fn (): int => (int) Store::query()->create([
            'name' => 'فرع '.$code,
            'code' => $code,
            'type' => 'retail',
            'is_main' => false,
            'is_active' => $active,
        ])->id);
    }

    private function assign(Tenant $tenant, User $user, int $storeId): void
    {
        $this->inTenant($tenant, fn () => User::query()->findOrFail($user->id)->stores()->attach($storeId));
    }

    /**
     * @return array<string, string>
     */
    private function headers(Tenant $tenant, ?User $user, int|string|null $store): array
    {
        $headers = $this->tenantHeaders($tenant, $user);
        $headers['X-Locale'] = 'en';
        unset($headers['X-Store-Id']);

        if ($store !== null) {
            $headers['X-Store-Id'] = (string) $store;
        }

        return $headers;
    }

    private function deniedMessage(): string
    {
        return trans('common.store_access_denied', [], 'en');
    }

    public function test_alias_is_registered(): void
    {
        $this->assertSame(ResolveActiveStore::class, Route::getMiddleware()['store.active'] ?? null);
    }

    public function test_no_header_resolves_the_users_default_store(): void
    {
        $tenant = $this->createTenant();
        $mainId = (int) $this->tenantStore($tenant)->id;
        $cashier = $this->createTenantUser($tenant, 'cashier');

        $this->getJson(self::PROBE, $this->headers($tenant, $cashier, null))
            ->assertOk()
            ->assertJsonPath('store_id', $mainId)
            ->assertJsonPath('all', false)
            ->assertJsonPath('session_store_id', $mainId);
    }

    public function test_no_header_skips_an_inactive_default_store_and_uses_an_assigned_one(): void
    {
        $tenant = $this->createTenant();
        $inactiveId = $this->createStore($tenant, 'OFF-01', active: false);
        $assignedId = $this->createStore($tenant, 'ON-01');
        $cashier = $this->createTenantUser($tenant, 'cashier', [], ['default_store_id' => $inactiveId]);
        $this->assign($tenant, $cashier, $assignedId);

        $this->getJson(self::PROBE, $this->headers($tenant, $cashier, null))
            ->assertOk()
            ->assertJsonPath('store_id', $assignedId);
    }

    public function test_no_header_and_no_usable_store_is_forbidden(): void
    {
        $tenant = $this->createTenant();
        $cashier = $this->createTenantUser($tenant, 'cashier', [], ['default_store_id' => null]);

        $this->getJson(self::PROBE, $this->headers($tenant, $cashier, null))
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', $this->deniedMessage());
    }

    public function test_header_for_an_assigned_store_is_accepted(): void
    {
        $tenant = $this->createTenant();
        $branchId = $this->createStore($tenant, 'BR-01');
        $cashier = $this->createTenantUser($tenant, 'cashier');
        $this->assign($tenant, $cashier, $branchId);

        $this->getJson(self::PROBE, $this->headers($tenant, $cashier, $branchId))
            ->assertOk()
            ->assertJsonPath('store_id', $branchId)
            ->assertJsonPath('session_store_id', $branchId);
    }

    public function test_header_for_a_store_the_user_cannot_access_is_forbidden(): void
    {
        $tenant = $this->createTenant();
        $otherId = $this->createStore($tenant, 'OTHER-01');
        $cashier = $this->createTenantUser($tenant, 'cashier');

        $this->getJson(self::PROBE, $this->headers($tenant, $cashier, $otherId))
            ->assertStatus(403)
            ->assertJsonPath('message', $this->deniedMessage());
    }

    public function test_body_or_query_store_id_never_wins_over_the_header(): void
    {
        $tenant = $this->createTenant();
        $mainId = (int) $this->tenantStore($tenant)->id;
        $otherId = $this->createStore($tenant, 'OTHER-01');
        $cashier = $this->createTenantUser($tenant, 'cashier');

        $this->postJson(self::PROBE, ['store_id' => $otherId], $this->headers($tenant, $cashier, $mainId))
            ->assertOk()
            ->assertJsonPath('store_id', $mainId);

        $this->getJson(self::PROBE.'?store_id='.$otherId, $this->headers($tenant, $cashier, null))
            ->assertOk()
            ->assertJsonPath('store_id', $mainId);
    }

    public function test_all_is_forbidden_for_a_cashier(): void
    {
        $tenant = $this->createTenant();
        $cashier = $this->createTenantUser($tenant, 'cashier');

        $this->getJson(self::PROBE, $this->headers($tenant, $cashier, 'all'))
            ->assertStatus(403)
            ->assertJsonPath('message', $this->deniedMessage());
    }

    public function test_all_is_allowed_for_admin_and_for_stores_manage(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createTenantUser($tenant, null, ['stores.manage']);

        $this->getJson(self::PROBE, $this->headers($tenant, null, 'all'))
            ->assertOk()
            ->assertJsonPath('all', true)
            ->assertJsonPath('store_id', null)
            ->assertJsonPath('session_store_id', null);

        $this->getJson(self::PROBE, $this->headers($tenant, $manager, 'all'))
            ->assertOk()
            ->assertJsonPath('all', true);
    }

    public function test_admin_may_select_any_existing_store_but_not_a_missing_one(): void
    {
        $tenant = $this->createTenant();
        $otherId = $this->createStore($tenant, 'OTHER-01');

        $this->getJson(self::PROBE, $this->headers($tenant, null, $otherId))
            ->assertOk()
            ->assertJsonPath('store_id', $otherId);

        $this->getJson(self::PROBE, $this->headers($tenant, null, $otherId + 999))
            ->assertStatus(403);
    }

    public function test_malformed_header_values_are_forbidden_not_silently_ignored(): void
    {
        $tenant = $this->createTenant();
        $mainId = (int) $this->tenantStore($tenant)->id;
        $cashier = $this->createTenantUser($tenant, 'cashier');

        foreach (['0', '-1', 'abc', $mainId.'.5', $mainId.'abc'] as $value) {
            $this->getJson(self::PROBE, $this->headers($tenant, $cashier, $value))
                ->assertStatus(403);
        }
    }

    /**
     * The middleware enforces the rules on its own, without relying on ApiTokenAuth's check.
     */
    public function test_middleware_rejects_a_forbidden_store_on_its_own(): void
    {
        $tenant = $this->createTenant();
        $otherId = $this->createStore($tenant, 'OTHER-01');
        $cashier = $this->createTenantUser($tenant, 'cashier');

        $statuses = $this->inTenant($tenant, function () use ($cashier, $otherId): array {
            $user = User::query()->findOrFail($cashier->id);
            $run = function (?string $header) use ($user): int {
                $request = Request::create('/probe', 'GET');
                if ($header !== null) {
                    $request->headers->set('X-Store-Id', $header);
                }
                $request->setUserResolver(fn () => $user);

                $middleware = new ResolveActiveStore(new ActiveStore);

                return $middleware->handle($request, fn () => response()->json(['ok' => true]))->getStatusCode();
            };

            return [
                'forbidden' => $run((string) $otherId),
                'all' => $run('all'),
                'default' => $run(null),
            ];
        });

        $this->assertSame(['forbidden' => 403, 'all' => 403, 'default' => 200], $statuses);
    }

    public function test_guest_reaching_the_middleware_gets_401(): void
    {
        $request = Request::create('/probe', 'GET');
        $request->setUserResolver(fn () => null);

        $response = (new ResolveActiveStore(new ActiveStore))->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_active_store_is_a_fresh_instance_per_request_scope(): void
    {
        $first = $this->app->make(ActiveStore::class);
        $this->assertSame($first, $this->app->make(ActiveStore::class));

        $first->setAll();
        $this->app->forgetScopedInstances();

        $this->assertFalse($this->app->make(ActiveStore::class)->isResolved());
    }
}
