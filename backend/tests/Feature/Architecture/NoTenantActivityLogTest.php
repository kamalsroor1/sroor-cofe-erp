<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * spatie/laravel-activitylog writes to the CENTRAL `activity_log` table: config/activitylog.php
 * points activity_model at App\Models\CentralActivity, which pins the central connection.
 * A tenant-side `activity()->log()` or a tenant model using `LogsActivity` would therefore copy
 * tenant business data (names, amounts, customers) into the platform database, shared by every
 * tenant. Tenant audit goes through App\Services\ActivityLogService (tenant `activity_logs`).
 *
 * This scans app/ (code only, comments and strings stripped) and fails on any use of the
 * activitylog API outside central code.
 */
final class NoTenantActivityLogTest extends TestCase
{
    /** Central (platform) code that may use the package. Paths relative to backend/, `/` separators. */
    private const CENTRAL_PREFIXES = [
        'app/Actions/Central/',
        'app/Http/Controllers/Api/Central/',
        // Central models (CentralActivity, CentralAuditLog extend the package's Activity model).
        'app/Models/Central',
        'app/Services/CentralAuditLogger.php',
    ];

    /** Patterns run on code with comments and string literals removed. */
    private const FORBIDDEN = [
        'activity() helper' => '/(?<![\w>$:\\\\])activity\s*\(/',
        'LogsActivity trait' => '/\bLogsActivity\b/',
        'activitylog namespace' => '/Spatie\\\\Activitylog\\\\/',
    ];

    public function test_no_tenant_side_code_uses_spatie_activitylog(): void
    {
        $root = dirname(__DIR__, 3);
        $offenders = [];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if ($this->isCentral($relative)) {
                continue;
            }

            foreach ($this->violations((string) file_get_contents($file->getPathname())) as $what) {
                $offenders[] = "{$relative}: {$what}";
            }
        }

        sort($offenders);

        $this->assertSame([], $offenders, "Tenant-side code must not use spatie/laravel-activitylog (it writes to the central activity_log):\n".implode("\n", $offenders));
    }

    public function test_scanner_flags_helper_trait_and_namespace_but_ignores_comments_strings_and_methods(): void
    {
        $bad = <<<'PHP'
            <?php
            use Spatie\Activitylog\Traits\LogsActivity;
            class Invoice { use LogsActivity; }
            activity()->log('sold');
            PHP;

        $clean = <<<'PHP'
            <?php
            // activity()->log() is forbidden here; LogsActivity too.
            /** Spatie\Activitylog\Models\Activity */
            $label = 'activity() LogsActivity';
            $this->activity('x');
            $service->activity();
            PHP;

        $this->assertSame(['activity() helper', 'LogsActivity trait', 'activitylog namespace'], $this->violations($bad));
        $this->assertSame([], $this->violations($clean));
    }

    private function isCentral(string $relative): bool
    {
        foreach (self::CENTRAL_PREFIXES as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string> names of the FORBIDDEN rules the source breaks
     */
    private function violations(string $source): array
    {
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML], true)) {
                    $code .= ' ';

                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }

        $found = [];
        foreach (self::FORBIDDEN as $name => $pattern) {
            if (preg_match($pattern, $code) === 1) {
                $found[] = $name;
            }
        }

        return $found;
    }
}
