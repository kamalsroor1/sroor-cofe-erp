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
        // QA-4: the only web-session login is the tenant host's /login (the central host has
        // none: SuperAdminLoginAction is not routed), so "an authenticated user" is a tenant
        // user. Real login on the tenant's host, then the home page, instead of actingAs().
        $tenant = $this->createTenant();
        $admin = $this->tenantAdmin($tenant);

        $this->post($this->tenantUrl($tenant, '/login'), ['phone' => $admin->phone, 'password' => 'password'])
            ->assertRedirect();
        $this->assertSame($admin->id, (int) session()->get($this->sessionAuthKey()), 'The tenant session login did not authenticate the tenant admin.');

        $this->get($this->tenantUrl($tenant, '/'))
            ->assertStatus(200)
            ->assertSee('id="app"', false);
    }

    public function test_a_session_from_one_tenant_is_a_guest_on_another_tenant(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $adminA = $this->tenantAdmin($tenantA);

        $this->post($this->tenantUrl($tenantA, '/login'), ['phone' => $adminA->phone, 'password' => 'password'])
            ->assertRedirect();

        // B's guest-only login page must still render: A's user id does not exist in B's database.
        $this->get($this->tenantUrl($tenantB, '/login'))->assertStatus(200);

        // And A's credentials never authenticate on B.
        $this->post($this->tenantUrl($tenantB, '/login'), ['phone' => $adminA->phone, 'password' => 'password'])
            ->assertSessionHasErrors('phone');
    }

    private function sessionAuthKey(): string
    {
        return $this->app['auth']->guard('web')->getName();
    }
}
