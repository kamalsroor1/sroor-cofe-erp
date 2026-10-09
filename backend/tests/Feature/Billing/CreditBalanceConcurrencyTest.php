<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\TenantCreditLedgerEntry;
use App\Services\Billing\CreditBalanceService;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TenantTestCase;
use Throwable;

/**
 * ENTI-1.10 on a real MySQL server: CreditBalanceService serialises movements of one
 * (tenant, addon_key) on its sentinel row, so parallel debits never take the balance below
 * zero.
 *
 * Group `mysql-only`: phpunit.xml (sqlite) excludes it, the CI `mysql` job runs it through
 * phpunit.mysql.xml (group `mysql`). sqlite has no row locks, so setUp() fails loudly
 * instead of skipping when the central connection is not MySQL/MariaDB.
 */
#[Group('billing')]
#[Group('mysql')]
#[Group('mysql-only')]
final class CreditBalanceConcurrencyTest extends TenantTestCase
{
    private const LOCK_WAIT_TIMEOUT = 1205;

    private const KEY = 'credits.messages';

    private const RACE_WORKERS = 6;

    private const WORKER_READY_TIMEOUT = 60;

    protected function setUp(): void
    {
        parent::setUp();

        $driver = DB::connection($this->centralConnectionName())->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->fail("CreditBalanceConcurrencyTest needs MySQL/MariaDB (central driver: {$driver}). Run it with phpunit.mysql.xml.");
        }
    }

    public function test_a_debit_waits_for_the_sentinel_lock(): void
    {
        $tenantId = 'qacredit'.Str::lower(Str::random(10));
        $holder = $this->centralSideConnection('qa_credit_lock_holder');

        // Committed sentinel, visible to the test's own session.
        $holder->table('tenant_credit_accounts')->insert(['tenant_id' => $tenantId, 'addon_key' => self::KEY, 'created_at' => now()]);

        $connection = DB::connection($this->centralConnectionName());
        $connection->statement('SET SESSION innodb_lock_wait_timeout = 1');
        $holder->beginTransaction();

        $errorCode = null;
        try {
            $holder->selectOne('select id from tenant_credit_accounts where tenant_id = ? and addon_key = ? for update', [$tenantId, self::KEY]);

            try {
                app(CreditBalanceService::class)->credit($tenantId, self::KEY, '1');
            } catch (Throwable $e) {
                for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
                    if ($cause instanceof QueryException) {
                        $errorCode = (int) ($cause->errorInfo[1] ?? 0);
                        break;
                    }
                }

                if ($errorCode === null) {
                    throw $e;
                }
            }
        } finally {
            $holder->rollBack();
            $connection->statement('SET SESSION innodb_lock_wait_timeout = 50');
            $holder->table('tenant_credit_accounts')->where('tenant_id', $tenantId)->delete();
            DB::purge('qa_credit_lock_holder');
        }

        $this->assertSame(self::LOCK_WAIT_TIMEOUT, $errorCode, 'The movement did not wait for the sentinel lock: lockForUpdate() is missing.');
    }

    public function test_parallel_debits_never_overdraw_the_balance(): void
    {
        // Committed fixtures on a separate session: the worker processes cannot see rows
        // inside this test's RefreshDatabase transaction. No tenants row is needed: the
        // ledger has no tenant FK (it outlives the tenant).
        $side = $this->centralSideConnection('qa_credit_race');
        $tenantId = 'qacredit'.Str::lower(Str::random(10));

        try {
            $side->table('tenant_credit_accounts')->insert(['tenant_id' => $tenantId, 'addon_key' => self::KEY, 'created_at' => now()]);
            $side->table('tenant_credit_ledger')->insert([
                'tenant_id' => $tenantId,
                'addon_key' => self::KEY,
                'delta' => '5.000',
                'reason' => TenantCreditLedgerEntry::REASON_ALLOWANCE,
                'created_at' => now(),
            ]);

            // Six workers debit 2.000 each from 5.000 at the same moment: exactly two may win.
            $payloads = array_fill(0, self::RACE_WORKERS, ['tenant_id' => $tenantId, 'addon_key' => self::KEY, 'amount' => '2.000']);
            $results = $this->runWorkers($payloads);

            foreach ($results as $result) {
                $this->assertTrue($result['ok'], 'No debit may crash or deadlock: '.json_encode($result, JSON_UNESCAPED_UNICODE));
            }

            $won = array_values(array_filter($results, fn (array $r): bool => $r['debited'] === true));
            $refused = array_values(array_filter($results, fn (array $r): bool => $r['debited'] === false));

            $this->assertCount(2, $won, 'Exactly two debits fit in the balance: '.json_encode($results, JSON_UNESCAPED_UNICODE));
            $this->assertCount(self::RACE_WORKERS - 2, $refused);
            foreach ($refused as $result) {
                $this->assertSame('insufficient_balance', $result['reason']);
            }

            $deltas = $side->table('tenant_credit_ledger')->where('tenant_id', $tenantId)->orderBy('id')->pluck('delta')->map(fn ($d): string => (string) $d)->all();
            $this->assertSame(['5.000', '-2.000', '-2.000'], $deltas);

            $balance = '0.000';
            foreach ($deltas as $delta) {
                $balance = bcadd($balance, $delta, 3);
            }
            $this->assertSame('1.000', $balance, 'The balance never goes below zero.');
        } finally {
            $side->table('tenant_credit_ledger')->where('tenant_id', $tenantId)->delete();
            $side->table('tenant_credit_accounts')->where('tenant_id', $tenantId)->delete();
            DB::purge('qa_credit_race');
        }
    }

    /**
     * Start one PHP process per payload (tests/Support/credit-debit-worker.php), release
     * them at the same moment and collect their JSON results.
     *
     * @param  list<array{tenant_id: string, addon_key: string, amount: string}>  $payloads
     * @return list<array<string, mixed>>
     */
    private function runWorkers(array $payloads): array
    {
        $script = base_path('tests/Support/credit-debit-worker.php');
        $workers = [];

        try {
            foreach ($payloads as $payload) {
                $stderr = (string) tempnam(sys_get_temp_dir(), 'qa-credit-');
                $pipes = [];
                $process = proc_open(
                    [PHP_BINARY, $script, base64_encode((string) json_encode($payload))],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $stderr, 'w']],
                    $pipes,
                    base_path(),
                );

                if (! is_resource($process)) {
                    throw new RuntimeException('Could not start a credit-debit worker.');
                }

                $workers[] = ['process' => $process, 'pipes' => $pipes, 'stderr' => $stderr];
            }

            foreach ($workers as $worker) {
                stream_set_timeout($worker['pipes'][1], self::WORKER_READY_TIMEOUT);
                $line = trim((string) fgets($worker['pipes'][1]));
                if ($line !== 'READY') {
                    throw new RuntimeException('Worker did not become ready: '.$line.' '.(string) file_get_contents($worker['stderr']));
                }
            }

            foreach ($workers as $worker) {
                fwrite($worker['pipes'][0], "GO\n");
                fflush($worker['pipes'][0]);
            }

            $results = [];
            foreach ($workers as $worker) {
                $output = trim((string) stream_get_contents($worker['pipes'][1]));
                $lines = preg_split('/\r?\n/', $output) ?: [];
                $decoded = json_decode((string) end($lines), true);

                if (! is_array($decoded) || ! array_key_exists('ok', $decoded)) {
                    throw new RuntimeException('Worker returned no result: '.$output.' '.(string) file_get_contents($worker['stderr']));
                }

                $results[] = $decoded;
            }

            return $results;
        } finally {
            foreach ($workers as $worker) {
                foreach ($worker['pipes'] as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($worker['process']);
                @unlink($worker['stderr']);
            }
        }
    }

    private function centralSideConnection(string $name): Connection
    {
        config(["database.connections.{$name}" => (array) config('database.connections.'.$this->centralConnectionName())]);

        return DB::connection($name);
    }
}
