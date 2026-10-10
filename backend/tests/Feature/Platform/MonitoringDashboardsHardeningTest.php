<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Http\Middleware\EnsureCentralContext;
use App\Providers\TelescopeServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Telescope\Telescope;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * Security audit (W2 lane 3I), LOW:
 *  - Telescope and Pulse routes run EnsureCentralContext (tenant host / tenancy / a tenant
 *    identifier => 404) before their own Authorize middleware;
 *  - Laravel's default "APP_ENV=local lets anyone into Telescope" is gone: the viewTelescope
 *    gate (central super admin) applies unless the environment is local AND
 *    TELESCOPE_LOCAL_OPEN=true.
 */
final class MonitoringDashboardsHardeningTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    /** @return array<string, array{0: string}> */
    public static function dashboards(): array
    {
        return ['telescope' => ['telescope.middleware'], 'pulse' => ['pulse.middleware']];
    }

    #[DataProvider('dashboards')]
    public function test_dashboard_routes_run_ensure_central_context_before_authorize(string $key): void
    {
        $middleware = config($key);

        $this->assertIsArray($middleware);
        $position = array_search(EnsureCentralContext::class, $middleware, true);
        $this->assertIsInt($position, $key.' must contain EnsureCentralContext');
        $this->assertSame(count($middleware) - 2, $position, 'EnsureCentralContext must run right before Authorize.');
    }

    public function test_local_environment_no_longer_opens_telescope_by_default(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'local');
        config(['telescope.local_open' => false]);

        $this->assertFalse(TelescopeServiceProvider::localOpen());
        $this->assertFalse(Telescope::check(Request::create('/telescope')), 'A guest must not pass in local.');
    }

    public function test_the_explicit_flag_opens_telescope_in_local_only(): void
    {
        config(['telescope.local_open' => true]);

        $this->app->detectEnvironment(static fn (): string => 'local');
        $this->assertTrue(TelescopeServiceProvider::localOpen());
        $this->assertTrue(Telescope::check(Request::create('/telescope')));

        foreach (['production', 'staging', 'testing'] as $environment) {
            $this->app->detectEnvironment(static fn (): string => $environment);
            $this->assertFalse(TelescopeServiceProvider::localOpen(), $environment);
            $this->assertFalse(Telescope::check(Request::create('/telescope')), $environment);
        }
    }

    public function test_a_string_flag_is_not_enough(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'local');
        // Only a real boolean true (the config file casts TELESCOPE_LOCAL_OPEN) opens it.
        config(['telescope.local_open' => 'yes']);

        $this->assertFalse(TelescopeServiceProvider::localOpen());
    }

    public function test_the_gate_still_admits_a_central_super_admin_in_local(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'local');
        config(['telescope.local_open' => false]);
        $operator = $this->centralSuperAdmin();

        // The gate reads the default guard's user (unchanged behaviour); sign the operator in there.
        $this->actingAs($operator, 'central_web');
        Auth::shouldUse('central_web');

        $this->assertTrue(Telescope::check(Request::create('/telescope')));
    }
}
