<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CentralAuditEvent;
use App\Jobs\PersistCentralAuditLogJob;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Support\TenantCache;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\DatabaseTransactionRecord;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The single write path into the central platform-operator audit log (IDEN-1.5, IDEN-1.15).
 *
 * - Always writes to the CENTRAL `central_audit_logs` table (the model pins the
 *   connection), so it is safe to call while a tenant is initialized.
 * - Redacts secrets recursively before anything is stored: any key containing
 *   `password`, `token`, `secret` (and a few other credential names) has its whole
 *   value replaced by REDACTED, at any depth, case-insensitively.
 * - Captures the client IP / user agent of the current HTTP request when there is one.
 * - Stamps `created_at` when the event is recorded, so a deferred or retried write keeps
 *   the time the event happened.
 *
 * Transactions. The row always goes to the CENTRAL connection by name, so it is never
 * part of a tenant transaction: a tenant rollback cannot remove it and it takes no lock in
 * the tenant DB. Three methods, three semantics; the caller picks the one that matches
 * what the row asserts:
 *
 *  - record(): "this change HAPPENED". The row must exist exactly when the change does.
 *      * no open transaction -> written at once;
 *      * only a central transaction open -> written at once INSIDE it, so it rolls back
 *        with the change it describes;
 *      * only a tenant transaction open -> deferred until that transaction commits; if it
 *        rolls back nothing happened and nothing is logged;
 *      * tenant AND central transactions open (either nesting order) -> the row exists iff
 *        BOTH commit: it is written after the later of the two commits, and dropped as soon
 *        as either of them (or a savepoint enclosing the call) rolls back.
 *
 *  - recordAttempt(): "this was TRIED" (failed login, refused / failed operator action).
 *    The row must survive whatever happens to the surrounding transactions.
 *      * no open central transaction -> written at once and committed on its own (a tenant
 *        rollback cannot touch it: different connection);
 *      * only a central transaction open -> written once the OUTERMOST central transaction
 *        finishes, after its COMMIT or after its ROLLBACK;
 *      * tenant AND central transactions open -> written once the outermost transaction of
 *        EACH connection has finished (commit or rollback), whichever finishes last.
 *
 *  - recordStrict(): a synchronous central write that THROWS when it fails. For callers that
 *    must not proceed without the audit row (it joins an open central transaction).
 *
 * Deferred rows are written by callbacks on Laravel's transaction records; the returned
 * model has `exists === false` until then. A deferred write runs after the business
 * transaction has finished, so its failure must never surface to that caller:
 *   1. up to 3 immediate attempts;
 *   2. then a queued PersistCentralAuditLogJob (5 tries, backoff 10 s … 15 min);
 *   3. final failure (or a queue that refuses the job) -> writeFailed(): critical log,
 *      report(), the AuditWriteFailed counter (writeFailureCount()) and a best-effort
 *      `audit_write_failed` audit row.
 * Immediate (non-deferred) writes of record() / recordAttempt() still throw to the caller.
 */
final class CentralAuditLogger
{
    public const REDACTED = '[REDACTED]';

    public const USER_AGENT_MAX = 512;

    /** Immediate attempts of a deferred write before it is handed to the queue. */
    public const DEFERRED_ATTEMPTS = 3;

    /** Central-scope cache key of the AuditWriteFailed counter. */
    public const WRITE_FAILURES_KEY = 'central_audit:write_failures';

    public const LAST_WRITE_FAILURE_KEY = 'central_audit:last_write_failure_at';

    /**
     * A key is sensitive when its lower-cased name CONTAINS one of these fragments.
     */
    private const SENSITIVE_FRAGMENTS = [
        'password',
        'passwd',
        'token',
        'secret',
        'authorization',
        'api_key',
        'apikey',
        'private_key',
        'recovery_code',
        'cookie',
    ];

    /**
     * A key is sensitive when its lower-cased name EQUALS one of these (too short to
     * match as fragments without hiding harmless keys).
     */
    private const SENSITIVE_EXACT = [
        'pin',
        'pin_code',
        'otp',
        'code_hash',
    ];

    /**
     * Write ids already handed to writeFailed() in this process, so a lost row is counted
     * once even when the sync queue driver calls the job's failed() AND rethrows.
     *
     * @var array<string, true>
     */
    private static array $reportedFailures = [];

    /**
     * Audit a change that happened (see the class doc for the transaction semantics).
     *
     * @param  array<string, mixed>  $properties  free-form context; secrets are redacted before storage
     * @param  CentralUser|null  $actor  the operator who acted; null for anonymous events (system jobs)
     * @param  Model|null  $subject  the record acted on; a Tenant subject also fills `tenant_id`
     * @param  string|null  $tenantId  explicit tenant id (wins over the subject's)
     * @return CentralAuditLog the row; `exists === false` while it waits for a commit
     */
    public function record(
        CentralAuditEvent $event,
        array $properties = [],
        ?CentralUser $actor = null,
        ?Model $subject = null,
        ?string $tenantId = null,
        ?string $description = null,
    ): CentralAuditLog {
        $log = $this->build($event, $properties, $actor, $subject, $tenantId, $description);

        $tenantTransaction = $this->innermostTransaction(central: false);
        if ($tenantTransaction === null) {
            // No tenant transaction: write now (inside the central transaction, if one is open).
            $this->persist($log);

            return $log;
        }

        $centralTransaction = $this->innermostTransaction(central: true);
        if ($centralTransaction === null) {
            // Discarded by Laravel if the tenant transaction (or a parent) rolls back.
            $tenantTransaction->addCallback(fn () => $this->persistDeferred($log));

            return $log;
        }

        $this->writeWhenAllCommit($log, [$tenantTransaction, $centralTransaction]);

        return $log;
    }

    /**
     * Audit an attempt whose record must survive a rollback: failed logins, refused or
     * failed operator actions (see the class doc for the transaction semantics).
     *
     * @param  array<string, mixed>  $properties  free-form context; secrets are redacted before storage
     * @param  CentralUser|null  $actor  the operator who tried; null for anonymous attempts (failed login)
     * @param  Model|null  $subject  the record the attempt targeted; a Tenant subject also fills `tenant_id`
     * @param  string|null  $tenantId  explicit tenant id (wins over the subject's)
     * @return CentralAuditLog the row; `exists === false` while it waits for the open transactions to finish
     */
    public function recordAttempt(
        CentralAuditEvent $event,
        array $properties = [],
        ?CentralUser $actor = null,
        ?Model $subject = null,
        ?string $tenantId = null,
        ?string $description = null,
    ): CentralAuditLog {
        $log = $this->build($event, $properties, $actor, $subject, $tenantId, $description);

        $centralTransaction = $this->outermostTransaction(central: true);
        if ($centralTransaction === null) {
            // No central transaction: autocommits now, independent of any tenant transaction.
            $this->persist($log);

            return $log;
        }

        $tenantTransaction = $this->outermostTransaction(central: false);

        $this->writeWhenAllFinish($log, $tenantTransaction === null
            ? [$centralTransaction]
            : [$centralTransaction, $tenantTransaction]);

        return $log;
    }

    /**
     * Audit synchronously on the central connection and throw if the row cannot be written.
     * Joins an open central transaction (and rolls back with it); ignores tenant ones.
     *
     * @param  array<string, mixed>  $properties  free-form context; secrets are redacted before storage
     *
     * @throws Throwable when the row could not be written
     */
    public function recordStrict(
        CentralAuditEvent $event,
        array $properties = [],
        ?CentralUser $actor = null,
        ?Model $subject = null,
        ?string $tenantId = null,
        ?string $description = null,
    ): CentralAuditLog {
        $log = $this->build($event, $properties, $actor, $subject, $tenantId, $description);

        $this->persist($log);

        return $log;
    }

    /**
     * Write a row handed over by PersistCentralAuditLogJob (raw, already redacted attributes).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function persistQueued(array $attributes): void
    {
        $log = new CentralAuditLog;
        $log->setRawAttributes($attributes);

        $this->persist($log);
    }

    /**
     * A deferred audit row is lost for good: every immediate attempt and every queued try
     * failed (or the queue refused the job). Logs it as critical, reports the exception,
     * bumps the AuditWriteFailed counter and tries to leave an `audit_write_failed` row.
     * Never throws.
     *
     * @param  array<string, mixed>  $attributes  the lost row's raw attributes
     */
    public function writeFailed(array $attributes, ?Throwable $exception, string $writeId): void
    {
        if (isset(self::$reportedFailures[$writeId])) {
            return;
        }
        self::$reportedFailures[$writeId] = true;

        $lostEvent = is_string($attributes['event'] ?? null) ? $attributes['event'] : null;
        $lostTenant = is_string($attributes['tenant_id'] ?? null) ? $attributes['tenant_id'] : null;
        $lostAt = isset($attributes['created_at']) && is_scalar($attributes['created_at']) ? (string) $attributes['created_at'] : null;

        Log::critical('Central audit row lost: every write attempt failed.', [
            'write_id' => $writeId,
            'event' => $lostEvent,
            'tenant_id' => $lostTenant,
            'created_at' => $lostAt,
            'exception' => $exception === null ? null : $exception::class.': '.$exception->getMessage(),
        ]);

        if ($exception !== null) {
            report($exception);
        }

        $this->countWriteFailure();

        if ($lostEvent === CentralAuditEvent::AuditWriteFailed->value) {
            return;
        }

        try {
            $this->persist($this->build(CentralAuditEvent::AuditWriteFailed, [
                'lost_event' => $lostEvent,
                'lost_at' => $lostAt,
                'write_id' => $writeId,
                'error' => $exception === null ? null : $exception::class,
            ], null, null, $lostTenant, null));
        } catch (Throwable) {
            // The counter and the critical log above are the record of last resort.
        }
    }

    /** How many deferred audit rows were lost for good (AuditWriteFailed counter). */
    public function writeFailureCount(): int
    {
        $value = Cache::get(TenantCache::centralKey(self::WRITE_FAILURES_KEY), 0);

        return is_numeric($value) ? (int) $value : 0;
    }

    /** ISO-8601 time of the last lost audit row, or null. */
    public function lastWriteFailureAt(): ?string
    {
        $value = Cache::get(TenantCache::centralKey(self::LAST_WRITE_FAILURE_KEY));

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function build(
        CentralAuditEvent $event,
        array $properties,
        ?CentralUser $actor,
        ?Model $subject,
        ?string $tenantId,
        ?string $description,
    ): CentralAuditLog {
        $request = $this->currentRequest();

        $log = new CentralAuditLog;
        $log->fill([
            'event' => $event->value,
            'description' => $description !== null ? mb_substr($description, 0, 255) : null,
            'causer_type' => $actor?->getMorphClass(),
            'causer_id' => $actor !== null ? (int) $actor->getKey() : null,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject !== null ? (string) $subject->getKey() : null,
            'tenant_id' => $tenantId ?? ($subject instanceof Tenant ? (string) $subject->getKey() : null),
            'properties' => $properties === [] ? null : $this->redact($properties),
            'ip_address' => $request?->ip(),
            'user_agent' => $this->userAgent($request),
        ]);
        $log->setCreatedAt($log->freshTimestamp());

        return $log;
    }

    private function persist(CentralAuditLog $log): void
    {
        if (! $log->save()) {
            throw new RuntimeException('The central audit row was not saved (a model listener cancelled it).');
        }
    }

    /**
     * Write after the transactions finished: 3 immediate attempts, then the queue; never throws.
     */
    private function persistDeferred(CentralAuditLog $log): void
    {
        if ($log->exists) {
            return;
        }

        $error = null;
        for ($attempt = 1; $attempt <= self::DEFERRED_ATTEMPTS; $attempt++) {
            try {
                $this->persist($log);

                return;
            } catch (Throwable $e) {
                $error = $e;
            }
        }

        $writeId = (string) Str::uuid();
        $attributes = $log->getAttributes();

        Log::warning('Central audit row could not be written after its transaction; queued for retry.', [
            'write_id' => $writeId,
            'event' => $attributes['event'] ?? null,
            'tenant_id' => $attributes['tenant_id'] ?? null,
            'exception' => $error::class.': '.$error->getMessage(),
        ]);

        try {
            // Dispatched right here (not through a PendingDispatch destructor), so a queue
            // that refuses the job throws inside this try.
            app(Dispatcher::class)->dispatch(new PersistCentralAuditLogJob($attributes, $writeId));
        } catch (Throwable $e) {
            // The queue refused the job (or, on the sync driver, the job already failed and
            // its failed() hook ran: writeFailed() ignores the duplicate write id).
            $this->writeFailed($attributes, $e, $writeId);
        }
    }

    /**
     * record() with a tenant AND a central transaction open: the row is written after the
     * last of them commits, and never when any of them rolls back. Callbacks sit on the
     * INNERMOST transaction of each connection, so a rolled-back savepoint that enclosed the
     * call drops the row too.
     *
     * @param  list<DatabaseTransactionRecord>  $transactions
     */
    private function writeWhenAllCommit(CentralAuditLog $log, array $transactions): void
    {
        $waiting = count($transactions);
        $cancelled = false;

        foreach ($transactions as $transaction) {
            $transaction->addCallback(function () use (&$waiting, &$cancelled, $log): void {
                if ($cancelled) {
                    return;
                }

                if (--$waiting === 0) {
                    $this->persistDeferred($log);
                }
            });

            $transaction->addCallbackForRollback(function () use (&$cancelled): void {
                $cancelled = true;
            });
        }
    }

    /**
     * recordAttempt() inside central (and maybe tenant) transactions: the row is written
     * once the OUTERMOST transaction of every given connection has finished, committed or
     * rolled back. Exactly one of the two callbacks of each record runs.
     *
     * @param  list<DatabaseTransactionRecord>  $transactions
     */
    private function writeWhenAllFinish(CentralAuditLog $log, array $transactions): void
    {
        $waiting = count($transactions);

        $finished = function () use (&$waiting, $log): void {
            if (--$waiting === 0) {
                $this->persistDeferred($log);
            }
        };

        foreach ($transactions as $transaction) {
            $transaction->addCallback($finished);
            $transaction->addCallbackForRollback($finished);
        }
    }

    private function countWriteFailure(): void
    {
        try {
            $key = TenantCache::centralKey(self::WRITE_FAILURES_KEY);

            Cache::add($key, 0, now()->addYears(5));
            Cache::increment($key);
            Cache::forever(TenantCache::centralKey(self::LAST_WRITE_FAILURE_KEY), now()->toIso8601String());
        } catch (Throwable $e) {
            Log::critical('AuditWriteFailed counter could not be updated.', ['exception' => $e::class.': '.$e->getMessage()]);
        }
    }

    /**
     * The innermost open transaction on the central connection (central: true) or on any
     * other connection, i.e. the tenant DB (central: false); null when there is none.
     */
    private function innermostTransaction(bool $central): ?DatabaseTransactionRecord
    {
        return $this->openTransactions($central)->last();
    }

    /** The outermost open transaction on the central connection / the tenant side, or null. */
    private function outermostTransaction(bool $central): ?DatabaseTransactionRecord
    {
        return $this->openTransactions($central)->first();
    }

    /**
     * Open transactions that accept callbacks, outermost first (in tests, the RefreshDatabase
     * wrapper is excluded by Laravel's testing manager, exactly like DB::afterCommit()).
     *
     * @return Collection<int, DatabaseTransactionRecord>
     */
    private function openTransactions(bool $central): Collection
    {
        $manager = app()->bound('db.transactions') ? app('db.transactions') : null;

        if (! $manager instanceof DatabaseTransactionsManager) {
            return new Collection;
        }

        $centralName = $this->centralConnectionName();

        return $manager->callbackApplicableTransactions()
            ->filter(static fn (DatabaseTransactionRecord $transaction): bool => ($transaction->connection === $centralName) === $central)
            ->values();
    }

    private function centralConnectionName(): string
    {
        // Single source of truth: throws when the central connection is not configured.
        return (string) (new CentralAuditLog)->getConnectionName();
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function redact(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $clean[$key] = self::REDACTED;

                continue;
            }

            $clean[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $clean;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        if (in_array($normalized, self::SENSITIVE_EXACT, true)) {
            return true;
        }

        foreach (self::SENSITIVE_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function currentRequest(): ?Request
    {
        return app()->bound('request') ? request() : null;
    }

    private function userAgent(?Request $request): ?string
    {
        $agent = $request?->userAgent();

        if ($agent === null || $agent === '') {
            return null;
        }

        return mb_substr($agent, 0, self::USER_AGENT_MAX);
    }
}
