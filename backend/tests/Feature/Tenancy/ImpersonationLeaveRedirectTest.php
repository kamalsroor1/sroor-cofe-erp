<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use Illuminate\Support\Facades\Route;
use Tests\TenantTestCase;

/**
 * SEC-1 (M7 / L8, W2 batch 6): the stancl session impersonation entry (GET /impersonate/{token})
 * and its exit (POST /impersonate/leave) are removed from routes/tenant.php. Nothing in the
 * platform console used them (its "impersonate" button only opens the shop's host), and a
 * one-time token turning into a tenant web session is exactly the kind of path IDEN-2.6 replaces
 * with an audited, step-up impersonation. This used to test the leave redirect.
 */
final class ImpersonationLeaveRedirectTest extends TenantTestCase
{
    public function test_the_impersonation_routes_are_not_registered(): void
    {
        $this->assertFalse(Route::has('impersonate'));
        $this->assertFalse(Route::has('impersonate.leave'));
    }

    public function test_leaving_impersonation_is_no_longer_routed_on_a_tenant_host(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createTenantUser($tenant, 'admin');

        $status = $this->actingAs($user, 'web')
            ->withSession(['is_impersonating' => true, 'impersonated_by_super' => true])
            ->post($this->tenantUrl($tenant, '/impersonate/leave'))
            ->getStatusCode();

        $this->assertContains($status, [404, 405]);
    }

    public function test_an_impersonation_token_url_never_signs_anyone_in(): void
    {
        $tenant = $this->createTenant();

        // Falls through to the SPA shell (GET catch-all); no session login happens.
        $this->get($this->tenantUrl($tenant, '/impersonate/'.str_repeat('a', 40)));

        $this->assertGuest('web');
    }
}
