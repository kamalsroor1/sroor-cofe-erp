<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Item;
use App\Models\Tenant;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Stancl\Tenancy\Events\CreatingDatabase;
use Stancl\Tenancy\Events\DatabaseCreated;
use Stancl\Tenancy\Events\DatabaseMigrated;
use Stancl\Tenancy\Events\MigratingDatabase;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\TenantTestCase;

/**
 * P0-OPS-1 — `tenant:populate-realistic-data` must refuse to run in production
 * before it resolves/creates a tenant, creates a database, or truncates anything.
 *
 * Contract for the interactive override (test b): with `--force-unsafe` in
 * production the command asks
 *   __('console.populate_realistic_data.confirm_production')
 * and aborts unless the operator answers yes.
 *
 * QA-4: on the tenant harness, an existing tenant with its own database (created in setUp,
 * BEFORE the tripwires) lets us also pin what sqlite :memory: could not: the guards never
 * touch an existing tenant's data, and without --fresh nothing is truncated.
 *
 * Still not covered: firstOrCreate staff users do not get a trivial default password
 * (needs a full generation run).
 */
final class PopulateRealisticTenantDataCommandTest extends TenantTestCase
{
    private const TENANT = 'zz-test';

    private Tenant $existing;

    protected function setUp(): void
    {
        parent::setUp();

        // A real tenant that already holds operational data (one item).
        $this->existing = $this->createTenant();
        $this->inTenant($this->existing, fn () => Item::create([
            'code' => 'KEEP-001',
            'name' => 'صنف يجب ألا يُحذف',
            'cost_price' => '10.000',
            'selling_price' => '15.000',
            'current_stock' => '7.250',
            'min_stock_level' => '1.000',
            'is_active' => true,
        ]));

        // Belt and braces: never let this test provision a real tenant database.
        Event::fake([
            TenantCreated::class,
            CreatingDatabase::class,
            DatabaseCreated::class,
            MigratingDatabase::class,
            DatabaseMigrated::class,
        ]);

        // Tripwire: if the command gets as far as creating a tenant, abort
        // immediately so a missing guard can never reach the truncate block.
        Tenant::creating(static function (): void {
            throw new RuntimeException('Guard missing: populate-realistic-data tried to create a tenant in production.');
        });

        $this->app['env'] = 'production';
    }

    private function assertExistingTenantUntouched(): void
    {
        $this->assertSame(
            ['KEEP-001' => '7.250'],
            $this->inTenant($this->existing, fn (): array => Item::query()->pluck('current_stock', 'code')->map(fn ($v): string => (string) $v)->all()),
            'The existing tenant\'s data was modified.'
        );
    }

    public function test_it_refuses_to_run_in_production_without_override(): void
    {
        $this->assertTrue($this->app->environment('production'));

        $this->artisan('tenant:populate-realistic-data', ['tenant' => self::TENANT])
            ->assertFailed();

        $this->assertTrue(Tenant::query()->where('id', self::TENANT)->doesntExist());
    }

    public function test_it_refuses_in_production_with_force_unsafe_when_operator_declines(): void
    {
        $this->artisan('tenant:populate-realistic-data', [
            'tenant' => self::TENANT,
            '--force-unsafe' => true,
        ])
            ->expectsConfirmation(__('console.populate_realistic_data.confirm_production'), 'no')
            ->assertFailed();

        $this->assertTrue(Tenant::query()->where('id', self::TENANT)->doesntExist());
    }

    public function test_it_refuses_in_production_with_force_unsafe_and_no_interaction(): void
    {
        $this->artisan('tenant:populate-realistic-data', [
            'tenant' => self::TENANT,
            '--force-unsafe' => true,
            '--no-interaction' => true,
        ])
            ->assertFailed();

        $this->assertTrue(Tenant::query()->where('id', self::TENANT)->doesntExist());
    }

    public function test_production_refusal_never_touches_an_existing_tenant_even_with_fresh(): void
    {
        $this->artisan('tenant:populate-realistic-data', [
            'tenant' => (string) $this->existing->getTenantKey(),
            '--fresh' => true,
        ])->assertFailed();

        $this->assertExistingTenantUntouched();
    }

    public function test_without_fresh_it_refuses_to_wipe_a_tenant_that_already_has_data(): void
    {
        // Outside production the guard is not involved: the existing-data check must stop it.
        $this->app['env'] = 'testing';
        $this->assertFalse($this->app->environment('production'));

        $this->artisan('tenant:populate-realistic-data', ['tenant' => (string) $this->existing->getTenantKey()])
            ->expectsOutputToContain(__('console.populate_realistic_data.existing_data'))
            ->assertFailed();

        $this->assertExistingTenantUntouched();
    }
}
