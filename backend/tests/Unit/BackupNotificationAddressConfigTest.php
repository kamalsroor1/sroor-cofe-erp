<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Backup\Config\Config;
use Tests\TestCase;

/**
 * The rendered production .env always carries BACKUP_NOTIFICATION_EMAIL / MAIL_FROM_ADDRESS,
 * empty in release rehearsal mode. An empty value must fall back instead of reaching spatie,
 * which rejects '' at boot ("is not a valid email address") and broke the first deploy.
 */
final class BackupNotificationAddressConfigTest extends TestCase
{
    private const KEYS = ['BACKUP_NOTIFICATION_EMAIL', 'MAIL_FROM_ADDRESS', 'HEALTH_TO_ADDRESS'];

    /** @var array<string, string|false> */
    private array $saved = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::KEYS as $key) {
            $this->saved[$key] = getenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $key => $value) {
            $this->setEnv($key, $value === false ? null : $value);
        }

        parent::tearDown();
    }

    /** @return array<string, array{array<string, string>, string}> */
    public static function addresses(): array
    {
        return [
            'notification address wins' => [['BACKUP_NOTIFICATION_EMAIL' => 'ops@example.org', 'MAIL_FROM_ADDRESS' => 'noreply@example.org'], 'ops@example.org'],
            'empty notification falls back to sender' => [['BACKUP_NOTIFICATION_EMAIL' => '', 'MAIL_FROM_ADDRESS' => 'noreply@example.org'], 'noreply@example.org'],
            'both empty falls back to placeholder' => [['BACKUP_NOTIFICATION_EMAIL' => '', 'MAIL_FROM_ADDRESS' => ''], 'hello@example.com'],
        ];
    }

    /** @param array<string, string> $env */
    #[DataProvider('addresses')]
    public function test_empty_addresses_fall_back_and_spatie_accepts_the_config(array $env, string $expectedTo): void
    {
        foreach ($env as $key => $value) {
            $this->setEnv($key, $value);
        }

        $backup = require base_path('config/backup.php');

        $this->assertSame($expectedTo, $backup['notifications']['mail']['to']);
        $this->assertNotSame('', $backup['notifications']['mail']['from']['address']);

        // Spatie validates every address when it builds its config object (this threw on '').
        $config = Config::fromArray($backup);
        $this->assertSame($expectedTo, $config->notifications->mail->to);
    }

    public function test_health_mail_recipient_ignores_empty_values(): void
    {
        $this->setEnv('HEALTH_TO_ADDRESS', '');
        $this->setEnv('BACKUP_NOTIFICATION_EMAIL', '');
        $this->setEnv('MAIL_FROM_ADDRESS', 'noreply@example.org');

        $health = require base_path('config/health.php');

        $this->assertSame('noreply@example.org', $health['notifications']['mail']['to']);
    }

    private function setEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
