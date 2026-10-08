<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Stancl\Tenancy\Events\CreatingDatabase;
use Stancl\Tenancy\Events\DatabaseCreated;
use Stancl\Tenancy\Events\DatabaseMigrated;
use Stancl\Tenancy\Events\MigratingDatabase;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\TestCase;

/**
 * P0-OPS-1 — `tenant:populate-realistic-data` must refuse to run in production
 * before it resolves/creates a tenant, creates a database, or truncates anything.
 *
 * Contract for the interactive override (test b): with `--force-unsafe` in
 * production the command asks
 *   __('console.populate_realistic_data.confirm_production')
 * and aborts unless the operator answers yes.
 *
 * Not covered here (need a real tenant DB, not feasible under sqlite :memory:):
 *  - truncation only happens with --fresh
 *  - firstOrCreate staff users do not get a trivial default password
 */
final class PopulateRealisticTenantDataCommandTest extends TestCase
{
    use RefreshDatabase;

    private const TENANT = 'zz-test';

    protected function setUp(): void
    {
        parent::setUp();

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
}
