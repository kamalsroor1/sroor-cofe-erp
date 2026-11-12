<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Customer;
use App\Models\Tenant;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * PERF-04, manual result (1) of 2026-11-12 automated (the request-level part):
 *  - a token of tenant A presented on tenant B's DOMAIN is 401, for reads and writes, and
 *    nothing is written in either tenant (TenantHarnessSmokeTest covers the X-Tenant header
 *    path for reads);
 *  - an X-Tenant header naming another tenant than the request host: the host (domain) wins;
 *  - interleaved requests on three tenants (customers, invoices, dashboard, context,
 *    treasury) each answer with the caller's own data.
 *
 * The same burst with real parallel processes runs against a real server in
 * scripts/perf/load-test.sh (scenario "Isolation burst"): a PHPUnit worker process cannot see
 * the central tenants/domains rows, which live inside this test's uncommitted transaction.
 */
#[Group('mysql')]
final class CrossTenantDomainIsolationTest extends TenantTestCase
{
    public function test_a_token_of_tenant_a_on_tenant_b_domain_is_401_for_reads_and_writes(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();
        $headers = $this->hostHeaders($a);

        foreach (['/api/v1/auth/me', '/api/v1/customers', '/api/v1/invoices', '/api/v1/dashboard', '/api/v1/treasury/summary'] as $uri) {
            $this->getJson($this->tenantUrl($b, $uri), $headers)->assertUnauthorized();
        }
        $this->postJson($this->tenantUrl($b, '/api/v1/customers'), ['name' => 'عميل متسلل'], $headers)->assertUnauthorized();
        $this->postJson($this->tenantUrl($b, '/api/v1/pos/checkout'), ['customer_id' => 1, 'items' => []], $headers)->assertUnauthorized();

        foreach ([$a, $b] as $tenant) {
            $this->assertSame(0, $this->inTenant($tenant, fn (): int => Customer::query()->where('name', 'عميل متسلل')->count()));
        }

        // Sanity: the same token works on its own domain.
        $this->getJson($this->tenantUrl($a, '/api/v1/auth/me'), $headers)->assertOk();
    }

    public function test_the_domain_wins_over_a_different_x_tenant_header(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();
        $this->inTenant($a, fn () => Customer::query()->create(['name' => 'عميل أ فقط', 'current_balance' => '0.000', 'is_active' => true]));
        $this->inTenant($b, fn () => Customer::query()->create(['name' => 'عميل ب فقط', 'current_balance' => '0.000', 'is_active' => true]));

        // Host of B, header naming A, B's token: B's data.
        $asB = array_merge($this->hostHeaders($b), ['X-Tenant' => (string) $a->getTenantKey()]);
        $this->getJson($this->tenantUrl($b, '/api/v1/auth/me'), $asB)
            ->assertOk()
            ->assertJsonPath('data.user.email', $this->tenantAdmin($b)->email);
        $this->getJson($this->tenantUrl($b, '/api/v1/customers?search=فقط'), $asB)
            ->assertOk()
            ->assertJsonFragment(['name' => 'عميل ب فقط'])
            ->assertJsonMissing(['name' => 'عميل أ فقط']);

        // Host of B, header naming A, A's token: the header cannot pull the request into A.
        $asA = array_merge($this->hostHeaders($a), ['X-Tenant' => (string) $a->getTenantKey()]);
        $this->getJson($this->tenantUrl($b, '/api/v1/auth/me'), $asA)->assertUnauthorized();
        $this->postJson($this->tenantUrl($b, '/api/v1/customers'), ['name' => 'عميل عبر الهيدر'], $asA)->assertUnauthorized();
        $this->assertSame(0, $this->inTenant($a, fn (): int => Customer::query()->where('name', 'عميل عبر الهيدر')->count()));
        $this->assertSame(0, $this->inTenant($b, fn (): int => Customer::query()->where('name', 'عميل عبر الهيدر')->count()));
    }

    public function test_interleaved_requests_on_three_tenants_each_get_their_own_data(): void
    {
        $tenants = [$this->createTenant(), $this->createTenant(), $this->createTenant()];
        $own = [];
        foreach ($tenants as $n => $tenant) {
            $own[$n] = 'عميل المحل '.$n;
            $this->inTenant($tenant, fn () => Customer::query()->create(['name' => $own[$n], 'current_balance' => '0.000', 'is_active' => true]));
        }
        $headers = array_map(fn (Tenant $t): array => $this->tenantHeaders($t), $tenants);

        for ($round = 0; $round < 3; $round++) {
            foreach ($tenants as $n => $tenant) {
                $customers = $this->getJson('/api/v1/customers', $headers[$n])->assertOk();
                foreach ($own as $m => $name) {
                    $m === $n ? $customers->assertJsonFragment(['name' => $name]) : $customers->assertJsonMissing(['name' => $name]);
                }
                $this->getJson('/api/v1/auth/me', $headers[$n])->assertOk()->assertJsonPath('data.user.email', $this->tenantAdmin($tenant)->email);
                $this->getJson('/api/v1/system/context', $headers[$n])->assertOk()->assertJsonPath('data.tenant.id', (string) $tenant->getTenantKey());
                $this->getJson('/api/v1/invoices', $headers[$n])->assertOk();
                $this->getJson('/api/v1/dashboard', $headers[$n])->assertOk();
                $this->getJson('/api/v1/treasury/summary', $headers[$n])->assertOk();
            }
        }
    }

    /**
     * Headers for host-based resolution (no X-Tenant), with $tenant's admin token.
     *
     * @return array<string, string>
     */
    private function hostHeaders(Tenant $tenant): array
    {
        $headers = $this->tenantHeaders($tenant);
        unset($headers['X-Tenant']);

        return $headers;
    }
}
