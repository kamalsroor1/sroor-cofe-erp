<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Http\Middleware\StoreScope;
use App\Models\CentralUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Horizon\Horizon;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * W2-B3 lane 3G: Horizon used to answer 500 for a platform operator because StoreScope (web
 * middleware group) called getCurrentStore() on a CentralUser, and the gate was typed ?User
 * and read the tenant-side `web` guard. Now:
 *  - StoreScope is a no-op in central context and for non-tenant users;
 *  - Horizon::auth reads the `central_web` session guard and requires
 *    super_admin.monitoring.view on the `central` guard.
 */
final class HorizonCentralWebGuardTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const CENTRAL_URL = 'http://localhost';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
    }

    public function test_super_admin_on_central_web_opens_the_dashboard_and_its_api(): void
    {
        $superAdmin = $this->centralSuperAdmin();

        $this->actingAs($superAdmin, 'central_web')->get(self::CENTRAL_URL.'/horizon')->assertOk();
        $this->actingAs($superAdmin, 'central_web')->get(self::CENTRAL_URL.'/horizon/api/stats')->assertOk();
    }

    public function test_guest_and_operator_without_monitoring_permission_are_403(): void
    {
        $this->get(self::CENTRAL_URL.'/horizon/api/stats')->assertForbidden();

        $this->actingAs($this->centralOperator(null), 'central_web')
            ->get(self::CENTRAL_URL.'/horizon/api/stats')
            ->assertForbidden();
    }

    public function test_tenant_user_on_the_web_guard_is_403_even_with_a_colliding_id(): void
    {
        $superAdmin = $this->centralSuperAdmin();
        // A `users` row (web guard) sharing the operator's primary key must not borrow its rights.
        $legacy = $this->legacyUsersTableSuperAdmin(['id' => $superAdmin->getKey()]);

        $this->actingAs($legacy, 'web')->get(self::CENTRAL_URL.'/horizon')->assertForbidden();
    }

    public function test_auth_callback_reads_only_the_central_web_guard(): void
    {
        $superAdmin = $this->centralSuperAdmin();

        $request = Request::create(self::CENTRAL_URL.'/horizon');
        $request->setUserResolver(static fn (?string $guard = null): ?CentralUser => $guard === 'central_web' ? $superAdmin : null);
        $this->assertTrue(Horizon::check($request));

        $wrongGuard = Request::create(self::CENTRAL_URL.'/horizon');
        $wrongGuard->setUserResolver(static fn (?string $guard = null): ?CentralUser => $guard === 'central_web' ? null : $superAdmin);
        $this->assertFalse(Horizon::check($wrongGuard));
    }

    public function test_store_scope_is_a_no_op_for_a_central_user(): void
    {
        $this->endTenancy();
        Auth::shouldUse('central_web');
        Auth::guard('central_web')->setUser($this->centralSuperAdmin());

        $response = (new StoreScope)->handle(
            Request::create(self::CENTRAL_URL.'/horizon'),
            static fn (): Response => new Response('ok'),
        );

        $this->assertSame('ok', $response->getContent());
        $this->assertFalse(session()->has('current_store_id'));
    }
}
