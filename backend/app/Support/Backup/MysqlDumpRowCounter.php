<?php

declare(strict_types=1);

namespace App\Support\Backup;

use RuntimeException;

/**
 * OPS-23: row count per table of a mysqldump / mariadb-dump file, read from the file itself.
 *
 * Both clients write extended inserts, in two layouts:
 *
 *   mysqldump 8.x        INSERT INTO `t` VALUES (1,'a'),(2,'b');            one line
 *   mariadb-dump 10.x+   INSERT INTO `t` VALUES\n(1,'a'),\n(2,'b');         one row per line
 *
 * and both are often installed under the name `mysqldump`. The file is streamed through a small
 * state machine (outside a statement -> INSERT header -> VALUES list), so the row count does not
 * depend on where the line breaks are:
 *  - a statement is recognised only at the start of a line (`CREATE TABLE`, `INSERT [IGNORE] INTO`,
 *    `REPLACE INTO`), so text inside a CREATE TABLE comment or a trigger body never counts;
 *  - the column list of `INSERT INTO t (a, b) VALUES` is skipped (backtick identifiers);
 *  - a row is a top-level `(` of the VALUES list; quoted strings ('...' and "...") are skipped
 *    with MySQL's escaping (backslash escapes, doubled quotes), so parentheses, commas, `;` and
 *    escaped `\n` inside data never count; hex (0x..), _binary '...' and NULL need no case;
 *  - the statement ends at a `;` outside quotes at depth 0, whatever line it is on.
 *
 * The file is read in pieces of at most $chunk bytes (gzip-compressed dumps are read through
 * zlib), and the state survives piece boundaries (an escape or a quote split in two included),
 * so memory stays flat on a large dump.
 *
 * `complete` is true only when the client's trailer `-- Dump completed` was seen (written by both
 * clients unless comments are skipped; backup:tenants pins comments on) and no statement was left
 * open: a truncated dump is never "complete".
 */
final class MysqlDumpRowCounter
{
    public const DEFAULT_CHUNK = 1048576;

    /** Trailer written last by mysqldump and mariadb-dump (comments on). */
    public const TRAILER = '-- Dump completed';

    /** Longest line start inspected to recognise a statement (the rest is streamed). */
    private const LINE_HEAD = 4096;

    /** Longest INSERT header (table + column list) accepted before VALUES. */
    private const MAX_HEADER = 1048576;

    private const OUTSIDE = 0;

    private const HEADER = 1;

    private const VALUES = 2;

    private int $state = self::OUTSIDE;

    private string $table = '';

    private string $header = '';

    private int $depth = 0;

    private ?string $quote = null;

    private bool $escape = false;

    private bool $trailer = false;

    /** @var array<string, int> */
    private array $counts = [];

    /**
     * @return array{tables: array<string, int>, complete: bool}
     */
    public static function scanFile(string $file, int $chunk = self::DEFAULT_CHUNK): array
    {
        $handle = self::open($file);

        try {
            return (new self)->scan($handle, max(2, $chunk));
        } finally {
            fclose($handle);
        }
    }

    /**
     * Top-level rows of a VALUES list (the text after the VALUES keyword, up to its `;`).
     */
    public static function countTuples(string $values): int
    {
        $counter = new self;
        $counter->state = self::VALUES;
        $counter->table = '';
        $counter->counts[''] = 0;
        $counter->feedValues($values, 0);

        return $counter->counts[''];
    }

    /**
     * @param  resource  $handle
     * @return array{tables: array<string, int>, complete: bool}
     */
    private function scan($handle, int $chunk): array
    {
        // fgets($h, $n) returns at most $n - 1 bytes.
        $atLineStart = true;

        while (($piece = fgets($handle, $chunk + 1)) !== false) {
            $lineStart = $atLineStart;

            if ($this->state === self::OUTSIDE) {
                if (! $lineStart) {
                    $atLineStart = str_ends_with($piece, "\n");

                    continue;
                }
                // Gather enough of the line to recognise the statement, however small the pieces.
                while (! str_ends_with($piece, "\n") && strlen($piece) < self::LINE_HEAD
                    && ($more = fgets($handle, $chunk + 1)) !== false) {
                    $piece .= $more;
                }
                $atLineStart = str_ends_with($piece, "\n");
                $this->startLine($piece);

                continue;
            }

            $atLineStart = str_ends_with($piece, "\n");
            if ($this->state === self::HEADER) {
                $this->feedHeader($piece);
            } else {
                $this->feedValues($piece, 0);
            }
        }

        $counts = $this->counts;
        ksort($counts);

        return ['tables' => $counts, 'complete' => $this->trailer && $this->state === self::OUTSIDE];
    }

    private function startLine(string $line): void
    {
        if (str_starts_with($line, self::TRAILER)) {
            $this->trailer = true;

            return;
        }

        if (str_starts_with($line, 'CREATE TABLE ')) {
            if (preg_match('/^CREATE TABLE (?:IF NOT EXISTS )?`((?:[^`]|``)+)`/', $line, $m) === 1) {
                $this->counts[str_replace('``', '`', $m[1])] ??= 0;
            }

            return;
        }

        if (! str_starts_with($line, 'INSERT ') && ! str_starts_with($line, 'REPLACE ')) {
            return;
        }
        if (preg_match('/^(?:INSERT(?: IGNORE)?|REPLACE) INTO `((?:[^`]|``)+)`(?:\.`((?:[^`]|``)+)`)?/', $line, $m) !== 1) {
            return;
        }

        $this->table = str_replace('``', '`', ($m[2] ?? '') !== '' ? $m[2] : $m[1]);
        $this->counts[$this->table] ??= 0;
        $this->state = self::HEADER;
        $this->header = '';
        $this->depth = 0;
        $this->quote = null;
        $this->escape = false;
        $this->feedHeader(substr($line, strlen($m[0])));
    }

    /** Text between the table name and VALUES: an optional backticked column list. */
    private function feedHeader(string $piece): void
    {
        $this->header .= $piece;
        $length = strlen($this->header);
        $i = 0;

        while ($i < $length) {
            $i += strcspn($this->header, '`;Vv', $i);
            if ($i >= $length) {
                break;
            }
            $char = $this->header[$i];

            if ($char === '`') {
                $close = $this->closingBacktick($i + 1);
                if ($close === null) {
                    break; // the identifier continues in the next piece
                }
                $i = $close + 1;

                continue;
            }

            if ($char === ';') {
                // A statement without a VALUES list (INSERT ... SELECT): nothing to count.
                $this->state = self::OUTSIDE;
                $this->header = '';

                return;
            }

            $before = $i === 0 ? ' ' : $this->header[$i - 1];
            $after = $this->header[$i + 6] ?? ' ';
            if (strncasecmp(substr($this->header, $i, 6), 'VALUES', 6) === 0
                && ! self::isWordChar($before) && ! self::isWordChar($after)) {
                $rest = substr($this->header, $i + 6);
                $this->header = '';
                $this->state = self::VALUES;
                $this->feedValues($rest, 0);

                return;
            }
            $i++;
        }

        if (strlen($this->header) > self::MAX_HEADER) {
            throw new RuntimeException((string) __('console.backups.dump_unparsable'));
        }
    }

    private function closingBacktick(int $from): ?int
    {
        while (($close = strpos($this->header, '`', $from)) !== false) {
            if (! isset($this->header[$close + 1])) {
                return null; // last byte so far: a doubled "``" may continue in the next piece
            }
            if ($this->header[$close + 1] !== '`') {
                return $close;
            }
            $from = $close + 2;
        }

        return null;
    }

    private static function isWordChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '`';
    }

    /**
     * VALUES list: counts top-level tuples until the terminating `;`. The hot loop of a backup:
     * state lives in locals and strcspn() jumps over plain data, written back once per piece.
     */
    private function feedValues(string $piece, int $offset): void
    {
        $length = strlen($piece);
        $i = $offset;
        $quote = $this->quote;
        $depth = $this->depth;
        $rows = 0;

        if ($this->escape) {
            // A backslash ended the previous piece: this byte is escaped.
            $this->escape = false;
            $i++;
        }

        while ($i < $length) {
            if ($quote !== null) {
                $i += strcspn($piece, $quote === "'" ? "\\'" : '\\"', $i);
                if ($i >= $length) {
                    break;
                }
                if ($piece[$i] === '\\') {
                    if ($i + 1 >= $length) {
                        $this->escape = true; // the escaped byte is in the next piece
                        $i++;

                        break;
                    }
                    $i += 2;

                    continue;
                }
                // Closing quote. A doubled quote ('') closes and reopens: same result.
                $quote = null;
                $i++;

                continue;
            }

            $i += strcspn($piece, "'\"();", $i);
            if ($i >= $length) {
                break;
            }

            $char = $piece[$i];
            if ($char === "'" || $char === '"') {
                $quote = $char;
            } elseif ($char === '(') {
                if ($depth === 0) {
                    $rows++;
                }
                $depth++;
            } elseif ($char === ')') {
                $depth = $depth > 0 ? $depth - 1 : 0;
            } elseif ($depth === 0) {
                // `;` outside quotes and tuples: end of the statement.
                $this->state = self::OUTSIDE;

                break;
            }
            $i++;
        }

        $this->quote = $quote;
        $this->depth = $depth;
        $this->counts[$this->table] += $rows;
    }

    /**
     * @return resource
     */
    private static function open(string $file)
    {
        $probe = @fopen($file, 'rb');
        if ($probe === false) {
            throw new RuntimeException((string) __('console.backups.dump_empty'));
        }
        $magic = (string) fread($probe, 2);
        fclose($probe);

        $handle = @fopen($magic === "\x1f\x8b" ? 'compress.zlib://'.$file : $file, 'rb');
        if ($handle === false) {
            throw new RuntimeException((string) __('console.backups.dump_empty'));
        }

        return $handle;
    }
}
