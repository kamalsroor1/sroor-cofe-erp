<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Tasks\Backup\FileSelection;
use Tests\TestCase;

/**
 * W1 hardening #3: backup archives leave the server (Google Drive), so they must
 * never contain `.env` or key material. Database dumps stay in.
 *
 * The file selection is built exactly like spatie's BackupJobFactory does, against a
 * throwaway storage tree, so this proves what would really end up in the zip.
 */
final class BackupConfigTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sroor-backup-test-'.bin2hex(random_bytes(6));
        File::makeDirectory($this->root, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_archive_contains_user_files_but_no_secrets_or_runtime_state(): void
    {
        $config = $this->backupConfigForStorage($this->root);
        $backupName = $config['backup']['name'];

        $kept = [
            'app/public/logo.png',
            'app/private/imports/items.xlsx',
            'app/central/public/app.apk',
            'tenant42/app/public/items/photo.jpg',
            'tenant42/app/private/report.pdf',
        ];
        $dropped = [
            'oauth-private.key',
            'oauth-public.key',
            'server.pem',
            'app/private/google-drive-oauth.json',
            'app/private/backup.key',
            'logs/laravel.log',
            'framework/cache/data/aa/bb',
            'framework/sessions/abc',
            'tenant42/framework/cache/data/x',
            'tenant42/logs/laravel.log',
            'app/backup-temp/temp/db-dumps/central.sql',
            'app/private/'.$backupName.'/2026-10-01-00-00-00.zip',
        ];

        foreach ([...$kept, ...$dropped] as $file) {
            $this->touch($file);
        }

        $selected = $this->selectedRelativeFiles($config);

        foreach ($kept as $file) {
            $this->assertContains($file, $selected, "User file `{$file}` must be in the backup.");
        }
        foreach ($dropped as $file) {
            $this->assertNotContains($file, $selected, "`{$file}` must never be in the backup archive.");
        }
    }

    public function test_env_files_are_outside_the_include_roots_and_explicitly_excluded(): void
    {
        $config = require config_path('backup.php');
        $files = $config['backup']['source']['files'];
        $env = $this->normalize(base_path('.env'));

        foreach ($files['include'] as $include) {
            $this->assertFalse(
                str_starts_with($env, rtrim($this->normalize($include), '/').'/'),
                "Include root `{$include}` contains .env."
            );
        }

        // Defence in depth: re-adding base_path() to `include` still cannot leak them.
        $this->assertContains(base_path('.env'), $files['exclude']);
        $this->assertContains(base_path('.env.*'), $files['exclude']);
        $this->assertContains(storage_path('*.key'), $files['exclude']);
        $this->assertFalse($files['follow_links'], 'Following symlinks could pull shared .env files into the archive.');
    }

    public function test_database_dumps_encryption_and_retention_are_configured(): void
    {
        $config = require config_path('backup.php');

        $this->assertNotEmpty($config['backup']['source']['databases'], 'Database dumps must stay in the backup.');
        $this->assertContains(config('tenancy.database.central_connection'), $config['backup']['source']['databases']);

        $this->assertSame('aes256', $config['backup']['encryption']);
        $this->assertTrue($config['backup']['verify_backup']);

        $retention = $config['cleanup']['default_strategy'];
        $this->assertSame(7, $retention['keep_all_backups_for_days']);
        $this->assertSame(4, $retention['keep_weekly_backups_for_weeks']);
        $this->assertSame(3, $retention['keep_monthly_backups_for_months']);

        // The package accepts the published config as is.
        $this->assertInstanceOf(Config::class, Config::fromArray($config));
    }

    /** @return array<string, mixed> */
    private function backupConfigForStorage(string $storage): array
    {
        $original = $this->app->storagePath();
        $this->app->useStoragePath($storage);

        try {
            return require config_path('backup.php');
        } finally {
            $this->app->useStoragePath($original);
        }
    }

    /**
     * Same construction as Spatie\Backup\Tasks\Backup\BackupJobFactory::createFileSelection().
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function selectedRelativeFiles(array $config): array
    {
        $files = Config::fromArray($config)->backup->source->files;

        $selection = FileSelection::create($files->include)
            ->excludeFilesFrom($files->exclude)
            ->shouldFollowLinks($files->followLinks)
            ->shouldIgnoreUnreadableDirs($files->ignoreUnreadableDirectories);

        $root = rtrim($this->normalize((string) realpath($this->root)), '/').'/';
        $relative = [];
        foreach ($selection->selectedFiles() as $path) {
            $real = realpath($path);
            if ($real === false || is_dir($real)) {
                continue;
            }
            $relative[] = substr($this->normalize($real), strlen($root));
        }

        return $relative;
    }

    private function touch(string $relative): void
    {
        $path = $this->root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'fixture');
    }

    private function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
