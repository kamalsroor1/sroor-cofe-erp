<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Backup;

use App\Support\Backup\MysqlDumpRowCounter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * OPS-23: the manifest of a MySQL backup was all zeros on servers whose `mysqldump` is the MariaDB
 * client, because the old counter only read rows on the `INSERT INTO ... VALUES` line itself while
 * mariadb-dump writes one row per following line. Both layouts must give the same counts.
 *
 * Fixtures `tests/Fixtures/backup/*.sql.txt` are REAL dumps of one MySQL 8.4 schema, taken with
 * `--single-transaction --skip-lock-tables --quick --no-tablespaces --hex-blob --extended-insert
 * --net-buffer-length=4096 --skip-dump-date` by:
 *  - mysqldump-8.4.sql.txt        mysqldump  Distrib 8.4.13 (Oracle client);
 *  - mariadb-dump-11.8.sql.txt    mariadb-dump 11.8.8 (the MariaDB client, the one that broke).
 * Schema: empty_table (0 rows), one_row (1), tricky (7: parentheses, commas, `;`, escaped quotes,
 * `\n`, backslashes, Arabic, double quotes, binary/hex, NULLs), `odd``name` (2, no AUTO_INCREMENT),
 * big (38 rows split over 2 extended INSERT statements). Kept as .sql.txt because *.sql is ignored.
 */
final class MysqlDumpRowCounterTest extends TestCase
{
    private const EXPECTED = ['big' => 38, 'empty_table' => 0, 'odd`name' => 2, 'one_row' => 1, 'tricky' => 7];

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /** @return array<string, array{0: string, 1: int}> */
    public static function realDumps(): array
    {
        $cases = [];
        foreach (['mysqldump-8.4.sql.txt', 'mariadb-dump-11.8.sql.txt'] as $fixture) {
            // Tiny pieces force every boundary case: an escape, a quote, `VALUES`, a backticked
            // name or the trailer split between two reads.
            foreach ([2, 3, 5, 7, 13, 64, 4096, MysqlDumpRowCounter::DEFAULT_CHUNK] as $chunk) {
                $cases["{$fixture} / {$chunk}-byte pieces"] = [$fixture, $chunk];
            }
        }

        return $cases;
    }

    #[DataProvider('realDumps')]
    public function test_real_dumps_of_both_clients_give_the_same_correct_counts(string $fixture, int $chunk): void
    {
        $result = MysqlDumpRowCounter::scanFile(self::fixture($fixture), $chunk);

        $this->assertSame(self::EXPECTED, $result['tables']);
        $this->assertTrue($result['complete']);
    }

    public function test_the_mariadb_fixture_really_has_rows_on_the_following_lines(): void
    {
        $dump = (string) file_get_contents(self::fixture('mariadb-dump-11.8.sql.txt'));

        $this->assertStringContainsString("INSERT INTO `tricky` VALUES\n(1,", $dump);
        $this->assertSame(2, substr_count($dump, 'INSERT INTO `big` VALUES'));
        $this->assertStringContainsString('INSERT INTO `tricky` VALUES (1,', (string) file_get_contents(self::fixture('mysqldump-8.4.sql.txt')));
    }

    public function test_a_gzip_compressed_dump_is_streamed_through_zlib(): void
    {
        foreach (['mysqldump-8.4.sql.txt', 'mariadb-dump-11.8.sql.txt'] as $fixture) {
            $gz = $this->temp((string) gzencode((string) file_get_contents(self::fixture($fixture)), 6));

            $result = MysqlDumpRowCounter::scanFile($gz, 64);

            $this->assertSame(self::EXPECTED, $result['tables'], $fixture);
            $this->assertTrue($result['complete'], $fixture);
        }
    }

    /** @return array<string, array{0: string}> */
    public static function layouts(): array
    {
        $rows = [
            "(1,'x),(y',0x28290A3B27,'a;b')",
            "(2,'it\\'s (fine); really',_binary '\\0\\'',NULL)",
            "(3,'line1\\nline2\\n(3,\\'z\\')',NULL,'c:\\\\dir\\\\')",
            "(4,'قهوة (محمصة)، بن','\\\"q\\\"','it''s')",
        ];

        $mysql = 'INSERT INTO `tricky` VALUES '.implode(',', array_slice($rows, 0, 2)).";\n"
            .'INSERT INTO `tricky` VALUES '.implode(',', array_slice($rows, 2)).";\n";
        $maria = "INSERT INTO `tricky` VALUES\n".implode(",\n", array_slice($rows, 0, 2)).";\n"
            ."INSERT INTO `tricky` VALUES\n".implode(",\n", array_slice($rows, 2)).";\n";
        $oneRowPerStatement = '';
        foreach ($rows as $row) {
            $oneRowPerStatement .= 'INSERT INTO `tricky` VALUES '.$row.";\n";
        }

        $wrap = static fn (string $inserts): string => implode("\n", [
            '-- MySQL dump 10.13',
            'CREATE TABLE `empty_table` (',
            "  `note` varchar(9) DEFAULT NULL COMMENT 'INSERT INTO `fake` VALUES (1)',",
            '  `id` int NOT NULL AUTO_INCREMENT',
            ') ENGINE=InnoDB;',
            'CREATE TABLE `tricky` (`id` int) ENGINE=InnoDB;',
            'CREATE TABLE `one_row` (`id` int) ENGINE=InnoDB;',
            'LOCK TABLES `tricky` WRITE;',
            rtrim($inserts, "\n"),
            'UNLOCK TABLES;',
            "INSERT INTO `one_row` (`id`, `values`) VALUES\n(1);",
            'DELIMITER ;;',
            '/*!50003 CREATE*/ /*!50003 TRIGGER `t` AFTER INSERT ON `one_row` FOR EACH ROW INSERT INTO `tricky` VALUES (9) */;;',
            'DELIMITER ;',
            '-- Dump completed on 2026-11-12 10:00:00',
            '',
        ]);

        return [
            'mysqldump: rows on the INSERT line' => [$wrap($mysql)],
            'mariadb-dump: one row per following line' => [$wrap($maria)],
            'one row per statement (--skip-extended-insert)' => [$wrap($oneRowPerStatement)],
        ];
    }

    #[DataProvider('layouts')]
    public function test_every_layout_counts_rows_with_hostile_values(string $dump): void
    {
        $file = $this->temp($dump);

        foreach ([3, 11, MysqlDumpRowCounter::DEFAULT_CHUNK] as $chunk) {
            $this->assertSame(
                ['result' => ['tables' => ['empty_table' => 0, 'one_row' => 1, 'tricky' => 4], 'complete' => true]],
                ['result' => MysqlDumpRowCounter::scanFile($file, $chunk)],
                "chunk {$chunk}",
            );
        }
    }

    public function test_a_value_longer_than_a_read_piece_is_followed_to_its_end(): void
    {
        $long = str_repeat("a),(b\\'c;", 2000);
        $file = $this->temp("CREATE TABLE `t` (`v` text);\nINSERT INTO `t` VALUES\n(1,'{$long}'),\n(2,'x');\n-- Dump completed\n");

        $this->assertSame(['tables' => ['t' => 2], 'complete' => true], MysqlDumpRowCounter::scanFile($file, 64));
    }

    public function test_schema_qualified_insert_ignore_and_replace_are_counted(): void
    {
        $file = $this->temp(implode("\n", [
            'INSERT IGNORE INTO `db`.`a` VALUES (1),(2);',
            'REPLACE INTO `b` VALUES',
            '(1);',
            '-- Dump completed',
            '',
        ]));

        $this->assertSame(['tables' => ['a' => 2, 'b' => 1], 'complete' => true], MysqlDumpRowCounter::scanFile($file));
    }

    public function test_a_dump_cut_inside_an_insert_is_incomplete(): void
    {
        $dump = (string) file_get_contents(self::fixture('mariadb-dump-11.8.sql.txt'));
        $cut = substr($dump, 0, (int) strpos($dump, "\n(20,'row 20") + 1);
        // Rows of `big` before the cut (its ids have gaps): one "(" line each.
        $rowsBeforeCut = substr_count(substr($cut, (int) strpos($cut, 'INSERT INTO `big`')), "\n(");

        $result = MysqlDumpRowCounter::scanFile($this->temp($cut));

        $this->assertFalse($result['complete']);
        $this->assertGreaterThan(0, $rowsBeforeCut);
        $this->assertLessThan(self::EXPECTED['big'], $rowsBeforeCut);
        $this->assertSame($rowsBeforeCut, $result['tables']['big']);
    }

    public function test_a_dump_without_its_trailer_is_incomplete(): void
    {
        $dump = (string) file_get_contents(self::fixture('mysqldump-8.4.sql.txt'));
        $cut = substr($dump, 0, (int) strpos($dump, MysqlDumpRowCounter::TRAILER));

        $result = MysqlDumpRowCounter::scanFile($this->temp($cut));

        $this->assertFalse($result['complete']);
        $this->assertSame(self::EXPECTED, $result['tables']);
    }

    public function test_an_empty_file_is_incomplete_and_has_no_tables(): void
    {
        $this->assertSame(['tables' => [], 'complete' => false], MysqlDumpRowCounter::scanFile($this->temp('')));
    }

    public function test_count_tuples_reads_a_values_list(): void
    {
        $this->assertSame(3, MysqlDumpRowCounter::countTuples("\n(1,'a'),\n(2,'(b)'),\n(3,NULL);\n"));
        $this->assertSame(0, MysqlDumpRowCounter::countTuples(';'));
    }

    private static function fixture(string $name): string
    {
        return dirname(__DIR__, 3).'/Fixtures/backup/'.$name;
    }

    private function temp(string $contents): string
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'sroor-dump-');
        file_put_contents($file, $contents);
        $this->files[] = $file;

        return $file;
    }
}
