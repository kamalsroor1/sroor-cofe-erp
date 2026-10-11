<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\CentralAuditEvent;
use App\Enums\TenantBackupKind;
use App\Enums\TenantStatus;
use App\Models\CentralAuditLog;
use App\Models\Tenant;
use App\Models\TenantBackup;
use App\Services\TenantProvisionerService;
use App\Support\Tenancy\TenantSuspensionReason;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * OPS-9 (CTO Q2–Q5): `tenants:purge` is the only way a tenant database is ever dropped.
 *
 *  - no backup → refused, 0 DROP; a backup older than 24 h → refused; a recent verified
 *    backup (sha256 re-checked, decrypted, restore drill) + typed confirmation → DROP;
 *  - eligibility: archived ≥ 30 days / blocked ≥ 90 days, never a violation;
 *  - confirmation: interactive typed shop code + yes (default no), or non-interactive
 *    --confirm=<slug> --yes-i-understand; --dry-run drops nothing;
 *  - tombstone: row kept with purged_at / purged_by, domains / credentials removed, files
 *    deleted, backups ledger kept, audits TenantPurgeStarted + TenantPurged;
 *  - a failed run (shared database) is audited and resumable; a purged tenant is 404 on the
 *    tenant API and never backed up again.
 */
final class TenantPurgeCommandTest extends TenantTestCase
{
    private const PASSWORD = 'fixture-archive-password-not-a-secret-0000';

    private const PREFIX = 'tenant_zz_restore_';

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('google');
        Notification::fake();

        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sroor-purge-'.bin2hex(random_bytes(5));

        config([
            'backup.backup.password' => self::PASSWORD,
            'backup.tenants.disks' => ['google'],
            'backup.tenants.path_prefix' => 'sroor-backups',
            'backup.tenants.notify_mail' => null,
            'backup.tenants.temporary_directory' => $this->tmp,
            'backup.tenants.verify_after_upload' => true,
            'backup.tenants.restore_database_prefix' => self::PREFIX,
        ]);
    }

    protected function tearDown(): void
    {
        foreach (File::glob(database_path(self::PREFIX.'*.sqlite')) as $file) {
            File::delete($file);
        }
        File::deleteDirectory($this->tmp);

        parent::tearDown();
    }

    // ------------------------------------------------------------------ backup rails

    public function test_without_any_backup_the_purge_is_refused_and_nothing_is_dropped(): void
    {
        $tenant = $this->eligibleTenant();

        $this->purge($tenant)
            ->expectsOutputToContain($this->refusal('backup_missing'))
            ->assertExitCode(1);

        $this->assertUntouched($tenant);
        $this->assertRefusalAudited($tenant, 'backup_missing');
    }

    public function test_a_backup_older_than_24_hours_is_refused(): void
    {
        $tenant = $this->eligibleTenant();
        $this->backup($tenant);

        $this->travel(25)->hours();

        $this->purge($tenant)
            ->expectsOutputToContain($this->refusal('backup_too_old'))
            ->assertExitCode(1);

        $this->assertUntouched($tenant);
    }

    public function test_a_recent_verified_backup_and_the_confirmation_purge_the_tenant_and_keep_a_tombstone(): void
    {
        $tenant = $this->eligibleTenant();
        $id = (string) $tenant->getKey();
        $backup = $this->backup($tenant);
        $database = $this->tenantDatabaseName($tenant);
        $storage = storage_path(config('tenancy.filesystem.suffix_base', 'tenant').$id);
        $this->assertDirectoryExists($storage);

        $this->purge($tenant)
            ->expectsOutputToContain((string) __('console.purge.backup_verified', ['backup' => $backup->getKey()]))
            ->assertExitCode(0);

        $this->assertFalse($this->tenantDatabaseExists($tenant), 'The tenant database is dropped.');
        $this->assertDirectoryDoesNotExist($storage, 'The tenant files are deleted.');
        $this->assertSame([], File::glob(database_path(self::PREFIX.'*.sqlite')), 'The restore drill copy is dropped.');

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertNotNull($fresh->purge_started_at);
        $this->assertNotNull($fresh->purged_at);
        $this->assertStringStartsWith('cli:', (string) $fresh->purged_by);
        $this->assertSame(TenantStatus::Archived->value, $fresh->status, 'The row is a tombstone: status and history are kept.');
        $this->assertSame(0, $fresh->domains()->count());
        $this->assertNull($fresh->getInternal('db_username'));
        $this->assertTrue(TenantBackup::query()->whereKey($backup->getKey())->exists(), 'The backups ledger is kept.');

        $started = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantPurgeStarted->value)->where('tenant_id', $id)->sole();
        $this->assertSame((int) $backup->getKey(), $started->properties['backup_id'] ?? null);
        $purged = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantPurged->value)->where('tenant_id', $id)->sole();
        $this->assertSame($backup->sha256, $purged->properties['sha256'] ?? null);
        $this->assertSame($database, $purged->properties['database'] ?? null);
        $this->assertTrue($purged->properties['database_dropped'] ?? false);
        $this->assertTrue($purged->properties['files_deleted'] ?? false);
        $this->assertSame(1, $purged->properties['domains_deleted'] ?? null);

        // Gone for clients, never provisioned, never backed up, never purged twice.
        $this->getJson('/api/v1/auth/me', ['Accept' => 'application/json', 'X-Tenant' => $id])
            ->assertStatus(404)
            ->assertJsonPath('message', __('auth.tenant_not_found'));
        $this->assertFalse(Tenant::query()->provisioned()->whereKey($id)->exists());
        $this->artisan('backup:tenants', ['--tenant' => [$id], '--skip-central' => true, '--no-cleanup' => true])->assertExitCode(1);
        $this->purge($tenant)->expectsOutputToContain((string) __('console.purge.already_purged', ['tenant' => $id]))->assertExitCode(1);
    }

    public function test_take_backup_takes_a_protected_pre_purge_backup_and_relies_on_it(): void
    {
        $tenant = $this->eligibleTenant();
        $id = (string) $tenant->getKey();

        $this->purge($tenant, ['--take-backup' => true])->assertExitCode(0);

        $backup = TenantBackup::query()->where('tenant_id', $id)->sole();
        $this->assertSame(TenantBackupKind::PrePurge, $backup->kind);
        $this->assertStringEndsWith('-pre_purge.zip', $backup->path);
        Storage::disk('google')->assertExists($backup->path);
        $this->assertFalse($this->tenantDatabaseExists($tenant));

        $started = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantPurgeStarted->value)->where('tenant_id', $id)->sole();
        $this->assertSame((int) $backup->getKey(), $started->properties['backup_id'] ?? null);
    }

    public function test_a_tampered_backup_fails_verification_and_nothing_is_dropped(): void
    {
        $tenant = $this->eligibleTenant();
        $backup = $this->backup($tenant);
        Storage::disk('google')->put($backup->path, 'tampered');

        $this->purge($tenant)
            ->expectsOutputToContain($this->refusal('backup_verification_failed'))
            ->assertExitCode(1);

        $this->assertUntouched($tenant);
    }

    public function test_another_tenants_backup_is_refused(): void
    {
        $tenant = $this->eligibleTenant();
        $other = $this->createTenant();
        $foreign = $this->backup($other);

        $this->purge($tenant, ['--backup' => (string) $foreign->getKey()])
            ->expectsOutputToContain($this->refusal('backup_wrong_tenant'))
            ->assertExitCode(1);

        $this->assertUntouched($tenant);
        $this->assertTrue($this->tenantDatabaseExists($other));
    }

    public function test_backup_and_take_backup_together_are_refused(): void
    {
        $tenant = $this->eligibleTenant();
        $backup = $this->backup($tenant);

        $this->purge($tenant, ['--backup' => (string) $backup->getKey(), '--take-backup' => true])
            ->expectsOutputToContain($this->refusal('conflicting_options'))
            ->assertExitCode(1);

        $this->assertUntouched($tenant);
    }

    // ------------------------------------------------------------------ eligibility

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function ineligible(): array
    {
        return [
            'archived 5 days ago' => [['archived_at' => -5, 'status_changed_at' => -5], 'too_recent'],
            'suspended 60 days ago' => [['status' => 'suspended', 'archived_at' => null, 'status_changed_at' => -60], 'too_recent'],
            'violation, archived a year ago' => [['suspension_reason' => 'violation', 'archived_at' => -365, 'status_changed_at' => -365], 'violation'],
            'active' => [['status' => 'active', 'suspension_reason' => null, 'archived_at' => null, 'status_changed_at' => -400], 'status_not_eligible'],
        ];
    }

    /**
     * @param  array<string, mixed>  $columns  dates as day offsets from now
     */
    #[DataProvider('ineligible')]
    public function test_an_ineligible_tenant_is_refused_before_any_backup_work(array $columns, string $reason): void
    {
        $tenant = $this->eligibleTenant();
        foreach (['archived_at', 'status_changed_at'] as $date) {
            if (array_key_exists($date, $columns) && $columns[$date] !== null) {
                $columns[$date] = now()->addDays((int) $columns[$date]);
            }
        }
        Tenant::query()->whereKey($tenant->getKey())->update($columns);

        $this->purge($tenant, ['--take-backup' => true])
            ->expectsOutputToContain($this->refusal($reason))
            ->assertExitCode(1);

        $this->assertUntouched($tenant);
        $this->assertSame(0, TenantBackup::query()->count(), 'An ineligible tenant gets no backup either.');
        $this->assertRefusalAudited($tenant, $reason);
    }

    public function test_suspended_for_90_days_is_eligible(): void
    {
        $tenant = $this->eligibleTenant();
        Tenant::query()->whereKey($tenant->getKey())->update([
            'status' => TenantStatus::Suspended->value,
            'archived_at' => null,
            'status_changed_at' => now()->subDays(90),
        ]);
        $this->backup($tenant);

        $this->purge($tenant)->assertExitCode(0);

        $this->assertFalse($this->tenantDatabaseExists($tenant));
    }

    // ------------------------------------------------------------------ confirmation

    public function test_non_interactive_runs_need_both_flags_and_the_exact_slug(): void
    {
        $tenant = $this->eligibleTenant();
        $this->backup($tenant);
        $id = (string) $tenant->getKey();

        $this->artisan('tenants:purge', ['tenant' => $id, '--no-interaction' => true, '--confirm' => (string) $tenant->slug])
            ->expectsOutputToContain($this->refusal('confirmation_missing'))
            ->assertExitCode(1);

        $this->artisan('tenants:purge', ['tenant' => $id, '--no-interaction' => true, '--yes-i-understand' => true])
            ->expectsOutputToContain($this->refusal('confirmation_missing'))
            ->assertExitCode(1);

        $this->artisan('tenants:purge', ['tenant' => $id, '--no-interaction' => true, '--confirm' => strtoupper((string) $tenant->slug), '--yes-i-understand' => true])
            ->expectsOutputToContain($this->refusal('confirmation_mismatch'))
            ->assertExitCode(1);

        $this->assertUntouched($tenant);
    }

    public function test_interactive_runs_ask_for_the_typed_slug_then_a_confirmation_defaulting_to_no(): void
    {
        $tenant = $this->eligibleTenant();
        $this->backup($tenant);
        $id = (string) $tenant->getKey();
        $slug = (string) $tenant->slug;
        $question = (string) __('console.purge.type_slug', ['slug' => $slug]);

        $this->artisan('tenants:purge', ['tenant' => $id])
            ->expectsQuestion($question, 'wrong-code')
            ->expectsOutputToContain($this->refusal('confirmation_mismatch'))
            ->assertExitCode(1);

        $this->artisan('tenants:purge', ['tenant' => $id])
            ->expectsQuestion($question, $slug)
            ->expectsConfirmation((string) __('console.purge.confirm_drop'), 'no')
            ->expectsOutputToContain($this->refusal('not_confirmed'))
            ->assertExitCode(1);

        $this->assertUntouched($tenant);

        $this->artisan('tenants:purge', ['tenant' => $id])
            ->expectsQuestion($question, $slug)
            ->expectsConfirmation((string) __('console.purge.confirm_drop'), 'yes')
            ->assertExitCode(0);

        $this->assertFalse($this->tenantDatabaseExists($tenant));
    }

    public function test_dry_run_runs_every_check_and_drops_nothing(): void
    {
        $tenant = $this->eligibleTenant();
        $this->backup($tenant);

        $this->artisan('tenants:purge', ['tenant' => (string) $tenant->getKey(), '--dry-run' => true, '--no-interaction' => true])
            ->expectsOutputToContain((string) __('console.purge.dry_run_done'))
            ->assertExitCode(0);

        $this->assertUntouched($tenant);
        $this->assertSame([], File::glob(database_path(self::PREFIX.'*.sqlite')), 'The drill copy is dropped even in a dry run.');
    }

    // ------------------------------------------------------------------ failure + resume

    public function test_a_database_shared_with_another_tenant_is_never_dropped_and_the_failure_is_resumable(): void
    {
        $tenant = $this->eligibleTenant();
        $id = (string) $tenant->getKey();
        $this->backup($tenant);

        $squatter = 'qa-squat-'.Str::lower(Str::random(6));
        Tenant::query()->create([
            'id' => $squatter,
            'name' => 'مستأجر يشارك القاعدة',
            'slug' => $squatter,
            'email' => $squatter.'@sroor.test',
            'status' => TenantStatus::Active->value,
            'tenancy_db_name' => $this->tenantDatabaseName($tenant),
            'tenancy_create_database' => false,
        ]);

        $this->purge($tenant)->assertExitCode(1);

        $this->assertTrue($this->tenantDatabaseExists($tenant), 'A shared database is never dropped.');
        $fresh = Tenant::query()->findOrFail($id);
        $this->assertNotNull($fresh->purge_started_at, 'The purge started (resumable)...');
        $this->assertNull($fresh->purged_at, '...but did not finish.');
        $failed = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantPurgeFailed->value)->where('tenant_id', $id)->sole();
        $this->assertSame('database', $failed->properties['step'] ?? null);

        // The operator fixes the conflict and runs the command again: it resumes.
        Tenant::query()->whereKey($squatter)->delete();

        $this->purge($tenant)
            ->expectsOutputToContain((string) __('console.purge.resuming', ['tenant' => $id]))
            ->assertExitCode(0);

        $this->assertFalse($this->tenantDatabaseExists($tenant));
        $this->assertNotNull(Tenant::query()->findOrFail($id)->purged_at);
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::TenantPurgeStarted->value)->where('tenant_id', $id)->count(), 'A resume does not start a second purge.');
    }

    public function test_a_resume_whose_database_is_already_gone_finishes_without_a_backup(): void
    {
        $tenant = $this->eligibleTenant();
        $id = (string) $tenant->getKey();
        Tenant::query()->whereKey($id)->update(['purge_started_at' => now()->subHour(), 'purged_by' => 'cli:ops']);
        $this->endTenancy();
        gc_collect_cycles();
        $tenant->database()->manager()->deleteDatabase($tenant);
        $this->assertFalse($this->tenantDatabaseExists($tenant));

        $this->purge($tenant)
            ->expectsOutputToContain((string) __('console.purge.database_missing', ['database' => $this->tenantDatabaseName($tenant)]))
            ->assertExitCode(0);

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertNotNull($fresh->purged_at);
        $purged = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantPurged->value)->where('tenant_id', $id)->sole();
        $this->assertFalse($purged->properties['database_existed'] ?? true);
        $this->assertTrue($purged->properties['resumed'] ?? false);
    }

    public function test_a_database_name_that_the_platform_did_not_make_for_this_tenant_is_never_dropped(): void
    {
        $tenant = $this->eligibleTenant();
        $id = (string) $tenant->getKey();
        $foreign = 'qa_operator_'.Str::lower(Str::random(6)).'.sqlite';
        File::copy(database_path($this->tenantDatabaseName($tenant)), database_path($foreign));

        try {
            $this->pointDatabaseAt($tenant, $foreign, recordAsCreated: false);
            $this->backup($tenant);

            $this->purge($tenant)
                ->expectsOutputToContain((string) __('console.purge.database_unexpected_name', [
                    'database' => $foreign,
                    'expected' => config('tenancy.database.prefix').$id.config('tenancy.database.suffix'),
                ]))
                ->assertExitCode(1);

            $this->assertFileExists(database_path($foreign), 'A database the platform did not make for this tenant is never dropped.');
            $this->assertNull(Tenant::query()->findOrFail($id)->purged_at);
            $failed = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantPurgeFailed->value)->where('tenant_id', $id)->sole();
            $this->assertSame('database', $failed->properties['step'] ?? null);
        } finally {
            gc_collect_cycles();
            File::delete(database_path($foreign));
        }
    }

    public function test_a_custom_database_name_recorded_as_created_by_the_provisioner_is_dropped(): void
    {
        $tenant = $this->eligibleTenant();
        $id = (string) $tenant->getKey();
        $created = 'qa_created_'.Str::lower(Str::random(6)).'.sqlite';
        File::copy(database_path($this->tenantDatabaseName($tenant)), database_path($created));

        try {
            $this->pointDatabaseAt($tenant, $created, recordAsCreated: true);
            $this->backup($tenant);

            $this->purge($tenant)->assertExitCode(0);

            $this->assertFileDoesNotExist(database_path($created));
            $this->assertNotNull(Tenant::query()->findOrFail($id)->purged_at);
        } finally {
            gc_collect_cycles();
            File::delete(database_path($created));
        }
    }

    public function test_an_unknown_tenant_is_an_error(): void
    {
        $this->artisan('tenants:purge', ['tenant' => 'qa-no-such-tenant', '--no-interaction' => true])
            ->expectsOutputToContain((string) __('console.purge.tenant_not_found', ['tenant' => 'qa-no-such-tenant']))
            ->assertExitCode(1);
    }

    // ------------------------------------------------------------------ helpers

    /** A real tenant (own database), archived 40 days ago for non-payment. */
    private function eligibleTenant(): Tenant
    {
        $tenant = $this->createTenant();
        Tenant::query()->whereKey($tenant->getKey())->update([
            'status' => TenantStatus::Archived->value,
            'status_before_archive' => TenantStatus::Suspended->value,
            'suspension_reason' => TenantSuspensionReason::NonPayment->value,
            'status_changed_at' => now()->subDays(40),
            'archived_at' => now()->subDays(40),
        ]);

        return Tenant::query()->findOrFail($tenant->getKey());
    }

    /** Repoint the tenant's tenancy_db_name (optionally recording it as provisioner-created). */
    private function pointDatabaseAt(Tenant $tenant, string $database, bool $recordAsCreated): void
    {
        $this->endTenancy();
        $fresh = Tenant::query()->findOrFail($tenant->getKey());
        $fresh->setInternal('db_name', $database);
        if ($recordAsCreated) {
            $fresh->setInternal(TenantProvisionerService::CREATED_DATABASE_KEY, $database);
        }
        $fresh->save();
    }

    /** A scheduled, verified backup through the real engine. */
    private function backup(Tenant $tenant): TenantBackup
    {
        $id = (string) $tenant->getKey();
        $this->artisan('backup:tenants', ['--tenant' => [$id], '--skip-central' => true, '--no-cleanup' => true])->assertExitCode(0);

        return TenantBackup::query()->where('tenant_id', $id)->latest('id')->firstOrFail();
    }

    /**
     * Non-interactive run with the full confirmation.
     *
     * @param  array<string, mixed>  $options
     */
    private function purge(Tenant $tenant, array $options = []): PendingCommand
    {
        $command = $this->artisan('tenants:purge', array_merge([
            'tenant' => (string) $tenant->getKey(),
            '--no-interaction' => true,
            '--confirm' => (string) $tenant->slug,
            '--yes-i-understand' => true,
        ], $options));

        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }

    private function refusal(string $reason): string
    {
        return (string) __('console.purge.refused', ['reason' => __('console.purge.reasons.'.$reason)]);
    }

    private function assertUntouched(Tenant $tenant): void
    {
        $fresh = Tenant::query()->findOrFail($tenant->getKey());
        $this->assertTrue($this->tenantDatabaseExists($tenant), 'The tenant database must still exist.');
        $this->assertNull($fresh->purge_started_at);
        $this->assertNull($fresh->purged_at);
        $this->assertSame(1, $fresh->domains()->count());
        $this->assertFalse(CentralAuditLog::query()->where('event', CentralAuditEvent::TenantPurged->value)->where('tenant_id', $tenant->getKey())->exists());
    }

    private function assertRefusalAudited(Tenant $tenant, string $reason): void
    {
        $refusal = CentralAuditLog::query()
            ->where('event', CentralAuditEvent::TenantPurgeRefused->value)
            ->where('tenant_id', $tenant->getKey())
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($reason, $refusal->properties['reason'] ?? null);
    }
}
