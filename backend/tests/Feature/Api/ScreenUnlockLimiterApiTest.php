<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * Security review of SEC-21 (7): POST /api/v1/auth/unlock-screen used the anonymous
 * `throttle:10,1`, whose bucket is keyed by the user id alone. Exploit/defect before the fix: ten
 * unlock attempts also exhausted every other `throttle:N,1` route of that user (here
 * PUT /profile/pos-pin answered 429), and on a shared cache store the same user id in another
 * tenant shared the bucket. Now the route has its own named limiter keyed by tenant + user.
 */
final class ScreenUnlockLimiterApiTest extends TenantTestCase
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
    }

    public function test_the_unlock_endpoint_has_its_own_per_user_limit(): void
    {
        $user = $this->createTenantUser($this->tenant, 'cashier');
        $headers = $this->tenantHeaders($this->tenant, $user);
        $budget = (int) config('rate_limits.tenant_unlock_screen.per_user_per_minute');
        $this->assertSame(10, $budget);

        for ($i = 0; $i < $budget; $i++) {
            $this->postJson('/api/v1/auth/unlock-screen', ['password' => 'wrong-'.$i], $headers)->assertStatus(422);
        }
        $this->postJson('/api/v1/auth/unlock-screen', ['password' => 'password'], $headers)->assertStatus(429);

        // Another throttle:10,1 route of the same user is not affected any more.
        $this->putJson('/api/v1/profile/pos-pin', ['pin' => '12', 'current_password' => 'x'], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('pin');

        // Another user of the same shop has their own bucket.
        $other = $this->createTenantUser($this->tenant, 'cashier');
        $this->postJson('/api/v1/auth/unlock-screen', ['password' => 'password'], $this->tenantHeaders($this->tenant, $other))->assertOk();
    }

    public function test_the_route_uses_the_named_limiter(): void
    {
        $route = app('router')->getRoutes()->getByName('api.auth.unlock_screen');
        $this->assertNotNull($route);
        $this->assertContains('throttle:tenant-unlock-screen', $route->gatherMiddleware());
        $this->assertNotContains('throttle:10,1', $route->gatherMiddleware());
    }
}
