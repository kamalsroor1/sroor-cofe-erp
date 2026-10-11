<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\TenantStatus;
use App\Http\Middleware\RequireRecentTwoFactor;
use App\Models\Tenant;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * N6: on the archive routes (throttle:6,1) the step-up check runs BEFORE the throttle, so
 * requests refused for a missing second factor never spend the destructive route's budget.
 * Laravel's middleware priority hoists ThrottleRequests, so the order is pinned through the
 * priority list (bootstrap/app.php), not just the route array.
 */
final class StepUpRunsBeforeArchiveThrottleTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    /**
     * @return array<string, array{string}>
     */
    public static function archiveRoutes(): array
    {
        return [
            'archive' => ['api.super_admin.tenants.destroy'],
            'unarchive' => ['api.super_admin.tenants.unarchive'],
        ];
    }

    #[DataProvider('archiveRoutes')]
    public function test_the_effective_order_runs_step_up_before_the_throttle(string $name): void
    {
        $router = $this->app->make(Router::class);
        $route = $router->getRoutes()->getByName($name);
        $this->assertNotNull($route);

        $names = array_map(
            static fn (mixed $m): string => is_string($m) ? explode(':', $m)[0] : $m::class,
            $router->gatherRouteMiddleware($route),
        );

        $stepUp = array_search(RequireRecentTwoFactor::class, $names, true);
        $throttle = array_search(ThrottleRequests::class, $names, true);
        $this->assertIsInt($stepUp, $name.' has no step-up check');
        $this->assertIsInt($throttle, $name.' has no throttle');
        $this->assertLessThan($throttle, $stepUp, $name.': step-up must run before the throttle');
    }

    public function test_requests_without_a_second_factor_never_spend_the_archive_budget(): void
    {
        $this->seedCentralPlatformRoles();
        Bus::fake();
        Queue::fake();

        $id = 'n6-'.Str::lower(Str::random(8));
        Tenant::query()->create([
            'id' => $id,
            'name' => 'مستأجر الخطوة الثانية',
            'slug' => $id,
            'email' => $id.'@sroor.test',
            'status' => TenantStatus::Active->value,
            'tenancy_create_database' => false,
        ]);
        $operator = $this->centralSuperAdmin();
        $uri = '/api/v1/super-admin/tenants/'.$id;

        for ($i = 0; $i < 8; $i++) {
            $this->deleteJson($uri, ['confirm_code' => $id], $this->centralHeaders($operator))
                ->assertStatus(403)
                ->assertJsonPath('error_code', 'central_auth.step_up_required');
        }

        // After the step-up the operator still has the full budget: the request reaches the
        // controller (an active tenant is refused with 409, never 429).
        $this->deleteJson($uri, [
            'confirm_code' => $id,
            'expected_status' => TenantStatus::Suspended->value,
        ], $this->steppedUpCentralHeaders($operator))
            ->assertStatus(409);
    }
}
