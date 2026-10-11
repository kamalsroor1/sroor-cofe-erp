<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Actions\Tenants\ArchiveTenantAction;
use App\Actions\Tenants\TransitionTenantStatusAction;
use App\Enums\CentralAuditEvent;
use App\Enums\TenantArchiveState;
use App\Enums\TenantBackupKind;
use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Exceptions\TenantLifecycleException;
use App\Models\CentralAuditLog;
use App\Models\Tenant;
use App\Models\TenantBackup;
use App\Models\TenantLifecycleEvent;
use App\Support\Tenancy\TenantStatusTransition;
use App\Support\Tenancy\TenantSuspensionReason;
use Closure;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * OPS-9 (CTO Q-B13): no tenant becomes `archived` without a FINAL backup that was uploaded
 * and verified. The guard lives in the single status writer (TransitionTenantStatusAction),
 * so every path (API job, tenants:archive, a future sweep) is covered:
 *
 *  - the backup row must be this tenant's, of kind final / pre_purge, verified and at most
 *    archive.final_backup_max_age_minutes old, else 409 subscription.final_backup_required,
 *    the status is untouched and TenantArchiveRefused is audited;
 *  - ArchiveTenantAction takes that backup through backup:tenants --kind=final and proves it
 *    on every configured disk; any failure leaves the status untouched and archive_state =
 *    failed with a code.
 */
final class ArchiveTenantRequiresFinalBackupTest extends TenantTestCase
{
    private const PASSWORD = 'fixture-archive-password-not-a-secret-0000';

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('google');
        Notification::fake();

        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sroor-archive-'.bin2hex(random_bytes(5));

        config([
            'backup.backup.password' => self::PASSWORD,
            'backup.tenants.disks' => ['google'],
            'backup.tenants.path_prefix' => 'sroor-backups',
            'backup.tenants.notify_mail' => null,
            'backup.tenants.temporary_directory' => $this->tmp,
            'backup.tenants.verify_after_upload' => true,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);

        parent::tearDown();
    }

    /**
     * @return array<string, array{0: Closure(string, string): ?int}>
     */
    public static function unprovenBackups(): array
    {
        $row = static fn (string $tenantId, array $overrides = []): int => (int) TenantBackup::query()->create(array_merge([
            'tenant_id' => $tenantId,
            'disk' => 'google',
            'path' => 'sroor-backups/'.$tenantId.'/fixture.zip',
            'sha256' => str_repeat('b', 64),
            'size_bytes' => 10,
            'kind' => TenantBackupKind::Final,
            'verified_at' => now(),
        ], $overrides))->getKey();

        return [
            'no backup named' => [static fn (string $id, string $other): ?int => null],
            'unknown backup id' => [static fn (string $id, string $other): ?int => 987654321],
            'another tenant\'s final backup' => [static fn (string $id, string $other): ?int => $row($other)],
            'the central database backup' => [static fn (string $id, string $other): ?int => $row($id, ['tenant_id' => null])],
            'a scheduled (prunable) backup' => [static fn (string $id, string $other): ?int => $row($id, ['kind' => TenantBackupKind::Scheduled])],
            'a manual backup' => [static fn (string $id, string $other): ?int => $row($id, ['kind' => TenantBackupKind::Manual])],
            'an unverified final backup' => [static fn (string $id, string $other): ?int => $row($id, ['verified_at' => null])],
        ];
    }

    /**
     * @param  Closure(string, string): ?int  $backup
     */
    #[DataProvider('unprovenBackups')]
    public function test_archived_is_refused_without_a_proven_final_backup(Closure $backup): void
    {
        $id = $this->centralOnlyTenant(TenantStatus::Suspended);
        $super = $this->centralSuperAdmin();

        $this->assertRefused('subscription.final_backup_required', fn () => $this->transition()->execute(TenantStatusTransition::to(
            $id,
            TenantStatus::Archived,
            TenantLifecycleActor::SuperAdmin,
            expectFrom: TenantStatus::Suspended,
            causer: $super,
            finalBackupId: $backup($id, 'qa-other-'.Str::lower(Str::random(6))),
        )));

        $this->assertStillSuspended($id);

        $refusal = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantArchiveRefused->value)->where('tenant_id', $id)->sole();
        $this->assertSame('final_backup_required', $refusal->properties['reason'] ?? null);
        $this->assertSame((int) $super->getKey(), $refusal->causer_id);
    }

    public function test_a_final_backup_older_than_the_window_is_refused(): void
    {
        $id = $this->centralOnlyTenant(TenantStatus::Cancelled);
        $maxAge = (int) config('tenant_lifecycle.archive.final_backup_max_age_minutes');
        $this->assertSame(120, $maxAge, 'CTO Q4 default.');

        $this->travel(-($maxAge + 1))->minutes();
        $old = $this->finalBackup($id);
        $this->travelBack();

        $this->assertRefused('subscription.final_backup_required', fn () => $this->transition()->execute(
            TenantStatusTransition::to($id, TenantStatus::Archived, TenantLifecycleActor::System, finalBackupId: $old),
        ));

        $this->assertSame(TenantStatus::Cancelled->value, Tenant::query()->findOrFail($id)->status);
    }

    public function test_a_recent_verified_final_backup_archives_and_is_recorded(): void
    {
        $tenant = $this->createTenant(['status' => TenantStatus::Suspended->value, 'suspension_reason' => TenantSuspensionReason::NonPayment->value]);
        $id = (string) $tenant->getKey();
        $backupId = $this->finalBackup($id, TenantBackupKind::PrePurge);

        $this->transition()->execute(TenantStatusTransition::to($id, TenantStatus::Archived, TenantLifecycleActor::System, finalBackupId: $backupId));

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantStatus::Archived, $fresh->lifecycleStatus());
        $this->assertSame($backupId, $fresh->final_backup_id);
        $this->assertNotNull($fresh->archived_at);
        $this->assertNull($fresh->archive_state);

        $audit = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantArchived->value)->where('tenant_id', $id)->sole();
        $this->assertSame($backupId, $audit->properties['final_backup_id'] ?? null);
        $this->assertTrue($this->tenantDatabaseExists($tenant), 'Archiving never drops the database.');
    }

    public function test_the_archive_action_takes_and_proves_a_final_backup_then_archives(): void
    {
        $tenant = $this->createTenant(['status' => TenantStatus::Suspended->value, 'suspension_reason' => TenantSuspensionReason::NonPayment->value]);
        $id = (string) $tenant->getKey();

        $archived = app(ArchiveTenantAction::class)->execute($id, TenantLifecycleActor::System, [TenantStatus::Suspended], null, 'أرشفة بعد 90 يوم');

        $backup = TenantBackup::query()->where('tenant_id', $id)->sole();
        $this->assertSame(TenantBackupKind::Final, $backup->kind);
        $this->assertNotNull($backup->verified_at);
        $this->assertStringEndsWith('-final.zip', $backup->path, 'Protected archives carry their kind in the file name.');
        Storage::disk('google')->assertExists($backup->path);

        $this->assertSame(TenantStatus::Archived->value, $archived->status);
        $this->assertSame((int) $backup->getKey(), $archived->final_backup_id);
        $this->assertNull($archived->archive_state);
        $this->assertNull($archived->archive_error_code);
        $this->assertTrue($this->tenantDatabaseExists($tenant));
        $this->assertFalse(tenancy()->initialized);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function failingBackups(): array
    {
        return [
            'no backup disk configured' => [['backup.tenants.disks' => []]],
            'uploaded but never verified' => [['backup.tenants.verify_after_upload' => false]],
            'refused by D4 (no archive password outside local/testing)' => [['backup.backup.password' => null, '__env' => 'staging']],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    #[DataProvider('failingBackups')]
    public function test_a_failing_final_backup_leaves_the_status_and_marks_the_request_failed(array $config): void
    {
        $tenant = $this->createTenant(['status' => TenantStatus::Suspended->value, 'suspension_reason' => TenantSuspensionReason::NonPayment->value]);
        $id = (string) $tenant->getKey();

        if (isset($config['__env'])) {
            $env = (string) $config['__env'];
            $this->app->detectEnvironment(static fn (): string => $env);
            unset($config['__env']);
        }
        config($config);

        $this->assertRefused('subscription.archive_failed', fn () => app(ArchiveTenantAction::class)->execute(
            $id, TenantLifecycleActor::System, [TenantStatus::Suspended], null, null,
        ));

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantStatus::Suspended->value, $fresh->status);
        $this->assertSame(TenantArchiveState::Failed, $fresh->archive_state);
        $this->assertSame(ArchiveTenantAction::FINAL_BACKUP_FAILED, $fresh->archive_error_code);
        $this->assertNull($fresh->archived_at);
        $this->assertSame(0, TenantLifecycleEvent::query()->where('tenant_id', $id)->count());

        $refusal = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantArchiveRefused->value)->where('tenant_id', $id)->sole();
        $this->assertSame(ArchiveTenantAction::FINAL_BACKUP_FAILED, $refusal->properties['archive_error_code'] ?? null);
        $this->assertTrue($this->tenantDatabaseExists($tenant));
    }

    public function test_a_status_that_changed_since_the_request_stops_before_any_backup(): void
    {
        $id = $this->centralOnlyTenant(TenantStatus::Cancelled);

        $this->assertRefused('subscription.archive_failed', fn () => app(ArchiveTenantAction::class)->execute(
            $id, TenantLifecycleActor::System, [TenantStatus::Suspended], null, null,
        ));

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantStatus::Cancelled->value, $fresh->status);
        $this->assertSame(ArchiveTenantAction::STATUS_CHANGED, $fresh->archive_error_code);
        $this->assertSame(0, TenantBackup::query()->count(), 'No backup is taken for a request that is no longer valid.');
    }

    public function test_a_purging_tenant_never_transitions_again(): void
    {
        $id = $this->centralOnlyTenant(TenantStatus::Archived);
        Tenant::query()->whereKey($id)->update(['status_before_archive' => TenantStatus::Suspended->value, 'purge_started_at' => now()]);

        $this->assertRefused('subscription.tenant_purged', fn () => $this->transition()->execute(
            TenantStatusTransition::unarchive($id, $this->centralSuperAdmin()),
        ));

        $this->assertSame(TenantStatus::Archived->value, Tenant::query()->findOrFail($id)->status);
    }

    private function transition(): TransitionTenantStatusAction
    {
        return app(TransitionTenantStatusAction::class);
    }

    /** A central row only (no database): enough for the guard's refusals. */
    private function centralOnlyTenant(TenantStatus $status): string
    {
        $id = 'qa-arch-'.Str::lower(Str::random(8));
        Tenant::query()->create([
            'id' => $id,
            'name' => 'محل أرشفة '.$id,
            'slug' => $id,
            'email' => $id.'@sroor.test',
            'status' => $status->value,
            'status_changed_at' => now()->subDays(100),
            'suspension_reason' => TenantSuspensionReason::NonPayment->value,
            'tenancy_create_database' => false,
        ]);

        return $id;
    }

    private function finalBackup(string $tenantId, TenantBackupKind $kind = TenantBackupKind::Final): int
    {
        return (int) TenantBackup::query()->create([
            'tenant_id' => $tenantId,
            'disk' => 'google',
            'path' => 'sroor-backups/'.$tenantId.'/'.$tenantId.'-'.$kind->value.'.zip',
            'sha256' => str_repeat('c', 64),
            'size_bytes' => 10,
            'kind' => $kind,
            'verified_at' => now(),
        ])->getKey();
    }

    private function assertStillSuspended(string $id): void
    {
        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantStatus::Suspended->value, $fresh->status);
        $this->assertNull($fresh->archived_at);
        $this->assertNull($fresh->final_backup_id);
        $this->assertSame(0, TenantLifecycleEvent::query()->where('tenant_id', $id)->count());
        $this->assertFalse(CentralAuditLog::query()->where('event', CentralAuditEvent::TenantArchived->value)->where('tenant_id', $id)->exists());
    }

    private function assertRefused(string $errorCode, Closure $call): void
    {
        try {
            $call();
            $this->fail("Expected a {$errorCode} refusal.");
        } catch (TenantLifecycleException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertSame($errorCode, $e->errorCode());
            $this->assertStringNotContainsString('subscription.', $e->getMessage(), 'The message must be translated.');
        }
    }
}
