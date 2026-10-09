<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\CentralAuditEvent;
use App\Jobs\PersistCentralAuditLogJob;
use App\Models\CentralAuditLog;
use App\Services\CentralAuditLogger;
use Closure;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TenantTestCase;
use Throwable;

/**
 * W2 hardening of CentralAuditLogger:
 *  (a) a deferred write that fails is retried 3 times at once, then queued
 *      (PersistCentralAuditLogJob); the final failure is logged as critical, reported and
 *      counted (AuditWriteFailed) — never thrown at the business caller;
 *  (b) mixed nesting of tenant and central transactions, in both orders and every outcome:
 *      record() exists iff BOTH commit, recordAttempt() always survives and is written only
 *      after the outermost transaction of both connections has finished;
 *  (c) recordStrict(): synchronous central write that throws on failure.
 */
#[Group('mysql')]
final class CentralAuditLoggerHardeningTest extends TenantTestCase
{
    private const INNER = 'inner step failed';

    private const OUTER = 'outer step failed';

    // ---------------------------------------------------------------------------------
    // (b) mixed nesting
    // ---------------------------------------------------------------------------------

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function nestingOrders(): iterable
    {
        foreach (['central_outer', 'tenant_outer'] as $order) {
            foreach (['both_commit', 'inner_rolls_back', 'outer_rolls_back', 'both_roll_back'] as $outcome) {
                yield "{$order}, {$outcome}" => [$order, $outcome];
            }
        }
    }

    #[DataProvider('nestingOrders')]
    public function test_record_with_both_transactions_open_exists_iff_both_commit(string $order, string $outcome): void
    {
        $description = "record-{$order}-{$outcome}";

        $seen = $this->nested($order, $outcome, fn (): CentralAuditLog => $this->logger()->record(CentralAuditEvent::TenantUpdated, description: $description), $description);

        $this->assertFalse($seen['exists_inside'], 'Nothing is written while both transactions are open.');
        $this->assertSame(0, $seen['rows_after_inner'], 'Still nothing after the inner transaction: the outer one has not committed yet.');
        $this->assertSame($outcome === 'both_commit' ? 1 : 0, $this->rows($description));
    }

    #[DataProvider('nestingOrders')]
    public function test_record_attempt_with_both_transactions_open_survives_and_waits_for_both(string $order, string $outcome): void
    {
        $description = "attempt-{$order}-{$outcome}";

        $seen = $this->nested($order, $outcome, fn (): CentralAuditLog => $this->logger()->recordAttempt(CentralAuditEvent::LoginFailed, ['password' => 'guess'], description: $description), $description);

        $this->assertFalse($seen['exists_inside']);
        $this->assertSame(0, $seen['rows_after_inner'], 'Written only after the outermost transaction of both connections finished.');
        $this->assertSame(1, $this->rows($description), 'An attempt survives every rollback, exactly once.');
        $this->assertSame(CentralAuditLogger::REDACTED, CentralAuditLog::query()->where('description', $description)->firstOrFail()->properties['password'] ?? null);
    }

    public function test_record_in_a_rolled_back_central_savepoint_inside_a_committed_tenant_transaction_is_dropped(): void
    {
        $tenant = $this->createTenant();
        $central = DB::connection($this->centralConnectionName());

        $this->inTenant($tenant, function () use ($central): void {
            DB::transaction(function () use ($central): void {
                $central->transaction(function () use ($central): void {
                    try {
                        $central->transaction(function (): void {
                            $this->logger()->record(CentralAuditEvent::PlanUpdated, description: 'savepoint-record');

                            throw new RuntimeException(self::INNER);
                        });
                    } catch (RuntimeException) {
                    }
                });
            });
        });

        $this->assertSame(0, $this->rows('savepoint-record'), 'The change it describes was rolled back with its savepoint.');
    }

    // ---------------------------------------------------------------------------------
    // (a) retries, queue, final failure
    // ---------------------------------------------------------------------------------

    public function test_a_deferred_write_is_retried_immediately_up_to_three_times(): void
    {
        Queue::fake();
        $this->failNextWrites(2);

        $this->recordInTenantTransaction('retry-ok');

        $this->assertSame(1, $this->rows('retry-ok'), 'The third immediate attempt wrote the row.');
        Queue::assertNothingPushed();
    }

    public function test_after_three_failed_attempts_the_row_is_queued_and_the_caller_never_sees_an_error(): void
    {
        Queue::fake();
        $this->failNextWrites(3);
        Carbon::setTestNow('2026-10-09 10:00:00');

        $committed = $this->recordInTenantTransaction('queued', ['token' => 'secret-value', 'note' => 'kept']);

        $this->assertTrue($committed, 'The business transaction committed normally.');
        $this->assertSame(0, $this->rows('queued'));

        $pushed = null;
        Queue::assertPushed(PersistCentralAuditLogJob::class, function (PersistCentralAuditLogJob $job) use (&$pushed): bool {
            $pushed = $job;

            return true;
        });
        $this->assertInstanceOf(PersistCentralAuditLogJob::class, $pushed);
        $this->assertSame(5, $pushed->tries);
        $this->assertSame([10, 60, 300, 900], $pushed->backoff());
        $this->assertStringNotContainsString('secret-value', (string) json_encode($pushed->attributes), 'The queued payload is already redacted.');

        // The worker runs later: the row keeps the time the event happened.
        Carbon::setTestNow('2026-10-09 10:05:00');
        $pushed->handle($this->logger());

        $row = CentralAuditLog::query()->where('description', 'queued')->firstOrFail();
        $this->assertSame('2026-10-09 10:00:00', $row->created_at?->format('Y-m-d H:i:s'));
        // assertEquals: MySQL JSON columns do not keep object key order.
        $this->assertEquals(['token' => CentralAuditLogger::REDACTED, 'note' => 'kept'], $row->properties);
        Carbon::setTestNow();
    }

    public function test_the_final_failure_is_logged_reported_and_counted(): void
    {
        Queue::fake();
        Exceptions::fake();
        $critical = [];
        Event::listen(MessageLogged::class, function (MessageLogged $logged) use (&$critical): void {
            if ($logged->level === 'critical') {
                $critical[] = $logged;
            }
        });
        $this->failNextWrites(3);

        $this->recordInTenantTransaction('lost');
        $job = null;
        Queue::assertPushed(PersistCentralAuditLogJob::class, function (PersistCentralAuditLogJob $pushed) use (&$job): bool {
            $job = $pushed;

            return true;
        });
        $this->assertInstanceOf(PersistCentralAuditLogJob::class, $job);

        $before = $this->logger()->writeFailureCount();
        $error = new RuntimeException('central database unreachable');
        $job->failed($error);
        $job->failed($error); // a duplicate hand-off of the same write is counted once

        $this->assertSame($before + 1, $this->logger()->writeFailureCount());
        $this->assertNotNull($this->logger()->lastWriteFailureAt());
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e === $error);
        $lost = array_values(array_filter($critical, fn (MessageLogged $logged): bool => str_contains($logged->message, 'Central audit row lost')));
        $this->assertCount(1, $lost, 'One critical log line per lost row.');
        $this->assertSame(CentralAuditEvent::TenantUpdated->value, $lost[0]->context['event'] ?? null);
        $this->assertSame($job->writeId, $lost[0]->context['write_id'] ?? null);

        $trace = CentralAuditLog::query()->where('event', CentralAuditEvent::AuditWriteFailed->value)->firstOrFail();
        $this->assertSame(CentralAuditEvent::TenantUpdated->value, $trace->properties['lost_event'] ?? null);
        $this->assertSame($job->writeId, $trace->properties['write_id'] ?? null);
        $this->assertSame(0, $this->rows('lost'));
    }

    public function test_on_the_sync_queue_a_permanently_failing_write_is_counted_once_and_never_thrown(): void
    {
        config(['queue.default' => 'sync']);
        $this->failNextWrites(PHP_INT_MAX);
        $before = $this->logger()->writeFailureCount();

        $committed = $this->recordInTenantTransaction('sync-lost');

        $this->assertTrue($committed);
        $this->assertSame(0, $this->rows('sync-lost'));
        $this->assertSame($before + 1, $this->logger()->writeFailureCount());
    }

    public function test_a_queue_that_refuses_the_job_still_counts_the_lost_row(): void
    {
        config(['queue.default' => 'qa-missing-connection']);
        $this->failNextWrites(PHP_INT_MAX);
        $before = $this->logger()->writeFailureCount();

        $committed = $this->recordInTenantTransaction('queue-down');

        $this->assertTrue($committed);
        $this->assertSame($before + 1, $this->logger()->writeFailureCount());
    }

    public function test_an_immediate_record_still_throws_to_its_caller(): void
    {
        $this->failNextWrites(1);

        $this->expectException(RuntimeException::class);

        $this->logger()->record(CentralAuditEvent::PlanUpdated, description: 'immediate');
    }

    // ---------------------------------------------------------------------------------
    // (c) recordStrict()
    // ---------------------------------------------------------------------------------

    public function test_record_strict_writes_synchronously_even_inside_a_tenant_transaction(): void
    {
        $tenant = $this->createTenant();

        $exists = $this->inTenant($tenant, fn (): bool => DB::transaction(
            fn (): bool => $this->logger()->recordStrict(CentralAuditEvent::TenantUpdated, ['password' => 'x'], description: 'strict')->exists,
        ));

        $this->assertTrue($exists);
        $this->assertSame(1, $this->rows('strict'));
        $this->assertSame(CentralAuditLogger::REDACTED, CentralAuditLog::query()->where('description', 'strict')->firstOrFail()->properties['password'] ?? null);
    }

    public function test_record_strict_throws_when_the_row_cannot_be_written(): void
    {
        Queue::fake();
        $this->failNextWrites(1);

        try {
            $this->logger()->recordStrict(CentralAuditEvent::PlanUpdated, description: 'strict-fail');
            $this->fail('recordStrict() must throw when the write fails.');
        } catch (RuntimeException $e) {
            $this->assertSame('central database unreachable', $e->getMessage());
        }

        $this->assertSame(0, $this->rows('strict-fail'));
        Queue::assertNothingPushed();
    }

    public function test_record_strict_rolls_back_with_an_open_central_transaction(): void
    {
        $central = DB::connection($this->centralConnectionName());

        try {
            $central->transaction(function (): void {
                $this->logger()->recordStrict(CentralAuditEvent::PlanUpdated, description: 'strict-rollback');

                throw new RuntimeException(self::OUTER);
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, $this->rows('strict-rollback'));
    }

    // ---------------------------------------------------------------------------------
    // helpers
    // ---------------------------------------------------------------------------------

    /**
     * Run $log inside an inner transaction nested in an outer one (central/tenant in the
     * given order) and finish them as $outcome says.
     *
     * @param  Closure(): CentralAuditLog  $log
     * @return array{exists_inside: bool, rows_after_inner: int}
     */
    private function nested(string $order, string $outcome, Closure $log, string $description): array
    {
        $tenant = $this->createTenant();
        $centralName = $this->centralConnectionName();

        return $this->inTenant($tenant, function () use ($order, $outcome, $log, $description, $centralName): array {
            $central = DB::connection($centralName);
            $tenantConnection = DB::connection();
            $this->assertNotSame($centralName, $tenantConnection->getName(), 'The tenant connection must differ from the central one.');

            [$outer, $inner] = $order === 'central_outer' ? [$central, $tenantConnection] : [$tenantConnection, $central];
            $seen = ['exists_inside' => true, 'rows_after_inner' => -1];

            try {
                $outer->transaction(function () use ($inner, $outcome, $log, $description, &$seen): void {
                    try {
                        $inner->transaction(function () use ($outcome, $log, &$seen): void {
                            $seen['exists_inside'] = $log()->exists;

                            if ($outcome === 'inner_rolls_back' || $outcome === 'both_roll_back') {
                                throw new RuntimeException(self::INNER);
                            }
                        });
                    } catch (RuntimeException $e) {
                        $seen['rows_after_inner'] = $this->rows($description);

                        if ($outcome === 'both_roll_back') {
                            throw $e;
                        }
                    }

                    $seen['rows_after_inner'] = $this->rows($description);

                    if ($outcome === 'outer_rolls_back') {
                        throw new RuntimeException(self::OUTER);
                    }
                });
            } catch (RuntimeException $e) {
                $this->assertContains($e->getMessage(), [self::INNER, self::OUTER]);
            }

            return $seen;
        });
    }

    /**
     * record() inside a committed tenant transaction (the deferred path).
     *
     * @param  array<string, mixed>  $properties
     */
    private function recordInTenantTransaction(string $description, array $properties = []): bool
    {
        $tenant = $this->createTenant();

        return $this->inTenant($tenant, fn (): bool => DB::transaction(function () use ($description, $properties): bool {
            $this->logger()->record(CentralAuditEvent::TenantUpdated, $properties, description: $description);

            return true;
        }));
    }

    /** Make the next $count central audit inserts fail like an unreachable database. */
    private function failNextWrites(int $count): void
    {
        $remaining = $count;

        CentralAuditLog::creating(static function () use (&$remaining): void {
            if ($remaining > 0) {
                $remaining--;

                throw new RuntimeException('central database unreachable');
            }
        });
    }

    private function rows(string $description): int
    {
        return DB::connection($this->centralConnectionName())->table('central_audit_logs')->where('description', $description)->count();
    }

    private function logger(): CentralAuditLogger
    {
        return $this->app->make(CentralAuditLogger::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        try {
            CentralAuditLog::flushEventListeners();
            CentralAuditLog::clearBootedModels();
        } catch (Throwable) {
        }

        parent::tearDown();
    }
}
