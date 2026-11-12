<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * BUG-07 condition: TransactionRetry runs the money/stock writes at READ COMMITTED. With the
 * binary log ON and binlog_format = STATEMENT, InnoDB refuses every write of such a transaction
 * (error 1665, "Binlogging impossible ... READ COMMITTED"), which is not a lock conflict: every
 * sale would fail. MySQL 8.4 defaults to ROW and MariaDB to MIXED, both fine; this catches a
 * server where someone set STATEMENT.
 *
 * Reads the session values the app itself gets (@@log_bin, @@binlog_format) on the central
 * connection and, when configured separately, on the tenant template connection (tenant
 * databases live on that server). Read-only, never throws: an unreachable connection is
 * reported as such and is the DatabaseCheck's business.
 */
final class WriteIsolationBinlog
{
    /**
     * @return list<array{connection: string, problem: 'statement_binlog'|'unreachable', log_bin: string, binlog_format: string}>
     */
    public static function violations(): array
    {
        if (TransactionRetry::isolationLevel() !== 'READ COMMITTED') {
            return [];
        }

        $problems = [];
        foreach (self::connections() as $name) {
            try {
                $connection = DB::connection($name);
                if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
                    continue;
                }

                $row = (array) $connection->selectOne('SELECT @@log_bin AS log_bin, @@binlog_format AS binlog_format');
            } catch (Throwable) {
                $problems[] = ['connection' => $name, 'problem' => 'unreachable', 'log_bin' => '', 'binlog_format' => ''];

                continue;
            }

            $problem = self::evaluate($name, $row);
            if ($problem !== null) {
                $problems[] = $problem;
            }
        }

        return $problems;
    }

    /**
     * The rule itself, for one connection's `SELECT @@log_bin, @@binlog_format` row.
     *
     * @param  array<string, mixed>  $row
     * @return array{connection: string, problem: 'statement_binlog', log_bin: string, binlog_format: string}|null
     */
    public static function evaluate(string $connection, array $row): ?array
    {
        $logBin = strtoupper(trim((string) ($row['log_bin'] ?? '')));
        $format = strtoupper(trim((string) ($row['binlog_format'] ?? '')));

        if (in_array($logBin, ['1', 'ON'], true) && $format === 'STATEMENT') {
            return ['connection' => $connection, 'problem' => 'statement_binlog', 'log_bin' => $logBin, 'binlog_format' => $format];
        }

        return null;
    }

    /** @return list<string> */
    private static function connections(): array
    {
        $central = (string) config('tenancy.database.central_connection', config('database.default'));
        $template = config('tenancy.database.template_tenant_connection');

        $names = [$central];
        if (is_string($template) && $template !== '' && $template !== $central) {
            $names[] = $template;
        }

        return $names;
    }
}
