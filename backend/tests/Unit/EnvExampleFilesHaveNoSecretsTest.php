<?php

declare(strict_types=1);

namespace Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * OPS-4: every tracked env template (`.env.example`, `*.env.example`) holds keys
 * and safe defaults only. Secret-bearing keys must be empty (or the literal
 * `null` Laravel ships with), and no value anywhere may look like a real
 * credential.
 *
 * Failures name the FILE and the KEY only. A matched value is never echoed, so
 * a CI log cannot leak it.
 */
final class EnvExampleFilesHaveNoSecretsTest extends TestCase
{
    /**
     * Keys whose value is a credential. Suffix match on the upper-cased key.
     * `*_FILE`, `*_TTL*`, `*_ENABLED`, `*_USER` keys never match these suffixes.
     */
    private const SECRET_KEY_PATTERN = '/(PASSWORD|PASSWD|_PASS|SECRET|TOKEN|APP_KEY|API_KEY|ACCESS_KEY|ACCESS_KEY_ID|PRIVATE_KEY|ENCRYPTION_KEY|CLIENT_ID|DSN|SALT)$/';

    /** Value shapes that are credentials whatever the key is called. */
    private const SECRET_VALUE_PATTERNS = [
        'laravel-app-key' => '/^base64:[A-Za-z0-9+\/]{20,}={0,2}$/',
        'telegram-bot-token' => '/\b\d{8,10}:[A-Za-z0-9_-]{35}\b/',
        'aws-access-key' => '/\b(AKIA|ASIA)[A-Z0-9]{16}\b/',
        'private-key-block' => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
        'google-oauth-refresh-token' => '/\b1\/\/0[A-Za-z0-9_-]{20,}/',
        'long-opaque-string' => '/^[A-Za-z0-9+\/=_-]{32,}$/',
    ];

    /** Placeholders that are allowed for secret keys. */
    private const EMPTY_PLACEHOLDERS = ['', 'null'];

    private const SKIP_DIRS = ['vendor', 'node_modules', '.git', 'worktrees', 'public', 'storage', 'backups'];

    #[DataProvider('envExampleFiles')]
    public function test_secret_keys_are_empty(string $relativePath): void
    {
        $offending = [];

        foreach ($this->parse($relativePath) as $key => $value) {
            if (preg_match(self::SECRET_KEY_PATTERN, strtoupper($key)) !== 1) {
                continue;
            }

            if (! in_array(strtolower($value), self::EMPTY_PLACEHOLDERS, true)) {
                $offending[] = $key;
            }
        }

        $this->assertSame(
            [],
            $offending,
            sprintf('%s: secret keys must be empty in an env template (values not shown): %s', $relativePath, implode(', ', $offending)),
        );
    }

    #[DataProvider('envExampleFiles')]
    public function test_no_value_looks_like_a_credential(string $relativePath): void
    {
        $offending = [];

        foreach ($this->parse($relativePath) as $key => $value) {
            foreach (self::SECRET_VALUE_PATTERNS as $shape => $pattern) {
                if (preg_match($pattern, $value) === 1) {
                    $offending[] = "{$key} ({$shape})";
                }
            }
        }

        $this->assertSame(
            [],
            $offending,
            sprintf('%s: values shaped like credentials (values not shown): %s', $relativePath, implode(', ', $offending)),
        );
    }

    public function test_the_backend_template_is_discovered(): void
    {
        $this->assertContains('backend/.env.example', array_keys(self::envExampleFiles()));
    }

    /**
     * The detector itself must catch what it claims to catch, otherwise a green
     * run proves nothing. Fixtures are built at runtime so no credential-shaped
     * literal lives in this file.
     */
    public function test_detector_flags_planted_values(): void
    {
        $fakeAppKey = 'base64:'.str_repeat('Q', 43).'=';
        $fakeTelegram = str_repeat('1', 10).':'.str_repeat('A', 35);
        $fakeOpaque = str_repeat('x9', 20);

        $this->assertSame(1, preg_match(self::SECRET_VALUE_PATTERNS['laravel-app-key'], $fakeAppKey));
        $this->assertSame(1, preg_match(self::SECRET_VALUE_PATTERNS['telegram-bot-token'], $fakeTelegram));
        $this->assertSame(1, preg_match(self::SECRET_VALUE_PATTERNS['long-opaque-string'], $fakeOpaque));

        foreach (['DB_PASSWORD', 'REDIS_PASSWORD', 'TELEGRAM_BOT_TOKEN', 'APP_KEY', 'AWS_SECRET_ACCESS_KEY', 'AWS_ACCESS_KEY_ID', 'DEPLOY_WEBHOOK_HMAC_SECRET', 'GOOGLE_DRIVE_CLIENT_SECRET', 'GOOGLE_DRIVE_REFRESH_TOKEN', 'BACKUP_ARCHIVE_PASSWORD'] as $key) {
            $this->assertSame(1, preg_match(self::SECRET_KEY_PATTERN, $key), "{$key} must be treated as a secret key");
        }

        foreach (['QUICK_LOGIN_TOKEN_TTL', 'TENANT_TOKEN_TTL_MINUTES', 'CERTBOT_DNS_CREDENTIALS_FILE', 'MYSQL_APP_USER', 'TELEGRAM_NOTIFICATIONS_ENABLED', 'ADMIN_SSH_PUBKEY_FILE'] as $key) {
            $this->assertSame(0, preg_match(self::SECRET_KEY_PATTERN, $key), "{$key} is configuration, not a secret");
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function envExampleFiles(): array
    {
        $root = self::repoRoot();
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                static function (SplFileInfo $file): bool {
                    if ($file->isDir()) {
                        return ! in_array($file->getFilename(), self::SKIP_DIRS, true);
                    }

                    return str_ends_with($file->getFilename(), '.env.example')
                        || $file->getFilename() === '.env.example';
                },
            ),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $files[$relative] = [$relative];
        }

        ksort($files);

        return $files;
    }

    /**
     * Minimal dotenv reader: KEY=VALUE lines, optional `export`, surrounding
     * quotes stripped, inline ` #` comments dropped on unquoted values.
     *
     * @return array<string, string>
     */
    private function parse(string $relativePath): array
    {
        $lines = file(self::repoRoot().'/'.$relativePath, FILE_IGNORE_NEW_LINES);
        $this->assertIsArray($lines, "{$relativePath} is not readable");

        $values = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', rtrim($line, "\r"), $m) !== 1) {
                continue;
            }

            $raw = trim($m[2]);

            if (preg_match('/^"((?:[^"\\\\]|\\\\.)*)"/', $raw, $q) === 1 || preg_match("/^'([^']*)'/", $raw, $q) === 1) {
                $value = $q[1];
            } else {
                $value = trim((string) preg_replace('/\s+#.*$/', '', $raw));
            }

            $values[$m[1]] = $value;
        }

        return $values;
    }

    private static function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }
}
