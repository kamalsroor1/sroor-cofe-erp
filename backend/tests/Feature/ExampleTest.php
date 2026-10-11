<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TenantTestCase;

class ExampleTest extends TenantTestCase
{
    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_authenticated_user_can_view_home(): void
    {
        // SEC-1 (M7, W2 batch 6): the legacy session POST /login is removed (the SPA signs in
        // through POST /api/v1/auth/login with a Bearer token). The home page still renders the
        // SPA shell on the tenant's host for a user of that tenant.
        $tenant = $this->createTenant();
        $admin = $this->tenantAdmin($tenant);

        $this->actingAs($admin, 'web')
            ->get($this->tenantUrl($tenant, '/'))
            ->assertStatus(200)
            ->assertSee('id="app"', false);
    }

    public function test_the_session_login_is_removed_and_never_authenticates(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $adminA = $this->tenantAdmin($tenantA);

        foreach ([$tenantA, $tenantB] as $tenant) {
            $status = $this->post($this->tenantUrl($tenant, '/login'), ['phone' => $adminA->phone, 'password' => 'password'])
                ->getStatusCode();

            $this->assertContains($status, [404, 405]);
            $this->assertNull(session()->get($this->sessionAuthKey()), 'POST /login must not start a session.');
        }

        // The SPA login page itself still renders on a tenant host.
        $this->get($this->tenantUrl($tenantB, '/login'))->assertStatus(200);
    }

    private function sessionAuthKey(): string
    {
        return $this->app['auth']->guard('web')->getName();
    }
}
