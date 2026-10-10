<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Models\AppVersion;
use App\Models\CentralPersonalAccessToken;
use App\Models\Plan;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * Security audit (W2 lane 3I), MEDIUM: the remaining dangerous control-plane writes demand a
 * recent second factor (RequireRecentTwoFactor, 15 min) like tenant destroy / DB config:
 * tenant toggle-status / override-feature / update-units, plan update, platform settings
 * update, app-version store / toggle-active / destroy.
 *
 *  - full central token without a recent proof        → 403 central_auth.step_up_required;
 *  - a stale proof (older than the TTL)               → same 403;
 *  - a fresh proof                                    → the request reaches the endpoint;
 *  - a read-only `support` operator                   → plain 403 from `can:` (no step-up prompt).
 *
 * CentralRouteSecurityGateTest pins the middleware on the route list; this test hits them.
 */
final class CentralDangerousWritesStepUpTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
    }

    /**
     * method, path template ({tenant}, {plan}, {version}), body.
     *
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public static function dangerousWrites(): array
    {
        return [
            'tenant toggle-status' => ['POST', '/api/v1/super-admin/tenants/{tenant}/toggle-status', ['status' => 'suspended', 'reason' => 'other']],
            'tenant override-feature' => ['POST', '/api/v1/super-admin/tenants/{tenant}/override-feature', ['feature_key' => 'custom_branding']],
            'tenant update-units' => ['POST', '/api/v1/super-admin/tenants/{tenant}/update-units', ['units' => ['كجم']]],
            'plan update' => ['PUT', '/api/v1/super-admin/plans/{plan}', ['name' => 'x']],
            'platform settings update' => ['POST', '/api/v1/super-admin/settings', ['platform_name' => 'x']],
            'app-version store' => ['POST', '/api/v1/super-admin/app-versions', []],
            'app-version toggle-active' => ['PATCH', '/api/v1/super-admin/app-versions/{version}/toggle-active', []],
            'app-version destroy' => ['DELETE', '/api/v1/super-admin/app-versions/{version}', []],
        ];
    }

    private function uri(string $template): string
    {
        $tenant = str_contains($template, '{tenant}') ? (string) $this->createTenant()->getTenantKey() : '';
        $plan = str_contains($template, '{plan}') ? (string) $this->plan()->getKey() : '';
        $version = str_contains($template, '{version}') ? (string) $this->appVersion()->getKey() : '';

        return str_replace(['{tenant}', '{plan}', '{version}'], [$tenant, $plan, $version], $template);
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('dangerousWrites')]
    public function test_without_a_recent_second_factor_it_is_403_step_up_required(string $method, string $template, array $body): void
    {
        $uri = $this->uri($template);
        $before = $this->snapshot();

        $this->json($method, $uri, $body, $this->centralHeaders($this->centralSuperAdmin()))
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'central_auth.step_up_required');

        $this->assertSame($before, $this->snapshot(), 'Nothing may change without the step-up.');
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('dangerousWrites')]
    public function test_a_stale_second_factor_is_403_step_up_required(string $method, string $template, array $body): void
    {
        $uri = $this->uri($template);
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin(), now()->subHour());

        $this->json($method, $uri, $body, $headers)
            ->assertForbidden()
            ->assertJsonPath('error_code', 'central_auth.step_up_required');
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('dangerousWrites')]
    public function test_a_recent_second_factor_reaches_the_endpoint(string $method, string $template, array $body): void
    {
        $uri = $this->uri($template);

        $response = $this->json($method, $uri, $body, $this->steppedUpCentralHeaders($this->centralSuperAdmin()));

        // Past the gate the endpoint answers itself (200 or its own 422 for the minimal bodies).
        $this->assertContains($response->getStatusCode(), [200, 201, 422], (string) $response->getContent());
        $this->assertNotSame('central_auth.step_up_required', $response->json('error_code'));
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('dangerousWrites')]
    public function test_support_gets_a_plain_403_not_a_step_up_prompt(string $method, string $template, array $body): void
    {
        $uri = $this->uri($template);

        $this->json($method, $uri, $body, $this->steppedUpCentralHeaders($this->centralSupport()))
            ->assertForbidden()
            ->assertJsonMissingPath('error_code');
    }

    public function test_step_up_is_checked_against_the_calling_token_only(): void
    {
        // A proof on ANOTHER token of the same operator does not lift the gate.
        $operator = $this->centralSuperAdmin();
        $this->steppedUpCentralHeaders($operator);
        $plain = $this->centralHeaders($operator);
        $this->assertSame(2, CentralPersonalAccessToken::query()->where('tokenable_id', $operator->getKey())->count());

        $this->postJson('/api/v1/super-admin/settings', ['platform_name' => 'x'], $plain)
            ->assertForbidden()
            ->assertJsonPath('error_code', 'central_auth.step_up_required');
    }

    private function plan(): Plan
    {
        return Plan::query()->create([
            'name' => 'باقة اختبار',
            'slug' => 'step-up-'.Str::lower(Str::random(6)),
            'price_monthly' => '100.000',
            'price_yearly' => '1000.000',
            'max_users' => 5,
            'max_stores' => 1,
            'max_items' => 100,
            'max_invoices_per_month' => 1000,
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 9,
            'features' => [],
        ]);
    }

    private function appVersion(): AppVersion
    {
        return AppVersion::query()->create([
            'platform' => 'android',
            'version_name' => '9.9.'.random_int(1, 999),
            'version_code' => random_int(10000, 99999),
            'release_notes_ar' => 'ملاحظات',
            'is_active' => true,
        ]);
    }

    /** @return array{versions: int, active_versions: int, plans: string} */
    private function snapshot(): array
    {
        return [
            'versions' => AppVersion::query()->count(),
            'active_versions' => AppVersion::query()->where('is_active', true)->count(),
            'plans' => (string) json_encode(Plan::query()->orderBy('id')->get(['id', 'name'])->toArray()),
        ];
    }
}
