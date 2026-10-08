<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Tenant;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * CORE-1 contract: TenantCache explicit scopes.
 *
 * - keyFor/versionFor/bumpFor($tenantId) address one tenant's cache from any
 *   context (central super-admin code invalidating tenant data).
 * - centralKey/centralVersion/centralBump address platform data identically
 *   inside and outside an initialized tenancy.
 * - key()/version()/bump() keep deriving the scope from the current context.
 *
 * Tenancy is toggled by hand (no bootstrappers) so every context shares the
 * same array cache store, which is the worst case for a scope mix-up.
 */
class TenantCacheScopeTest extends TestCase
{
    private Tenant $tenantX;

    private Tenant $tenantY;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->tenantX = new Tenant(['id' => 'tenant-x']);
        $this->tenantY = new Tenant(['id' => 'tenant-y']);
    }

    protected function tearDown(): void
    {
        $this->actAsTenant(null);

        parent::tearDown();
    }

    private function actAsTenant(?Tenant $tenant): void
    {
        tenancy()->tenant = $tenant;
        tenancy()->initialized = $tenant !== null;
    }

    public function test_key_for_matches_implicit_key_inside_that_tenant(): void
    {
        $this->actAsTenant(null);
        $explicit = TenantCache::keyFor('tenant-x', 'entitlements');

        $this->actAsTenant($this->tenantX);
        $this->assertSame($explicit, TenantCache::key('entitlements'));
        $this->assertSame($explicit, TenantCache::keyFor('tenant-x', 'entitlements'));

        $this->actAsTenant($this->tenantY);
        $this->assertSame($explicit, TenantCache::keyFor('tenant-x', 'entitlements'));
        $this->assertNotSame($explicit, TenantCache::key('entitlements'));
    }

    public function test_central_bump_for_tenant_is_seen_by_the_next_tenant_read(): void
    {
        $this->actAsTenant($this->tenantX);
        $before = TenantCache::version('entitlements');

        $this->actAsTenant(null);
        TenantCache::bumpFor('tenant-x', 'entitlements');
        $bumped = TenantCache::versionFor('tenant-x', 'entitlements');

        $this->actAsTenant($this->tenantX);
        $after = TenantCache::version('entitlements');

        $this->assertNotSame($before, $after);
        $this->assertSame($bumped, $after);
    }

    public function test_bump_for_one_tenant_does_not_touch_other_tenants_or_central(): void
    {
        $this->actAsTenant(null);
        TenantCache::bumpFor('tenant-y', 'entitlements');
        TenantCache::centralBump('entitlements');
        $yBefore = TenantCache::versionFor('tenant-y', 'entitlements');
        $centralBefore = TenantCache::centralVersion('entitlements');
        $implicitCentralBefore = TenantCache::version('entitlements');

        TenantCache::bumpFor('tenant-x', 'entitlements');

        $this->assertSame($yBefore, TenantCache::versionFor('tenant-y', 'entitlements'));
        $this->assertSame($centralBefore, TenantCache::centralVersion('entitlements'));
        $this->assertSame($implicitCentralBefore, TenantCache::version('entitlements'));
        $this->assertNotSame('1', TenantCache::versionFor('tenant-x', 'entitlements'));
    }

    public function test_implicit_bump_inside_tenant_stays_inside_that_tenant(): void
    {
        $this->actAsTenant(null);
        $yBefore = TenantCache::versionFor('tenant-y', 'erp_pnl');
        $centralBefore = TenantCache::centralVersion('erp_pnl');

        $this->actAsTenant($this->tenantX);
        TenantCache::bump('erp_pnl');
        $xVersion = TenantCache::version('erp_pnl');

        $this->actAsTenant(null);
        $this->assertSame($xVersion, TenantCache::versionFor('tenant-x', 'erp_pnl'));
        $this->assertSame($yBefore, TenantCache::versionFor('tenant-y', 'erp_pnl'));
        $this->assertSame($centralBefore, TenantCache::centralVersion('erp_pnl'));
    }

    public function test_central_key_is_identical_with_tenancy_initialized_and_ended(): void
    {
        $this->actAsTenant(null);
        $outside = TenantCache::centralKey('platform_branding');

        $this->actAsTenant($this->tenantX);
        $insideX = TenantCache::centralKey('platform_branding');

        $this->actAsTenant($this->tenantY);
        $insideY = TenantCache::centralKey('platform_branding');

        $this->assertSame($outside, $insideX);
        $this->assertSame($outside, $insideY);
        $this->assertNotSame($outside, TenantCache::key('platform_branding'));
    }

    public function test_implicit_key_in_central_context_equals_central_key(): void
    {
        $this->actAsTenant(null);

        $this->assertSame(TenantCache::centralKey('x'), TenantCache::key('x'));
    }

    public function test_central_bump_from_central_is_seen_inside_tenant_request(): void
    {
        $this->actAsTenant($this->tenantX);
        $before = TenantCache::centralVersion('platform_branding');
        Cache::forever(TenantCache::centralKey('platform_branding:v'.$before), 'Old Name');

        $this->actAsTenant(null);
        TenantCache::centralBump('platform_branding');

        $this->actAsTenant($this->tenantX);
        $after = TenantCache::centralVersion('platform_branding');

        $this->assertNotSame($before, $after);
        $this->assertNull(Cache::get(TenantCache::centralKey('platform_branding:v'.$after)));
    }

    public function test_central_bump_inside_tenant_does_not_move_that_tenant_version(): void
    {
        $this->actAsTenant($this->tenantX);
        $tenantBefore = TenantCache::version('platform_branding');

        TenantCache::centralBump('platform_branding');

        $this->assertSame($tenantBefore, TenantCache::version('platform_branding'));
        $this->assertNotSame('1', TenantCache::centralVersion('platform_branding'));
    }

    public function test_central_scope_cannot_collide_with_a_tenant_named_central(): void
    {
        $this->actAsTenant(null);

        $this->assertNotSame(TenantCache::centralKey('x'), TenantCache::keyFor('central', 'x'));

        $this->actAsTenant(new Tenant(['id' => 'central']));
        $this->assertNotSame(TenantCache::centralKey('x'), TenantCache::key('x'));
    }

    public function test_key_for_rejects_blank_tenant_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TenantCache::keyFor('  ', 'x');
    }

    public function test_bump_for_rejects_blank_tenant_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TenantCache::bumpFor('', 'x');
    }
}
