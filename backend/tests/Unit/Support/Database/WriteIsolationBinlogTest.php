<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Database;

use App\Health\Checks\WriteIsolationBinlogCheck;
use App\Support\Database\WriteIsolationBinlog;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Health\Enums\Status;
use Tests\TestCase;

/**
 * BUG-07 condition: READ COMMITTED writes are refused by InnoDB when the binary log is ON with
 * binlog_format=STATEMENT (error 1665). The rule, the artisan command and the health check.
 * The live-server reading is exercised on MySQL/MariaDB by the `mysql` group runs; this suite runs
 * on sqlite, where there is no binary log to read.
 */
final class WriteIsolationBinlogTest extends TestCase
{
    /** @return array<string, array{array<string, mixed>, bool}> */
    public static function serverRows(): array
    {
        return [
            'binlog on, STATEMENT (MySQL reports 1)' => [['log_bin' => 1, 'binlog_format' => 'STATEMENT'], true],
            'binlog on, statement (lower case, ON)' => [['log_bin' => 'ON', 'binlog_format' => 'statement'], true],
            'binlog on, ROW (MySQL 8.4 default)' => [['log_bin' => 1, 'binlog_format' => 'ROW'], false],
            'binlog on, MIXED (MariaDB default)' => [['log_bin' => 1, 'binlog_format' => 'MIXED'], false],
            'binlog off, STATEMENT' => [['log_bin' => 0, 'binlog_format' => 'STATEMENT'], false],
            'binlog OFF, STATEMENT' => [['log_bin' => 'OFF', 'binlog_format' => 'STATEMENT'], false],
        ];
    }

    /** @param array<string, mixed> $row */
    #[DataProvider('serverRows')]
    public function test_the_rule_fails_only_on_binlog_on_with_statement_format(array $row, bool $violates): void
    {
        $result = WriteIsolationBinlog::evaluate('mysql', $row);

        $this->assertSame($violates, $result !== null);
        if ($violates) {
            $this->assertSame('statement_binlog', $result['problem'] ?? null);
            $this->assertSame('mysql', $result['connection'] ?? null);
        }
    }

    public function test_nothing_is_checked_when_the_server_isolation_is_kept(): void
    {
        config(['database.transaction_retry.isolation' => '']);

        $this->assertSame([], WriteIsolationBinlog::violations());
    }

    public function test_sqlite_has_no_binary_log_and_passes(): void
    {
        config(['database.transaction_retry.isolation' => 'READ COMMITTED']);

        $this->assertSame([], WriteIsolationBinlog::violations());
        $this->artisan('db:check-write-isolation')->assertExitCode(0);
        $this->assertSame(Status::ok()->value, WriteIsolationBinlogCheck::new()->run()->status->value);
    }

    public function test_the_failure_message_is_translated_in_both_languages(): void
    {
        foreach (['ar', 'en'] as $locale) {
            foreach (['console.write_isolation.statement_binlog', 'console.write_isolation.unreachable', 'console.health.write_isolation_statement_binlog'] as $key) {
                $this->assertNotSame($key, trans($key, ['connection' => 'mysql', 'connections' => 'mysql'], $locale), "{$key} missing in {$locale}");
            }
        }
    }
}
