<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Actions\System\GetSetupProgressAction;
use App\Actions\Tenants\AdvanceDocumentSequencesAction;
use App\Models\Setting;
use App\Models\Tenant;
use App\Support\DashboardCache;
use App\Support\Tenancy\EndsTenantImpersonationSessions;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * BKP-02: backup:swap-tenant refusals and the document counter jump that stops numbers printed
 * after the backup from being issued again.
 */
final class SwapTenantDatabaseCommandTest extends TenantTestCase
{
    public function test_an_unknown_tenant_is_refused(): void
    {
        $this->artisan('backup:swap-tenant', ['tenant' => 'no-such-shop', '--database' => 'tenant_zz_restore_x', '--force' => true])
            ->expectsOutputToContain((string) __('console.swap.tenant_not_found', ['tenant' => 'no-such-shop']))
            ->assertExitCode(1);
    }

    public function test_a_missing_or_unsafe_database_name_is_refused(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();

        $this->artisan('backup:swap-tenant', ['tenant' => $id, '--force' => true])
            ->expectsOutputToContain((string) __('console.swap.invalid_database'))
            ->assertExitCode(1);

        $this->artisan('backup:swap-tenant', ['tenant' => $id, '--database' => 'x`; drop database y', '--force' => true])
            ->expectsOutputToContain((string) __('console.swap.invalid_database'))
            ->assertExitCode(1);
    }

    public function test_a_margin_below_one_is_refused(): void
    {
        $tenant = $this->createTenant();

        $this->artisan('backup:swap-tenant', [
            'tenant' => (string) $tenant->getTenantKey(),
            '--database' => 'tenant_zz_restore_x',
            '--number-margin' => 0,
            '--force' => true,
        ])->expectsOutputToContain((string) __('console.swap.invalid_margin'))->assertExitCode(1);
    }

    public function test_every_counter_moves_forward_and_missing_days_get_a_row(): void
    {
        $this->inTenant($this->createTenant(), $this->assertCountersAdvance(...));
    }

    private function assertCountersAdvance(): void
    {
        DB::table('document_sequences')->insert([
            ['scope' => 'INV-MAIN-20200101', 'last_value' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['scope' => 'PAY-INV-20200101', 'last_value' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['scope' => 'SHIFT', 'last_value' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $result = app(AdvanceDocumentSequencesAction::class)->execute(1000);

        $this->assertSame(3, $result['bumped']);
        $this->assertSame(1007, (int) DB::table('document_sequences')->where('scope', 'INV-MAIN-20200101')->value('last_value'));
        $this->assertSame(1003, (int) DB::table('document_sequences')->where('scope', 'PAY-INV-20200101')->value('last_value'));
        $this->assertSame(1005, (int) DB::table('document_sequences')->where('scope', 'SHIFT')->value('last_value'));
        // 2020 is far in the past: only the last 31 days up to today get a seeded row, per family.
        $this->assertSame(1000, (int) DB::table('document_sequences')->where('scope', 'like', 'INV-MAIN-%')->where('scope', '!=', 'INV-MAIN-20200101')->min('last_value'));
        $this->assertGreaterThan(0, $result['created']);
    }

    public function test_a_margin_below_one_is_rejected_by_the_action(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(AdvanceDocumentSequencesAction::class)->execute(0);
    }

    public function test_a_swap_forgets_only_this_tenants_cache_and_keeps_central_and_other_tenants_keys(): void
    {
        $tenant = $this->createTenant();
        $other = $this->createTenant();
        $id = (string) $tenant->getTenantKey();
        $otherId = (string) $other->getTenantKey();
        $restored = $this->copyTenantDatabase($tenant);

        try {
            Cache::forever('c:central-state', 'keep');
            Cache::forever(TenantCache::keyFor($otherId, 'x'), 'keep');
            Cache::forever(Setting::cacheKeyFor($otherId), ['company_name' => 'keep']);
            Cache::forever(Setting::cacheKeyFor($id), ['company_name' => 'stale']);
            Cache::forever(GetSetupProgressAction::cacheKeyFor($id), ['completed' => 7]);
            $dashboardBefore = TenantCache::versionFor($id, DashboardCache::NAMESPACE);

            $this->artisan('backup:swap-tenant', ['tenant' => $id, '--database' => $restored, '--force' => true])
                ->assertExitCode(0);

            $this->assertSame('keep', Cache::get('c:central-state'));
            $this->assertSame('keep', Cache::get(TenantCache::keyFor($otherId, 'x')));
            $this->assertSame(['company_name' => 'keep'], Cache::get(Setting::cacheKeyFor($otherId)));
            $this->assertNull(Cache::get(Setting::cacheKeyFor($id)));
            $this->assertNull(Cache::get(GetSetupProgressAction::cacheKeyFor($id)));
            $this->assertNotSame($dashboardBefore, TenantCache::versionFor($id, DashboardCache::NAMESPACE));
            $this->assertSame($restored, (string) $tenant->fresh()?->database()->getName());
        } finally {
            $this->dropCopiedDatabase($restored);
        }
    }

    public function test_a_failure_after_the_repoint_points_the_shop_back_at_the_previous_database(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();
        $previous = (string) $tenant->database()->getName();
        $restored = $this->copyTenantDatabase($tenant);

        $this->app->instance(AdvanceDocumentSequencesAction::class, new class
        {
            /** @return array{bumped: int, created: int} */
            public function execute(int $margin): array
            {
                throw new RuntimeException('sequence table locked');
            }
        });

        try {
            $this->artisan('backup:swap-tenant', ['tenant' => $id, '--database' => $restored, '--force' => true])
                ->expectsOutputToContain((string) __('console.swap.rolled_back', ['database' => $previous, 'reason' => RuntimeException::class]))
                ->assertExitCode(1);

            $this->assertSame($previous, (string) $tenant->fresh()?->database()->getName());
        } finally {
            $this->dropCopiedDatabase($restored);
        }
    }

    public function test_a_token_revocation_failure_points_the_shop_back_at_the_previous_database(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();
        $previous = (string) $tenant->database()->getName();
        $restored = $this->copyTenantDatabase($tenant);

        $this->app->instance(EndsTenantImpersonationSessions::class, new class implements EndsTenantImpersonationSessions
        {
            public function endAllForTenant(Tenant $tenant): int
            {
                throw new RuntimeException('central down');
            }
        });

        try {
            $this->artisan('backup:swap-tenant', ['tenant' => $id, '--database' => $restored, '--force' => true])
                ->assertExitCode(1);

            $this->assertSame($previous, (string) $tenant->fresh()?->database()->getName());
        } finally {
            $this->dropCopiedDatabase($restored);
        }
    }

    /** sqlite only: a "restored" database is a copy of the tenant's file under a swap-safe name. */
    private function copyTenantDatabase(Tenant $tenant): string
    {
        $source = database_path($this->tenantDatabaseName($tenant));
        if (! is_file($source)) {
            $this->markTestSkipped('The swap round-trip test copies the sqlite tenant file.');
        }

        // The provisioner (MySQL-only GRANT / schema lookup) is skipped on a non-MySQL connection.
        config(['tenancy.provisioning.connection' => config('database.default')]);

        $name = 'tenant_zz_restore_'.Str::lower(Str::random(10));
        $this->assertTrue(copy($source, database_path($name)));

        return $name;
    }

    private function dropCopiedDatabase(string $name): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        DB::purge('tenant');
        @unlink(database_path($name));
    }
}
