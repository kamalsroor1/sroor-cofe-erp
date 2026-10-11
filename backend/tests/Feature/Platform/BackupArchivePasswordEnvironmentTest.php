<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Console\Commands\BackupTenantsCommand;
use App\Health\Checks\BackupArchivePasswordCheck;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Health\Enums\Status;
use Tests\TenantTestCase;

/**
 * W2-B3 lane 3L: the backup command refuses an empty BACKUP_ARCHIVE_PASSWORD everywhere except
 * local/testing (BackupTenantsCommand::UNENCRYPTED_ENVIRONMENTS); the health check used to be
 * red in `production` only. Both now follow the same rule.
 */
final class BackupArchivePasswordEnvironmentTest extends TenantTestCase
{
    /** @return array<string, array{0: string}> */
    public static function refusingEnvironments(): array
    {
        return [
            'production' => ['production'],
            'staging' => ['staging'],
            'prod typo' => ['prod'],
        ];
    }

    #[DataProvider('refusingEnvironments')]
    public function test_an_empty_password_is_red_outside_local_and_testing(string $environment): void
    {
        config(['backup.backup.password' => null]);
        $this->app->detectEnvironment(static fn (): string => $environment);

        $this->assertSame(Status::failed(), BackupArchivePasswordCheck::new()->run()->status);
        $this->assertSame(Status::failed(), BackupArchivePasswordCheck::new()->failuresOnly()->run()->status);
    }

    public function test_an_empty_password_is_only_a_warning_in_local_and_testing(): void
    {
        config(['backup.backup.password' => null]);

        foreach (BackupTenantsCommand::UNENCRYPTED_ENVIRONMENTS as $environment) {
            $this->app->detectEnvironment(static fn (): string => $environment);

            $this->assertSame(Status::warning(), BackupArchivePasswordCheck::new()->run()->status, $environment);
            $this->assertSame(Status::ok(), BackupArchivePasswordCheck::new()->failuresOnly()->run()->status, $environment);
        }
    }

    public function test_the_refusal_message_no_longer_mentions_production_only(): void
    {
        foreach (['en', 'ar'] as $locale) {
            $message = (string) __('console.backups.password_missing', [], $locale);

            $this->assertStringContainsString('local/testing', $message, $locale);
        }
    }

    protected function tearDown(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'testing');

        parent::tearDown();
    }
}
