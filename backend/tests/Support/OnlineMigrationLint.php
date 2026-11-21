<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * MIG-01: static check for tenant migrations that would stall selling on a large live shop.
 *
 * On a big table (thousands to millions of rows that every sale writes to):
 *  - a foreign key added with the schema builder (`->constrained()`, `->foreign()`) or a raw
 *    `ALTER TABLE … FOREIGN KEY` makes MySQL copy the whole table while every insert waits;
 *    use OnlineMigration::addNullableForeignKey() instead;
 *  - a backfill written as one UPDATE over the table holds row locks on all of it for the
 *    whole statement; walk it with OnlineMigration::forEachIdRange() instead.
 *
 * Deliberately a heuristic on the source text (comments stripped): table names must be
 * literal to be seen, and Eloquent model updates are not inspected. False positives go to the
 * allowlist of the architecture test with a reason; this class never reads the database.
 */
final class OnlineMigrationLint
{
    /** Tables large enough on a busy shop that a blocking ALTER/UPDATE stops the till. */
    public const BIG_TABLES = ['invoices', 'invoice_items', 'stock_movements', 'payments', 'store_stocks'];

    /**
     * @return list<string> one human-readable line per problem, empty when the code is online-safe
     */
    public static function violations(string $php): array
    {
        $code = self::stripComments($php);
        $violations = [];
        // Tables walked by forEachIdRange(): literal names only. A table passed as a variable
        // (foreach ([...] as $table)) exempts the file's variable-table updates, nothing else.
        preg_match_all('/OnlineMigration::forEachIdRange\(\s*[\'"](\w+)[\'"]/', $code, $walked);
        $chunkedTables = $walked[1];
        $chunkedByVariable = preg_match('/OnlineMigration::forEachIdRange\(\s*\$/', $code) === 1;

        foreach (self::BIG_TABLES as $table) {
            // 'payments' or 'payments as p'
            $quoted = '[\'"]'.preg_quote($table, '/').'(\s+as\s+\w+)?[\'"]';

            foreach (self::segmentsAfter('/Schema::(connection\([^)]*\)\s*->\s*)?table\(\s*'.$quoted.'/', $code) as $segment) {
                if (preg_match('/->\s*(constrained|foreign|foreignIdFor|references)\s*\(/', $segment) === 1
                    || preg_match('/->\s*foreignId\s*\([^;]*->\s*constrained/', $segment) === 1) {
                    $violations[] = "{$table}: foreign key added with the schema builder (table copy) — use OnlineMigration::addNullableForeignKey()";
                    break;
                }
            }

            if (preg_match('/ALTER\s+TABLE\s+`?'.preg_quote($table, '/').'`?\b[^;\'"]*FOREIGN\s+KEY/i', $code) === 1
                && ! str_contains($code, 'OnlineMigration::')) {
                $violations[] = "{$table}: raw ALTER TABLE … FOREIGN KEY outside OnlineMigration — use OnlineMigration::addNullableForeignKey()";
            }

            if (in_array($table, $chunkedTables, true)) {
                continue;
            }

            $builderUpdate = false;
            foreach (self::segmentsAfter('/DB::(connection\([^)]*\)->)?table\(\s*'.$quoted.'\s*\)/', $code, ';') as $segment) {
                if (preg_match('/->\s*update\s*\(/', $segment) === 1 && ! self::builderTargetsKeys($segment)) {
                    $builderUpdate = true;
                    break;
                }
            }

            $rawUpdate = false;
            $rawPattern = '\s*UPDATE\s+`?'.preg_quote($table, '/').'`?\b';
            $statements = [];
            if (preg_match_all('/([\'"])'.$rawPattern.'.*?\1/is', $code, $raw) > 0) {
                $statements = $raw[0];
            }
            // heredoc / nowdoc SQL
            if (preg_match_all('/<<<\s*[\'"]?(\w+)[\'"]?\s*\n'.$rawPattern.'.*?\n\s*\1\b/is', $code, $doc) > 0) {
                $statements = array_merge($statements, $doc[0]);
            }
            if ($statements !== []) {
                foreach ($statements as $statement) {
                    if (! self::rawTargetsKeys($statement)) {
                        $rawUpdate = true;
                        break;
                    }
                }
            }

            if ($builderUpdate || $rawUpdate) {
                $violations[] = "{$table}: UPDATE over the whole table in one statement — chunk it with OnlineMigration::forEachIdRange()";
            }
        }

        // A backfill over a table named by a variable (foreach ([...'invoices'...] as $table)):
        // a builder chain on DB::table($table) or raw SQL interpolating it ("UPDATE {$table} …").
        if (! $chunkedByVariable) {
            $variableUpdate = false;
            foreach (self::segmentsAfter('/DB::(connection\([^)]*\)->)?table\(\s*\$\w+\s*\)/', $code, ';') as $segment) {
                if (preg_match('/->\s*update\s*\(/', $segment) === 1 && ! self::builderTargetsKeys($segment)) {
                    $variableUpdate = true;
                    break;
                }
            }
            if (! $variableUpdate && preg_match_all('/"\s*UPDATE\s+`?\{?\$\w+\}?`?[^"]*"/i', $code, $interpolated) > 0) {
                foreach ($interpolated[0] as $statement) {
                    if (! self::rawTargetsKeys($statement)) {
                        $variableUpdate = true;
                        break;
                    }
                }
            }
            if ($variableUpdate) {
                foreach (self::BIG_TABLES as $table) {
                    if (! in_array($table, $chunkedTables, true) && preg_match('/[\'"]'.preg_quote($table, '/').'[\'"]/', $code) === 1) {
                        $violations[] = "{$table}: UPDATE over the whole table (table name in a variable) — chunk it with OnlineMigration::forEachIdRange()";
                    }
                }
            }
        }

        return array_values(array_unique($violations));
    }

    /**
     * Builder chain restricted to keys: where('id', $x), where('id', '=', $x), whereIn('id', …),
     * whereBetween('id', …), whereKey(…). A range like where('id', '>', 0) is still a table scan.
     */
    private static function builderTargetsKeys(string $chain): bool
    {
        $id = '\s*[\'"](\w+\.)?id[\'"]\s*';

        return preg_match('/->\s*where(In|Between)\s*\('.$id.',|->\s*whereKey\s*\(/i', $chain) === 1
            || preg_match('/->\s*where\s*\('.$id.',\s*([\'"]=[\'"]\s*,)?\s*[^,\'")]+\)/i', $chain) === 1;
    }

    /** Raw UPDATE whose own WHERE (not a sub-select's) is on the key: UPDATE t SET … WHERE id = ? */
    private static function rawTargetsKeys(string $statement): bool
    {
        $withoutSubqueries = preg_replace('/\([^()]*\)/', '()', $statement) ?? $statement;

        return preg_match('/\bwhere\s+(`?\w+`?\.)?`?id`?\s*(=|in\b|between\b)/i', $withoutSubqueries) === 1;
    }

    private static function stripComments(string $php): string
    {
        $out = '';
        foreach (token_get_all($php) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }
                $out .= $token[1];

                continue;
            }
            $out .= $token;
        }

        return $out;
    }

    /**
     * Text following each match of $pattern up to the next Schema::table/create/drop… call (a
     * Schema::hasColumn() guard inside the closure does not end it), or up to $terminator.
     *
     * @return list<string>
     */
    private static function segmentsAfter(string $pattern, string $code, ?string $terminator = null): array
    {
        if (preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        $segments = [];
        foreach ($matches[0] as [$text, $offset]) {
            $start = $offset + strlen($text);
            if ($terminator !== null) {
                $end = strpos($code, $terminator, $start);
            } else {
                $end = preg_match('/Schema::(table|create|drop|dropIfExists|rename)\s*\(/', $code, $next, PREG_OFFSET_CAPTURE, $start) === 1
                    ? $next[0][1]
                    : false;
            }
            $segments[] = substr($code, $start, $end === false ? null : $end - $start);
        }

        return $segments;
    }
}
