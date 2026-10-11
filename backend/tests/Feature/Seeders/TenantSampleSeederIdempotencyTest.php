<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\DTOs\CreateTenantDTO;
use App\Models\Tenant;
use App\Services\TenantProvisionerService;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\TenantSampleSeeder;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * TenantSampleSeeder uses the same identity as scripts/local/provision-local-tenant.php:
 * tenant id/slug = SEED_DEMO_TENANT_SLUG (default "demo"), domains "<slug>.<central domain>"
 * (provisioner) + "<slug>.localhost". It is idempotent: a tenant already holding that id or
 * slug is never re-provisioned. (It used to look up a hardcoded "tenant_sroor" and then
 * provision "demo", so a second run crashed on the duplicate.)
 *
 * The provisioner is replaced by a recording fake: no tenant database is ever created here.
 */
final class TenantSampleSeederIdempotencyTest extends TenantTestCase
{
    private const ENV_KEY = 'SEED_DEMO_TENANT_SLUG';

    /** @var object{captured: CreateTenantDTO|null, calls: int} */
    private object $provisioner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clearEnv();
        $this->seed(PlansAndFeaturesSeeder::class);

        $this->provisioner = new class extends TenantProvisionerService
        {
            public ?CreateTenantDTO $captured = null;

            public int $calls = 0;

            public function __construct() {}

            public function provision(CreateTenantDTO $dto): Tenant
            {
                $this->captured = $dto;
                $this->calls++;

                // Never provision a real tenant database from a test.
                throw new RuntimeException('captured');
            }
        };
        $this->app->instance(TenantProvisionerService::class, $this->provisioner);
    }

    protected function tearDown(): void
    {
        $this->clearEnv();

        parent::tearDown();
    }

    private function setEnv(string $value): void
    {
        putenv(self::ENV_KEY.'='.$value);
        $_ENV[self::ENV_KEY] = $value;
        $_SERVER[self::ENV_KEY] = $value;
    }

    private function clearEnv(): void
    {
        putenv(self::ENV_KEY);
        unset($_ENV[self::ENV_KEY], $_SERVER[self::ENV_KEY]);
    }

    private function runSeeder(): void
    {
        try {
            $this->seed(TenantSampleSeeder::class);
        } catch (RuntimeException $e) {
            $this->assertSame('captured', $e->getMessage());
        }
    }

    public function test_default_identity_matches_the_local_provisioning_script(): void
    {
        $this->runSeeder();

        $dto = $this->provisioner->captured;
        $this->assertNotNull($dto, 'TenantSampleSeeder did not call the provisioner.');
        $this->assertSame('demo', $dto->slug);
        $this->assertSame('demo.localhost', $dto->customDomain);
    }

    public function test_the_slug_comes_from_the_environment_and_is_normalised(): void
    {
        $this->setEnv('  Sample Shop ');

        $this->runSeeder();

        $dto = $this->provisioner->captured;
        $this->assertNotNull($dto, 'TenantSampleSeeder did not call the provisioner.');
        $this->assertSame('sample-shop', $dto->slug);
        $this->assertSame('sample-shop.localhost', $dto->customDomain);
    }

    public function test_an_existing_tenant_with_that_id_is_left_untouched(): void
    {
        $tenant = $this->createTenant();
        $this->setEnv((string) $tenant->getTenantKey());
        $domainsBefore = $tenant->domains()->pluck('domain')->all();

        $this->runSeeder();
        $this->runSeeder();

        $this->assertSame(0, $this->provisioner->calls, 'An existing sample tenant was re-provisioned.');
        $this->assertSame($domainsBefore, $tenant->domains()->pluck('domain')->all());
    }

    public function test_an_existing_tenant_matched_by_slug_is_left_untouched(): void
    {
        $tenant = $this->createTenant();
        $tenant->forceFill(['slug' => 'sample-by-slug'])->save();
        $this->setEnv('sample-by-slug');

        $this->runSeeder();

        $this->assertSame(0, $this->provisioner->calls);
        $this->assertNull(Tenant::find('tenant_sroor'));
    }
}
