<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\BackupTenantsCommand;
use App\Enums\TenantBackupKind;
use App\Models\TenantBackup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * OPS-9 (CTO Q6): the 7/4/3 retention of backup:tenants never deletes a `final` or
 * `pre_purge` archive (the last copy of an archived / purged tenant), whether it is
 * recognised by its file name (`…-final.zip`, `…-pre_purge.zip`) or by its ledger kind.
 * Scheduled archives keep being pruned exactly as before. `--kind` is validated.
 */
final class BackupRetentionProtectsFinalArchivesTest extends TenantTestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('google');
        Notification::fake();

        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sroor-retention-'.bin2hex(random_bytes(5));

        config([
            'backup.backup.password' => 'fixture-archive-password-not-a-secret-0000',
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

    public function test_final_and_pre_purge_archives_survive_retention_while_old_scheduled_ones_go(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();
        $now = CarbonImmutable::now();

        $scheduledOld = $this->archive($id, $now->subDays(150), TenantBackupKind::Scheduled);
        $scheduledOlder = $this->archive($id, $now->subDays(160), TenantBackupKind::Scheduled);
        $finalNamed = $this->archive($id, $now->subDays(200), TenantBackupKind::Final, suffix: true);
        $prePurgeNamed = $this->archive($id, $now->subDays(180), TenantBackupKind::PrePurge, suffix: true);
        // A protected row whose file carries the plain (prunable-looking) name: the ledger kind protects it.
        $finalByLedgerOnly = $this->archive($id, $now->subDays(170), TenantBackupKind::Final, suffix: false);
        $manualOld = $this->archive($id, $now->subDays(155), TenantBackupKind::Manual);

        $this->artisan('backup:tenants', ['--tenant' => [$id]])->assertExitCode(0);

        foreach ([$finalNamed, $prePurgeNamed, $finalByLedgerOnly] as $protected) {
            Storage::disk('google')->assertExists($protected);
            $this->assertTrue(TenantBackup::query()->where('path', $protected)->exists(), 'A protected archive keeps its ledger row.');
        }

        foreach ([$scheduledOld, $scheduledOlder, $manualOld] as $pruned) {
            Storage::disk('google')->assertMissing($pruned);
            $this->assertFalse(TenantBackup::query()->where('path', $pruned)->exists());
        }

        $newest = TenantBackup::query()->where('tenant_id', $id)->latest('id')->firstOrFail();
        $this->assertSame(TenantBackupKind::Scheduled, $newest->kind, 'A plain run records the scheduled kind.');
        $this->assertNotNull($newest->verified_at);
    }

    public function test_the_kind_option_is_validated(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();

        $this->artisan('backup:tenants', ['--kind' => 'weekly'])
            ->expectsOutputToContain((string) __('console.backups.kind_invalid', ['kinds' => implode(', ', TenantBackupKind::values())]))
            ->assertExitCode(1);

        $this->artisan('backup:tenants', ['--kind' => 'final', '--skip-central' => true])
            ->expectsOutputToContain((string) __('console.backups.kind_needs_single_tenant', ['kind' => 'final']))
            ->assertExitCode(1);

        $this->artisan('backup:tenants', ['--kind' => 'pre_purge', '--tenant' => [$id]])
            ->expectsOutputToContain((string) __('console.backups.kind_needs_single_tenant', ['kind' => 'pre_purge']))
            ->assertExitCode(1);

        $this->assertSame(0, TenantBackup::query()->count(), 'A refused run writes nothing.');

        $this->artisan('backup:tenants', ['--kind' => 'manual', '--tenant' => [$id], '--no-cleanup' => true])->assertExitCode(0);
        $manual = TenantBackup::query()->where('tenant_id', $id)->sole();
        $this->assertSame(TenantBackupKind::Manual, $manual->kind);
        $this->assertStringEndsWith('.zip', $manual->path);
        $this->assertStringNotContainsString('-manual', $manual->path, 'Only protected kinds carry their kind in the name.');
    }

    private function archive(string $tenantId, CarbonImmutable $at, TenantBackupKind $kind, bool $suffix = false): string
    {
        $name = $tenantId.'-'.$at->format(BackupTenantsCommand::TIMESTAMP_FORMAT).($suffix ? '-'.$kind->value : '').'.zip';
        $path = 'sroor-backups/'.$tenantId.'/'.$name;

        Storage::disk('google')->put($path, 'archive '.$kind->value);
        TenantBackup::query()->create([
            'tenant_id' => $tenantId,
            'disk' => 'google',
            'path' => $path,
            'sha256' => str_repeat('0', 64),
            'size_bytes' => 10,
            'kind' => $kind,
            'verified_at' => $at,
        ]);

        return $path;
    }
}
