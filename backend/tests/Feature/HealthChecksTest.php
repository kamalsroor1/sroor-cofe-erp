<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Health\Checks\BackupArchivePasswordCheck;
use App\Health\Checks\BackupFreshnessCheck;
use App\Health\Checks\DiskSpaceCheck;
use App\Models\Tenant;
use App\Models\TenantBackup;
use App\Providers\HealthServiceProvider;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\HorizonCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Enums\Status;
use Spatie\Health\Facades\Health;
use Tests\TenantTestCase;

/**
 * OPS-5 / OPS-7: spatie/laravel-health checks registered by HealthServiceProvider.
 * - deploy gate (SROOR_HEALTH_DEPLOY_GATE=1): release-level checks only, never "warning";
 * - D4: an empty BACKUP_ARCHIVE_PASSWORD is red in production;
 * - backup freshness: every database needs a VERIFIED backup younger than 26 h
 *   (failed in production, warning elsewhere).
 */
final class HealthChecksTest extends TenantTestCase
{
    private const PASSWORD = 'fixture-archive-password-not-a-secret-0000';

    protected function tearDown(): void
    {
        putenv(HealthServiceProvider::DEPLOY_GATE_ENV);

        parent::tearDown();
    }

    public function test_the_app_registers_release_and_monitoring_checks(): void
    {
        $this->assertSame([
            DatabaseCheck::class,
            CacheCheck::class,
            RedisCheck::class,
            DiskSpaceCheck::class,
            BackupArchivePasswordCheck::class,
            HorizonCheck::class,
            QueueCheck::class,
            ScheduleCheck::class,
            BackupFreshnessCheck::class,
        ], $this->classes(Health::registeredChecks()->all()));
    }

    public function test_the_deploy_gate_only_runs_release_level_checks(): void
    {
        $this->assertSame([
            DatabaseCheck::class,
            CacheCheck::class,
            RedisCheck::class,
            DiskSpaceCheck::class,
            BackupArchivePasswordCheck::class,
        ], $this->classes(HealthServiceProvider::checks(true)));
    }

    public function test_the_deploy_gate_is_read_from_the_process_environment(): void
    {
        putenv(HealthServiceProvider::DEPLOY_GATE_ENV);
        $this->assertFalse(HealthServiceProvider::isDeployGate());

        putenv(HealthServiceProvider::DEPLOY_GATE_ENV.'=1');
        $this->assertTrue(HealthServiceProvider::isDeployGate());
        $this->assertCount(5, HealthServiceProvider::checks());

        putenv(HealthServiceProvider::DEPLOY_GATE_ENV.'=0');
        $this->assertFalse(HealthServiceProvider::isDeployGate());
    }

    public function test_health_check_command_runs_the_registered_checks(): void
    {
        config(['backup.backup.password' => self::PASSWORD]);

        $this->artisan('health:check', ['--no-notification' => true, '--do-not-store-results' => true])
            ->expectsOutputToContain('Running check: Backup Archive Password')
            ->expectsOutputToContain('Running check: Backup Freshness')
            ->assertExitCode(0);
    }

    public function test_d4_an_empty_archive_password_is_red_in_production(): void
    {
        config(['backup.backup.password' => null]);
        $this->production();

        $this->assertSame(Status::failed(), BackupArchivePasswordCheck::new()->run()->status);
        $this->assertSame(Status::failed(), BackupArchivePasswordCheck::new()->failuresOnly()->run()->status, 'The deploy gate must stay red too.');

        config(['backup.backup.password' => self::PASSWORD]);
        $this->assertSame(Status::ok(), BackupArchivePasswordCheck::new()->run()->status);
    }

    public function test_an_empty_or_short_archive_password_is_only_a_warning_outside_production(): void
    {
        config(['backup.backup.password' => null]);
        $this->assertSame(Status::warning(), BackupArchivePasswordCheck::new()->run()->status);
        $this->assertSame(Status::ok(), BackupArchivePasswordCheck::new()->failuresOnly()->run()->status);

        config(['backup.backup.password' => 'short']);
        $this->assertSame(Status::warning(), BackupArchivePasswordCheck::new()->run()->status);
    }

    public function test_backups_younger_than_26_hours_for_every_database_are_ok(): void
    {
        $this->production();
        $tenant = $this->agedTenant();
        $this->verifiedBackup(null, hoursAgo: 2);
        $this->verifiedBackup((string) $tenant->getTenantKey(), hoursAgo: 25);

        $result = BackupFreshnessCheck::new()->run();

        $this->assertSame(Status::ok(), $result->status, $result->getNotificationMessage());
    }

    public function test_a_backup_older_than_26_hours_fails_in_production(): void
    {
        $this->production();
        $tenant = $this->agedTenant();
        $id = (string) $tenant->getTenantKey();
        $this->verifiedBackup(null, hoursAgo: 1);
        $this->verifiedBackup($id, hoursAgo: 27);

        $result = BackupFreshnessCheck::new()->run();

        $this->assertSame(Status::failed(), $result->status);
        $this->assertSame([$id], $result->meta['stale']);
        $this->assertStringContainsString($id, $result->getNotificationMessage());
    }

    public function test_an_unverified_backup_does_not_count(): void
    {
        $this->production();
        $this->verifiedBackup(null, hoursAgo: 1, verified: false);

        $result = BackupFreshnessCheck::new()->run();

        $this->assertSame(Status::failed(), $result->status);
        $this->assertSame([TenantBackup::CENTRAL], $result->meta['stale']);
    }

    public function test_new_and_archived_tenants_are_not_required_yet(): void
    {
        $this->production();
        $this->createTenant();
        $archived = $this->agedTenant();
        Tenant::query()->whereKey($archived->getTenantKey())->update(['status' => 'archived']);
        $this->verifiedBackup(null, hoursAgo: 3);

        $result = BackupFreshnessCheck::new()->run();

        $this->assertSame(Status::ok(), $result->status, $result->getNotificationMessage());
        $this->assertSame(1, $result->meta['checked']);
    }

    public function test_a_missing_backup_is_only_a_warning_outside_production(): void
    {
        $this->agedTenant();

        $this->assertSame(Status::warning(), BackupFreshnessCheck::new()->run()->status);
    }

    public function test_disk_space_thresholds(): void
    {
        $check = static fn (int $warn, int $fail): Status => DiskSpaceCheck::new()
            ->warnWhenUsedSpaceIsAbovePercentage($warn)
            ->failWhenUsedSpaceIsAbovePercentage($fail)
            ->run()->status;

        $this->assertSame(Status::ok(), $check(100, 100));
        $this->assertSame(Status::warning(), $check(-1, 100));
        $this->assertSame(Status::failed(), $check(-1, -1));
    }

    private function production(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
    }

    private function agedTenant(): Tenant
    {
        $tenant = $this->createTenant();
        Tenant::query()->whereKey($tenant->getTenantKey())->update(['created_at' => now()->subDays(3)]);

        return $tenant;
    }

    private function verifiedBackup(?string $tenantId, int $hoursAgo, bool $verified = true): void
    {
        $at = now()->subHours($hoursAgo);
        TenantBackup::query()->insert([
            'tenant_id' => $tenantId,
            'disk' => 'google',
            'path' => 'sroor-backups/'.($tenantId ?? 'central').'/x-'.$hoursAgo.'.zip',
            'sha256' => str_repeat('a', 64),
            'size_bytes' => 10,
            'created_at' => $at,
            'verified_at' => $verified ? $at : null,
        ]);
    }

    /**
     * @param  array<int, Check>  $checks
     * @return list<class-string>
     */
    private function classes(array $checks): array
    {
        return array_values(array_map(static fn (Check $check): string => $check::class, $checks));
    }
}
