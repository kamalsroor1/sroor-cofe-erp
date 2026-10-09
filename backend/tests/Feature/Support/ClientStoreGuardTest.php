<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ClientStoreGuard;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * Unit-level contract of App\Support\ClientStoreGuard (W2 batch 2): how a client-supplied
 * store id (body / query) is parsed and access-checked, and how concrete() picks ONE store
 * without ever turning `all` into store 0. Also stands in for the unrouted legacy Blade
 * controllers (ExportController, ReportPrintController) that use the same guard.
 */
final class ClientStoreGuardTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $mainId;

    private int $otherId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->mainId = (int) $this->tenantStore($this->tenant)->id;
        $this->otherId = $this->inTenant($this->tenant, fn (): int => (int) Store::query()->create([
            'name' => 'فرع آخر', 'code' => 'BR-X', 'type' => 'retail', 'is_main' => false, 'is_active' => true,
        ])->id);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    private function request(User $user, array $query = [], array $headers = []): Request
    {
        $request = Request::create('/api/v1/probe', 'GET', $query);
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    /** @return iterable<string, array{mixed}> */
    public static function absentValues(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'zero string' => ['0'];
    }

    #[DataProvider('absentValues')]
    public function test_absent_values_mean_not_sent(mixed $value): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');

        $this->inTenant($this->tenant, function () use ($cashier, $value): void {
            $query = $value === null ? [] : ['store_id' => $value];
            $this->assertNull(ClientStoreGuard::verified($this->request($cashier, $query)));
        });
    }

    public function test_accessible_store_is_returned_as_int_and_others_are_403(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');

        $this->inTenant($this->tenant, function () use ($cashier): void {
            $this->assertSame($this->mainId, ClientStoreGuard::verified($this->request($cashier, ['store_id' => (string) $this->mainId])));

            foreach ([(string) $this->otherId, 'all', '1abc', '-1', '1.5'] as $value) {
                try {
                    ClientStoreGuard::verified($this->request($cashier, ['store_id' => $value]));
                    $this->fail("store_id={$value} must be refused");
                } catch (HttpResponseException $e) {
                    $this->assertSame(403, $e->getResponse()->getStatusCode());
                    $this->assertSame(ClientStoreGuard::ERROR_CODE, json_decode((string) $e->getResponse()->getContent(), true)['error_code']);
                }
            }
        });
    }

    public function test_admin_gets_any_store_and_all(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $admin = $this->tenantAdmin($this->tenant);

            $this->assertSame($this->otherId, ClientStoreGuard::verified($this->request($admin, ['store_id' => (string) $this->otherId])));
            $this->assertSame('all', ClientStoreGuard::verified($this->request($admin, ['store_id' => 'all'])));
        });
    }

    public function test_from_store_id_key_is_checked_too(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');

        $this->inTenant($this->tenant, function () use ($cashier): void {
            $this->expectException(HttpResponseException::class);
            ClientStoreGuard::verified($this->request($cashier, ['from_store_id' => (string) $this->otherId]), 'from_store_id');
        });
    }

    public function test_concrete_prefers_the_header_and_still_checks_the_client_value(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');

        $this->inTenant($this->tenant, function () use ($cashier): void {
            $admin = $this->tenantAdmin($this->tenant);
            $header = ['X-Store-Id' => (string) $this->mainId];

            $this->assertSame($this->mainId, ClientStoreGuard::concrete($this->request($admin, ['store_id' => (string) $this->otherId], $header)));
            $this->assertSame($this->otherId, ClientStoreGuard::concrete($this->request($admin, ['store_id' => (string) $this->otherId], $header), preferClient: true));
            $this->assertNull(ClientStoreGuard::concrete($this->request($admin)));

            // A valid header does not launder a forbidden client value.
            $this->expectException(HttpResponseException::class);
            ClientStoreGuard::concrete($this->request($cashier, ['store_id' => (string) $this->otherId], $header));
        });
    }

    public function test_concrete_never_turns_all_into_store_zero(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $admin = $this->tenantAdmin($this->tenant);

            foreach ([
                [[], ['X-Store-Id' => 'all'], false],
                [['store_id' => 'all'], [], false],
                [['store_id' => 'all'], ['X-Store-Id' => (string) $this->mainId], true],
            ] as [$query, $headers, $preferClient]) {
                try {
                    ClientStoreGuard::concrete($this->request($admin, $query, $headers), $preferClient);
                    $this->fail('`all` must be a 422 on a single-store endpoint');
                } catch (ValidationException $e) {
                    $this->assertArrayHasKey('store_id', $e->errors());
                }
            }

            // Header `all` + a concrete client store: the concrete store is used.
            $this->assertSame($this->otherId, ClientStoreGuard::concrete($this->request($admin, ['store_id' => (string) $this->otherId], ['X-Store-Id' => 'all'])));
        });
    }
}
