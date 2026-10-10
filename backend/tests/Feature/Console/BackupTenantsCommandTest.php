<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\BackupTenantsCommand;
use App\Health\Notifications\BackupFailedNotification;
use App\Models\Tenant;
use App\Models\TenantBackup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TenantTestCase;
use ZipArchive;

/**
 * OPS-5: `backup:tenants` dumps the central DB and every tenant DB (each inside its own
 * tenancy), writes one AES-256 archive per database, uploads it, records and verifies it
 * in the central ledger, applies retention 7/4/3, never lets one failing tenant stop the
 * others, and refuses to run in production without BACKUP_ARCHIVE_PASSWORD (D4).
 */
final class BackupTenantsCommandTest extends TenantTestCase
{
    private const PASSWORD = 'fixture-archive-password-not-a-secret-0000';

    private const MAIL = 'ops@example.test';

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('google');
        Notification::fake();

        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sroor-backup-cmd-'.bin2hex(random_bytes(5));

        config([
            'backup.backup.password' => self::PASSWORD,
            'backup.tenants.disks' => ['google'],
            'backup.tenants.path_prefix' => 'sroor-backups',
            'backup.tenants.notify_mail' => self::MAIL,
            'backup.tenants.temporary_directory' => $this->tmp,
            'backup.tenants.verify_after_upload' => true,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        DB::purge('backup_central_probe');

        parent::tearDown();
    }

    public function test_every_tenant_gets_an_encrypted_verified_archive_with_row_counts(): void
    {
        $first = $this->createTenant();
        $second = $this->createTenant();
        $this->createTenantUser($second);

        $this->artisan('backup:tenants', ['--skip-central' => true])->assertExitCode(0);

        foreach ([$first, $second] as $tenant) {
            $id = (string) $tenant->getTenantKey();
            $row = TenantBackup::query()->where('tenant_id', $id)->sole();

            $this->assertSame('google', $row->disk);
            $this->assertMatchesRegularExpression('#^sroor-backups/'.preg_quote($id, '#').'/'.preg_quote($id, '#').'-\d{4}(-\d{2}){5}\.zip$#', $row->path);
            $this->assertNotNull($row->verified_at, 'The uploaded copy must be read back and verified.');
            Storage::disk('google')->assertExists($row->path);

            $local = Storage::disk('google')->path($row->path);
            $this->assertSame(hash_file('sha256', $local), $row->sha256);
            $this->assertSame(filesize($local), $row->size_bytes);

            $this->assertEveryEntryIsAes256($local);
            $this->assertFalse($this->readEntry($local, BackupTenantsCommand::MANIFEST, null), 'The manifest must not be readable without the password.');

            $manifest = BackupTenantsCommand::readManifest($local, self::PASSWORD);
            $this->assertSame($id, $manifest['subject']);
            $this->assertSame($id, $manifest['tenant_id']);
            $this->assertSame('database.sqlite', $manifest['dump']);
            $users = $this->inTenant($tenant, fn (): int => User::query()->count());
            $this->assertSame($users, $manifest['tables']['users']);
        }

        $this->assertSame(0, TenantBackup::query()->whereNull('tenant_id')->count(), '--skip-central must not dump the central DB.');
        $this->assertSame([], File::isDirectory($this->tmp) ? File::allFiles($this->tmp) : [], 'Plain dumps must never stay on disk.');
        $this->assertFalse(tenancy()->initialized, 'Tenancy must be ended after the run.');
        Notification::assertNothingSent();
    }

    public function test_one_failing_tenant_does_not_stop_the_others_and_is_reported(): void
    {
        $broken = $this->createTenantWithoutDatabase();
        $good = $this->createTenant();

        $this->artisan('backup:tenants', ['--skip-central' => true])->assertExitCode(1);

        $this->assertSame(1, TenantBackup::query()->where('tenant_id', $good->getTenantKey())->whereNotNull('verified_at')->count());
        $this->assertSame(0, TenantBackup::query()->where('tenant_id', $broken)->count());
        $this->assertFalse(tenancy()->initialized);

        Notification::assertSentOnDemand(
            BackupFailedNotification::class,
            static fn (BackupFailedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => array_key_exists($broken, $notification->failures)
                && ! array_key_exists((string) $good->getTenantKey(), $notification->failures)
                && $notifiable->routes['mail'] === self::MAIL,
        );
    }

    public function test_d4_refuses_to_run_in_production_without_an_archive_password(): void
    {
        $this->createTenant();
        config(['backup.backup.password' => null]);
        $this->app->detectEnvironment(static fn (): string => 'production');

        $this->artisan('backup:tenants')
            ->expectsOutputToContain((string) __('console.backups.password_missing'))
            ->assertExitCode(1);

        $this->assertSame(0, TenantBackup::query()->count());
        $this->assertSame([], Storage::disk('google')->allFiles());
        Notification::assertSentOnDemand(BackupFailedNotification::class);
    }

    public function test_the_central_database_is_backed_up_as_its_own_subject(): void
    {
        $file = $this->tmp.DIRECTORY_SEPARATOR.'central-probe.sqlite';
        File::ensureDirectoryExists($this->tmp);
        touch($file);
        config([
            'database.connections.backup_central_probe' => ['driver' => 'sqlite', 'database' => $file, 'prefix' => '', 'foreign_key_constraints' => true],
            'backup.tenants.central_connection' => 'backup_central_probe',
        ]);
        DB::connection('backup_central_probe')->statement('create table probe (id integer primary key, name text)');
        DB::connection('backup_central_probe')->table('probe')->insert([['name' => 'a'], ['name' => 'b'], ['name' => 'c']]);

        $this->artisan('backup:tenants', ['--only-central' => true])->assertExitCode(0);

        $row = TenantBackup::query()->whereNull('tenant_id')->sole();
        $this->assertStringStartsWith('sroor-backups/central/central-', $row->path);
        $this->assertNotNull($row->verified_at);

        $manifest = BackupTenantsCommand::readManifest(Storage::disk('google')->path($row->path), self::PASSWORD);
        $this->assertSame('central', $manifest['subject']);
        $this->assertNull($manifest['tenant_id']);
        $this->assertSame(['probe' => 3], $manifest['tables']);
    }

    public function test_archived_tenants_are_skipped_unless_requested_explicitly(): void
    {
        $active = $this->createTenant();
        $archived = $this->createTenant();
        Tenant::query()->whereKey($archived->getTenantKey())->update(['status' => 'archived']);

        $this->artisan('backup:tenants', ['--skip-central' => true])->assertExitCode(0);
        $this->assertSame([(string) $active->getTenantKey()], TenantBackup::query()->pluck('tenant_id')->all());

        $this->artisan('backup:tenants', ['--tenant' => [(string) $archived->getTenantKey()]])->assertExitCode(0);
        $this->assertSame(1, TenantBackup::query()->where('tenant_id', $archived->getTenantKey())->count());
        $this->assertSame(0, TenantBackup::query()->whereNull('tenant_id')->count(), '--tenant never adds the central DB.');
    }

    public function test_an_unknown_tenant_id_fails_the_run(): void
    {
        $this->artisan('backup:tenants', ['--tenant' => ['no-such-tenant']])
            ->expectsOutputToContain((string) __('console.backups.tenant_not_found', ['tenant' => 'no-such-tenant']))
            ->assertExitCode(1);
    }

    public function test_retention_keeps_7_daily_4_weekly_3_monthly_and_drops_ledger_rows(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();
        $now = CarbonImmutable::now();
        $paths = [];
        for ($days = 1; $days <= 150; $days++) {
            $path = 'sroor-backups/'.$id.'/'.$id.'-'.$now->subDays($days)->format(BackupTenantsCommand::TIMESTAMP_FORMAT).'.zip';
            Storage::disk('google')->put($path, 'old archive');
            TenantBackup::query()->create(['tenant_id' => $id, 'disk' => 'google', 'path' => $path, 'sha256' => str_repeat('0', 64), 'size_bytes' => 11]);
            $paths[$days] = $path;
        }
        $foreign = 'sroor-backups/'.$id.'/notes.txt';
        Storage::disk('google')->put($foreign, 'not an archive');

        $this->artisan('backup:tenants', ['--tenant' => [$id]])->assertExitCode(0);

        $remaining = array_values(array_filter(
            Storage::disk('google')->files('sroor-backups/'.$id),
            static fn (string $file): bool => str_ends_with($file, '.zip'),
        ));
        // today + 6 full days kept + ~4 weekly + ~3 monthly (bucket edges may add one each)
        $this->assertGreaterThanOrEqual(14, count($remaining));
        $this->assertLessThanOrEqual(17, count($remaining));

        foreach ([1, 2, 6] as $days) {
            Storage::disk('google')->assertExists($paths[$days]);
        }
        foreach ([140, 150] as $days) {
            Storage::disk('google')->assertMissing($paths[$days]);
            $this->assertSame(0, TenantBackup::query()->where('path', $paths[$days])->count(), 'Pruned archives leave the ledger too.');
        }
        Storage::disk('google')->assertExists($foreign);
        $this->assertSame(count($remaining), TenantBackup::query()->where('tenant_id', $id)->count());
    }

    public function test_retention_policy_selection(): void
    {
        $now = CarbonImmutable::parse('2026-10-10 01:30:00');
        $backups = [];
        for ($days = 0; $days < 365; $days++) {
            $backups['b'.$days] = $now->subDays($days);
        }

        $delete = BackupTenantsCommand::selectForDeletion($backups, $now, [
            'keep_all_backups_for_days' => 7,
            'keep_weekly_backups_for_weeks' => 4,
            'keep_monthly_backups_for_months' => 3,
        ]);
        $kept = array_diff(array_keys($backups), $delete);

        for ($days = 0; $days <= 7; $days++) {
            $this->assertContains('b'.$days, $kept, "Daily backup of day {$days} must be kept.");
        }
        $this->assertGreaterThanOrEqual(14, count($kept));
        $this->assertLessThanOrEqual(17, count($kept));
        $keptAges = array_map(static fn (string $key): int => (int) substr($key, 1), $kept);
        $this->assertLessThanOrEqual(7 + 28 + 93, max($keptAges), 'Nothing older than 7 days + 4 weeks + 3 months survives.');

        $this->assertSame([], BackupTenantsCommand::selectForDeletion(['only' => $now->subYears(2)], $now, []), 'The newest archive is never deleted.');
    }

    /** A central tenant row whose database was never created (stancl pipeline stopped). */
    private function createTenantWithoutDatabase(): string
    {
        $id = 'qa'.Str::lower(Str::random(12));
        Tenant::query()->create([
            'id' => $id,
            'name' => 'broken '.$id,
            'slug' => $id,
            'email' => $id.'@harness.test',
            'status' => 'active',
            'enabled_features' => [],
            'tenancy_create_database' => false,
        ]);

        return $id;
    }

    private function assertEveryEntryIsAes256(string $archive): void
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archive, ZipArchive::RDONLY));
        $this->assertSame(2, $zip->numFiles);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $this->assertIsArray($stat);
            $this->assertSame(ZipArchive::EM_AES_256, $stat['encryption_method'], "Entry {$stat['name']} must be AES-256 encrypted.");
        }
        $zip->close();
    }

    private function readEntry(string $archive, string $entry, ?string $password): string|false
    {
        $zip = new ZipArchive;
        $zip->open($archive, ZipArchive::RDONLY);
        if ($password !== null) {
            $zip->setPassword($password);
        }
        $content = $zip->getFromName($entry);
        $zip->close();

        return $content;
    }
}
