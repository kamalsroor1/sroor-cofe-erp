<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * SETG-2 ext (CTO Settings Q6): every business date a document carries comes from
 * App\Support\TenantClock::businessDate() (tenant timezone + business_day_cutoff), never
 * from the server clock. This test fails when code under app/ stamps a date directly:
 *
 *   today()                          Carbon::today() / CarbonImmutable::today()
 *   now()->toDateString()            now()->format('Y-m-d') / ('Ymd')
 *   Carbon::now()->toDateString()    date('Y-m-d') / date('Ymd')   (single argument)
 *
 * TenantClock itself is exempt. ALLOWLIST holds only non-business uses (export file
 * names, console output); the business-date hits other W2 lanes left behind were fixed by
 * lane 2H. The list must only shrink: an entry that no longer matches fails the test so
 * it gets removed.
 */
final class TenantClockWriteSideArchitectureTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../../app';

    private const PATTERN = '/(?<![\w>:$])today\(\)'
        .'|Carbon(?:Immutable)?::(?:today\(|now\(\)->(?:toDateString\(\)|format\(\s*[\'"]Y-?m-?d))'
        .'|(?<![\w>:$])now\(\)->(?:toDateString\(\)|format\(\s*[\'"]Y-?m-?d[\'"]\s*\))'
        .'|(?<![\w>:$])date\(\s*[\'"]Y-?m-?d[^\'"]*[\'"]\s*\)/';

    /** Files that ARE the clock. */
    private const EXEMPT = [
        'Support/TenantClock.php',
    ];

    /**
     * path relative to app/ => why it is still allowed.
     *
     * @var array<string, string>
     */
    private const ALLOWLIST = [
        // Not a business date.
        'Actions/Logs/ExportActivityLogsCsvAction.php' => 'export file name',
        'Services/ExportService.php' => 'export file names',
        'Console/Commands/SendDailyTelegramSummaryCommand.php' => 'console info line only',
    ];

    public function test_no_direct_business_date_stamping_outside_tenant_clock(): void
    {
        $offenders = [];

        foreach ($this->hits() as $relative => $lines) {
            if (in_array($relative, self::EXEMPT, true) || array_key_exists($relative, self::ALLOWLIST)) {
                continue;
            }

            foreach ($lines as $line) {
                $offenders[] = "app/{$relative}: {$line}";
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Stamp business dates with App\\Support\\TenantClock::businessDate() (SETG-2 ext):\n".implode("\n", $offenders),
        );
    }

    public function test_allowlist_has_no_stale_entries(): void
    {
        $hits = $this->hits();
        $stale = array_values(array_filter(
            array_keys(self::ALLOWLIST),
            static fn (string $relative): bool => ! array_key_exists($relative, $hits),
        ));

        $this->assertSame([], $stale, 'Fixed — remove these entries from ALLOWLIST: '.implode(', ', $stale));
    }

    public function test_the_pattern_catches_every_forbidden_form(): void
    {
        $forbidden = [
            "'invoice_date' => now()->toDateString(),",
            '$d = today();',
            '$d = Carbon::today();',
            '$d = CarbonImmutable::today()->toDateString();',
            '$d = Carbon::now()->toDateString();',
            "\$d = now()->format('Y-m-d');",
            "\$n = 'INV-'.date('Ymd');",
            "\$d = date('Y-m-d');",
        ];
        $allowed = [
            '$d = $this->tenantClock->businessDate();',
            '$d = $this->tenantClock->today();',
            "\$d = date('Ymd', strtotime(\$given));",
            "'opened_at' => now(),",
            '$d = $carbon->toDateString();',
        ];

        foreach ($forbidden as $code) {
            $this->assertSame(1, preg_match(self::PATTERN, $code), "Not caught: {$code}");
        }
        foreach ($allowed as $code) {
            $this->assertSame(0, preg_match(self::PATTERN, $code), "False positive: {$code}");
        }
    }

    /**
     * @return array<string, list<string>> relative path => matching lines
     */
    private function hits(): array
    {
        $root = (string) realpath(self::APP_DIR);
        $hits = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

            foreach ((array) file($file->getPathname()) as $number => $line) {
                $line = (string) $line;
                $trimmed = ltrim($line);
                if (str_starts_with($trimmed, '*') || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/*')) {
                    continue;
                }

                if (preg_match(self::PATTERN, $line) === 1) {
                    $hits[$relative][] = ($number + 1).': '.trim($line);
                }
            }
        }

        ksort($hits);

        return $hits;
    }
}
