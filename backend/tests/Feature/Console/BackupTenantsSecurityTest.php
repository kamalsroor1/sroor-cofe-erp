<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\BackupTenantsCommand;
use App\Health\Notifications\BackupFailedNotification;
use App\Models\TenantBackup;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * Security audit (W2 lane 3I), MEDIUM, on `backup:tenants`:
 *  - without BACKUP_ARCHIVE_PASSWORD it refuses to run in EVERY environment except local
 *    and testing (staging, `prod`, a typo... no longer upload plain archives);
 *  - the failure mail only carries the subject and the exception class; the exception
 *    message (paths, hosts, SQL) stays in the log and on the console.
 */
final class BackupTenantsSecurityTest extends TenantTestCase
{
    private const PASSWORD = 'fixture-archive-password-not-a-secret-0001';

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('google');
        Notification::fake();

        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sroor-backup-sec-'.bin2hex(random_bytes(5));

        config([
            'backup.backup.password' => self::PASSWORD,
            'backup.tenants.disks' => ['google'],
            'backup.tenants.path_prefix' => 'sroor-backups',
            'backup.tenants.notify_mail' => 'ops@example.test',
            'backup.tenants.temporary_directory' => $this->tmp,
            'backup.tenants.verify_after_upload' => true,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);

        parent::tearDown();
    }

    /** @return array<string, array{0: string}> */
    public static function nonDevelopmentEnvironments(): array
    {
        return [
            'production' => ['production'],
            'staging' => ['staging'],
            'typo' => ['prod'],
        ];
    }

    #[DataProvider('nonDevelopmentEnvironments')]
    public function test_it_refuses_without_an_archive_password_outside_local_and_testing(string $environment): void
    {
        $this->createTenant();
        config(['backup.backup.password' => null]);
        $this->app->detectEnvironment(static fn (): string => $environment);

        $this->artisan('backup:tenants', ['--skip-central' => true])
            ->expectsOutputToContain((string) __('console.backups.password_missing'))
            ->assertExitCode(1);

        $this->assertSame(0, TenantBackup::query()->count());
        $this->assertSame([], Storage::disk('google')->allFiles());
        Notification::assertSentOnDemand(BackupFailedNotification::class);
    }

    public function test_local_without_an_archive_password_only_warns(): void
    {
        $tenant = $this->createTenant();
        config(['backup.backup.password' => null]);
        $this->app->detectEnvironment(static fn (): string => 'local');

        $this->artisan('backup:tenants', ['--tenant' => [(string) $tenant->getTenantKey()]])
            ->expectsOutputToContain((string) __('console.backups.password_missing_dev'))
            ->assertExitCode(0);

        $this->assertSame(1, TenantBackup::query()->where('tenant_id', $tenant->getTenantKey())->count());
        $this->assertSame(['local', 'testing'], BackupTenantsCommand::UNENCRYPTED_ENVIRONMENTS);
    }

    public function test_the_failure_mail_carries_the_exception_class_only(): void
    {
        $missing = 'no-such-tenant-'.bin2hex(random_bytes(3));
        $detail = (string) __('console.backups.tenant_not_found', ['tenant' => $missing]);

        // The details still reach the operator's console (and the log).
        $this->artisan('backup:tenants', ['--tenant' => [$missing]])
            ->expectsOutputToContain($detail)
            ->assertExitCode(1);

        Notification::assertSentOnDemand(
            BackupFailedNotification::class,
            static fn (BackupFailedNotification $notification): bool => $notification->failures === [$missing => RuntimeException::class],
        );

        Notification::assertSentOnDemand(
            BackupFailedNotification::class,
            function (BackupFailedNotification $notification, array $channels, object $notifiable) use ($detail): bool {
                $mail = $notification->toMail($notifiable);
                $text = implode("\n", array_map('strval', array_merge($mail->introLines, $mail->outroLines)));

                $this->assertStringNotContainsString($detail, $text);
                $this->assertStringContainsString(RuntimeException::class, $text);

                return true;
            },
        );
    }
}
