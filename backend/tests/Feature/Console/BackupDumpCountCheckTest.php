<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\BackupTenantsCommand;
use Tests\TestCase;

/**
 * OPS-23: the dump counts are checked against a live bracket taken around the dump
 * (AUTO_INCREMENT + COUNT before, COUNT + AUTO_INCREMENT after). The rows at the dump's snapshot
 * lie in [after - I, before + I], I = inserts made while the dump ran (AUTO_INCREMENT delta).
 */
final class BackupDumpCountCheckTest extends TestCase
{
    public function test_an_all_zero_manifest_of_a_quiet_database_fails_every_table_with_data(): void
    {
        $live = ['invoices' => 47241, 'invoice_items' => 141108, 'settings' => 0];
        $auto = ['invoices' => 47242, 'invoice_items' => 141109, 'settings' => 1];

        $problems = BackupTenantsCommand::dumpCountProblems(
            ['invoices' => 0, 'invoice_items' => 0, 'settings' => 0],
            true,
            $live,
            $live,
            $auto,
            $auto,
        );

        $this->assertCount(2, $problems);
        $this->assertStringStartsWith('invoice_items: ', $problems[1]);
        $this->assertStringStartsWith('invoices: ', $problems[0]);
    }

    public function test_an_untouched_table_must_match_exactly(): void
    {
        $live = ['customers' => 10];
        $auto = ['customers' => 11];

        $this->assertSame([], BackupTenantsCommand::dumpCountProblems(['customers' => 10], true, $live, $live, $auto, $auto));
        $this->assertCount(1, BackupTenantsCommand::dumpCountProblems(['customers' => 9], true, $live, $live, $auto, $auto));
        $this->assertCount(1, BackupTenantsCommand::dumpCountProblems(['customers' => 11], true, $live, $live, $auto, $auto));
    }

    public function test_a_busy_table_may_drift_only_by_the_inserts_made_during_the_dump(): void
    {
        // 5 sales inserted while the dump ran (100 -> 105 rows, counter 101 -> 106).
        $before = ['invoices' => 100];
        $after = ['invoices' => 105];
        $autoBefore = ['invoices' => 101];
        $autoAfter = ['invoices' => 106];

        foreach ([100, 103, 105] as $rows) {
            $this->assertSame([], BackupTenantsCommand::dumpCountProblems(['invoices' => $rows], true, $before, $after, $autoBefore, $autoAfter), "{$rows} rows");
        }
        foreach ([0, 99, 106] as $rows) {
            $this->assertCount(1, BackupTenantsCommand::dumpCountProblems(['invoices' => $rows], true, $before, $after, $autoBefore, $autoAfter), "{$rows} rows");
        }
    }

    public function test_a_queue_style_table_that_got_and_lost_a_row_does_not_false_fail(): void
    {
        // jobs: 0 rows before and after, but one job was pushed (counter +1) and processed.
        $live = ['jobs' => 0];

        foreach ([0, 1] as $rows) {
            $this->assertSame([], BackupTenantsCommand::dumpCountProblems(['jobs' => $rows], true, $live, $live, ['jobs' => 7], ['jobs' => 8]));
        }
    }

    public function test_deletes_during_the_dump_only_widen_the_window(): void
    {
        // tokens: 3 deleted, 1 inserted while the dump ran (10 -> 8 rows).
        $problems = BackupTenantsCommand::dumpCountProblems(['personal_access_tokens' => 7], true, ['personal_access_tokens' => 10], ['personal_access_tokens' => 8], ['personal_access_tokens' => 50], ['personal_access_tokens' => 51]);

        $this->assertSame([], $problems);
    }

    public function test_a_table_without_auto_increment_is_only_checked_for_presence(): void
    {
        $live = ['cache' => 5, 'model_has_roles' => 3];

        $this->assertSame([], BackupTenantsCommand::dumpCountProblems(['cache' => 0, 'model_has_roles' => 3], true, $live, $live, [], []));
        $this->assertCount(1, BackupTenantsCommand::dumpCountProblems(['cache' => 5], true, $live, $live, [], []));
    }

    public function test_an_incomplete_dump_fails_even_when_the_counts_agree(): void
    {
        $problems = BackupTenantsCommand::dumpCountProblems(['users' => 1], false, ['users' => 1], ['users' => 1], ['users' => 2], ['users' => 2]);

        $this->assertCount(1, $problems);
        $this->assertStringStartsWith('*: ', $problems[0]);
    }

    public function test_a_table_missing_from_the_dump_fails_but_one_created_during_the_dump_does_not(): void
    {
        $this->assertCount(1, BackupTenantsCommand::dumpCountProblems([], true, ['users' => 0], ['users' => 0], [], []));
        $this->assertSame([], BackupTenantsCommand::dumpCountProblems([], true, [], ['new_table' => 0], [], []));
    }
}
