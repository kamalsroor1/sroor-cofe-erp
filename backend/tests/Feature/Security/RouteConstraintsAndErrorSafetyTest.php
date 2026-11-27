<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Tenant;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * GEN-204 (#737):
 * - Non-numeric route IDs across API routes must return 404 (never 500 TypeError).
 * - Unhandled 500s with APP_DEBUG=false must return generic JSON without stack traces,
 *   file paths, exception class names, or SQL queries.
 */
final class RouteConstraintsAndErrorSafetyTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private Tenant $tenant;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->headers = $this->tenantHeaders($this->tenant);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function nonNumericTenantApiEndpoints(): array
    {
        return [
            'invoices show' => ['GET', '/api/v1/invoices/abc'],
            'invoices cancel' => ['POST', '/api/v1/invoices/abc/cancel'],
            'invoices receipt-link' => ['POST', '/api/v1/invoices/abc/receipt-link'],
            'customers show' => ['GET', '/api/v1/customers/abc'],
            'customers update' => ['PUT', '/api/v1/customers/abc'],
            'customers destroy' => ['DELETE', '/api/v1/customers/abc'],
            'customers toggle-active' => ['PATCH', '/api/v1/customers/abc/toggle-active'],
            'customers collect-payment' => ['POST', '/api/v1/customers/abc/collect-payment'],
            'customers statement' => ['GET', '/api/v1/customers/abc/statement'],
            'suppliers show' => ['GET', '/api/v1/suppliers/abc'],
            'suppliers update' => ['PUT', '/api/v1/suppliers/abc'],
            'suppliers destroy' => ['DELETE', '/api/v1/suppliers/abc'],
            'suppliers toggle-active' => ['PATCH', '/api/v1/suppliers/abc/toggle-active'],
            'suppliers pay' => ['POST', '/api/v1/suppliers/abc/pay'],
            'suppliers statement' => ['GET', '/api/v1/suppliers/abc/statement'],
            'purchases show' => ['GET', '/api/v1/purchases/abc'],
            'purchases cancel' => ['POST', '/api/v1/purchases/abc/cancel'],
            'items show' => ['GET', '/api/v1/items/abc'],
            'items update' => ['PUT', '/api/v1/items/abc'],
            'items destroy' => ['DELETE', '/api/v1/items/abc'],
            'items toggle-active' => ['PATCH', '/api/v1/items/abc/toggle-active'],
            'items adjust-stock' => ['POST', '/api/v1/items/abc/adjust-stock'],
            'items movements' => ['GET', '/api/v1/items/abc/movements'],
            'categories update' => ['PUT', '/api/v1/categories/abc'],
            'categories destroy' => ['DELETE', '/api/v1/categories/abc'],
            'stores show' => ['GET', '/api/v1/stores/abc'],
            'stores update' => ['PUT', '/api/v1/stores/abc'],
            'stores destroy' => ['DELETE', '/api/v1/stores/abc'],
            'stores toggle-active' => ['PATCH', '/api/v1/stores/abc/toggle-active'],
            'stores assign-users' => ['POST', '/api/v1/stores/abc/assign-users'],
            'shifts z-report' => ['GET', '/api/v1/shifts/abc/z-report'],
            'expenses show' => ['GET', '/api/v1/expenses/abc'],
            'expenses update' => ['PUT', '/api/v1/expenses/abc'],
            'expenses destroy' => ['DELETE', '/api/v1/expenses/abc'],
            'reports item card' => ['GET', '/api/v1/reports/items/abc/card'],
            'returns show' => ['GET', '/api/v1/returns/abc'],
            'returns destroy' => ['DELETE', '/api/v1/returns/abc'],
            'transfers show' => ['GET', '/api/v1/transfers/abc'],
            'transfers cancel' => ['POST', '/api/v1/transfers/abc/cancel'],
            'stock-counts show' => ['GET', '/api/v1/stock-counts/abc'],
            'users show' => ['GET', '/api/v1/users/abc'],
            'users update' => ['PUT', '/api/v1/users/abc'],
            'users destroy' => ['DELETE', '/api/v1/users/abc'],
            'users toggle-active' => ['PATCH', '/api/v1/users/abc/toggle-active'],
            'roles update-permissions' => ['PUT', '/api/v1/roles/abc/permissions'],
            'trash restore' => ['POST', '/api/v1/trash/customers/abc/restore'],
            'trash force delete' => ['DELETE', '/api/v1/trash/customers/abc/force'],
        ];
    }

    #[DataProvider('nonNumericTenantApiEndpoints')]
    public function test_non_numeric_route_id_returns_404_instead_of_500(string $method, string $uri): void
    {
        $response = $this->json($method, $uri, [], $this->headers);

        $response->assertStatus(404);
    }

    public function test_central_app_version_non_numeric_id_returns_404(): void
    {
        $this->useCentralAdminHost();
        $operator = $this->centralOperator();
        $headers = $this->steppedUpCentralHeaders($operator);

        $this->patchJson($this->centralAdminUrl('/api/v1/super-admin/app-versions/abc/toggle-active'), [], $headers)
            ->assertStatus(404);

        $this->deleteJson($this->centralAdminUrl('/api/v1/super-admin/app-versions/abc'), [], $headers)
            ->assertStatus(404);
    }

    public function test_unhandled_500_with_app_debug_false_does_not_leak_stack_trace_or_sql(): void
    {
        config(['app.debug' => false]);

        $handler = $this->app->make(ExceptionHandler::class);
        $request = Request::create('/api/v1/invoices', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);

        $runtimeResponse = $handler->render($request, new RuntimeException('Internal secret path /var/www/secret.php'));
        $this->assertSame(500, $runtimeResponse->getStatusCode());
        $runtimeBody = (string) $runtimeResponse->getContent();
        $runtimeJson = json_decode($runtimeBody, true);
        $this->assertSame(['message' => 'Server Error'], $runtimeJson);
        $this->assertStringNotContainsString('secret.php', $runtimeBody);
        $this->assertStringNotContainsString('trace', $runtimeBody);

        $pdo = new PDOException('SQLSTATE[42S22]: Column not found: 1054 Unknown column secret_col');
        $pdo->errorInfo = ['42S22', 1054, 'Unknown column secret_col'];
        $queryException = new QueryException('mysql', 'select secret_col from invoices where id = ?', [1], $pdo);

        $sqlResponse = $handler->render($request, $queryException);
        $this->assertSame(500, $sqlResponse->getStatusCode());
        $sqlBody = (string) $sqlResponse->getContent();
        $sqlJson = json_decode($sqlBody, true);
        $this->assertSame(['message' => 'Server Error'], $sqlJson);
        $this->assertStringNotContainsString('SQLSTATE', $sqlBody);
        $this->assertStringNotContainsString('secret_col', $sqlBody);
        $this->assertStringNotContainsString('trace', $sqlBody);
    }
}
