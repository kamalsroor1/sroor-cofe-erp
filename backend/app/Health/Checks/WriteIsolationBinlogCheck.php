<?php

declare(strict_types=1);

namespace App\Health\Checks;

use App\Support\Database\WriteIsolationBinlog;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * BUG-07 condition: the money/stock writes run at READ COMMITTED (DB_WRITE_ISOLATION), which
 * InnoDB refuses to write under binlog_format = STATEMENT with the binary log on (every sale
 * would fail with 1665). Red when that combination is found; never a warning, so it can sit in
 * the deploy gate. An unreachable connection is left to DatabaseCheck.
 */
final class WriteIsolationBinlogCheck extends Check
{
    public function run(): Result
    {
        $statement = array_values(array_filter(
            WriteIsolationBinlog::violations(),
            static fn (array $violation): bool => $violation['problem'] === 'statement_binlog',
        ));

        if ($statement !== []) {
            return Result::make()->failed((string) __('console.health.write_isolation_statement_binlog', [
                'connections' => implode(', ', array_column($statement, 'connection')),
            ]));
        }

        return Result::make()->ok();
    }
}
