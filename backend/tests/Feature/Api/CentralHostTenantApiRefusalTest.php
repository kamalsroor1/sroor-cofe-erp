<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Actions\Auth\ApiLoginAction;
use App\DTOs\Auth\ApiLoginDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * SEC-1 (M1, W2 batch 6): with no tenant resolved (central host without X-Tenant, or an unknown
 * host) every tenant-API route of /api/v1 answers 404 instead of running against the CENTRAL
 * database. Only the central-safe allowlist (ping, translations, branding, app updates, login
 * options) and the public workspace resolver answer. ApiLoginAction never reads central users.
 */
final class CentralHostTenantApiRefusalTest extends TenantTestCase
{
    private const CENTRAL_PHONE = '01000007900';

    /** @return array<string, array{0: string, 1: string}> */
    public static function tenantApiRoutes(): array
    {
        return [
            'login' => ['POST', '/api/v1/auth/login'],
            'activity logs' => ['GET', '/api/v1/activity-logs'],
            'activity logs csv' => ['GET', '/api/v1/activity-logs/export-csv'],
            'me' => ['GET', '/api/v1/auth/me'],
            'items' => ['GET', '/api/v1/items'],
            'system context' => ['GET', '/api/v1/system/context'],
            'checkout' => ['POST', '/api/v1/pos/checkout'],
        ];
    }

    #[DataProvider('tenantApiRoutes')]
    public function test_tenant_api_routes_are_404_on_the_central_host_without_a_tenant(string $method, string $uri): void
    {
        $this->json($method, $uri, ['login' => self::CENTRAL_PHONE, 'password' => 'password'], ['Accept' => 'application/json'])
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_login_on_the_central_host_never_authenticates_a_central_user_nor_mints_a_token(): void
    {
        $central = (string) config('tenancy.database.central_connection', config('database.default'));
        DB::connection($central)->table('users')->insert([
            'name' => 'legacy central admin',
            'phone' => self::CENTRAL_PHONE,
            'email' => 'legacy-central@harness.test',
            'password' => Hash::make('password'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tokensBefore = DB::connection($central)->table('personal_access_tokens')->count();

        $this->postJson('/api/v1/auth/login', ['login' => self::CENTRAL_PHONE, 'password' => 'password'])
            ->assertStatus(404)
            ->assertJsonMissingPath('data.token');

        $this->assertSame($tokensBefore, DB::connection($central)->table('personal_access_tokens')->count());
    }

    public function test_an_unknown_host_is_404_too(): void
    {
        $this->postJson('http://no-such-shop.harness.test/api/v1/auth/login', ['login' => self::CENTRAL_PHONE, 'password' => 'password'])
            ->assertStatus(404);
    }

    public function test_central_safe_routes_still_answer_without_a_tenant(): void
    {
        $this->getJson('/api/v1/ping')->assertOk();
        $this->getJson('/api/v1/system/translations?locale=ar')->assertOk();
        $this->getJson('/api/v1/auth/options')->assertOk();
        $this->assertNotSame(404, $this->getJson('/api/v1/branding')->getStatusCode());
        $this->assertNotSame(404, $this->getJson('/api/v1/app/check-update?platform=android&version_code=1')->getStatusCode());
    }

    public function test_the_same_login_works_when_the_tenant_is_named(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->tenantAdmin($tenant);

        $this->postJson('/api/v1/auth/login', ['login' => $admin->phone, 'password' => 'password'], ['X-Tenant' => (string) $tenant->getTenantKey()])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_the_login_action_refuses_without_tenancy_and_reads_no_users_table(): void
    {
        $this->endTenancy();
        $central = (string) config('tenancy.database.central_connection', config('database.default'));
        DB::connection($central)->enableQueryLog();

        try {
            app(ApiLoginAction::class)->execute(new ApiLoginDTO(self::CENTRAL_PHONE, 'password'));
            $this->fail('ApiLoginAction must refuse without an initialized tenant.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('login', $e->errors());
        }

        $queries = array_column(DB::connection($central)->getQueryLog(), 'query');
        $this->assertSame([], array_values(array_filter($queries, static fn (string $sql): bool => str_contains($sql, '"users"') || str_contains($sql, '`users`'))));
    }
}
