<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CentralAuditEvent;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use App\Models\Tenant;
use Illuminate\Database\DatabaseTransactionRecord;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The single write path into the central platform-operator audit log (IDEN-1.5).
 *
 * - Always writes to the CENTRAL `central_audit_logs` table (the model pins the
 *   connection), so it is safe to call while a tenant is initialized.
 * - Redacts secrets recursively before anything is stored: any key containing
 *   `password`, `token`, `secret` (and a few other credential names) has its whole
 *   value replaced by REDACTED, at any depth, case-insensitively.
 * - Captures the client IP / user agent of the current HTTP request when there is one.
 *
 * Transactions (W1 hardening). The row always goes to the CENTRAL connection by name
 * (CentralAuditLog::getConnectionName(), never the default connection, which is the
 * tenant DB while a tenant is initialized), so it is never part of a tenant transaction:
 * a tenant rollback cannot remove it and it takes no lock in the tenant DB. Two methods,
 * two semantics; the caller picks the one that matches what the row asserts:
 *
 *  - record(): "this change HAPPENED". The row must exist exactly when the change does.
 *      * inside an open tenant (non-central) transaction -> deferred until that
 *        transaction commits; if it rolls back, nothing happened and nothing is logged;
 *      * otherwise written immediately on the central connection; if a central
 *        DB::transaction() is open it joins it and rolls back with the change it describes.
 *
 *  - recordAttempt(): "this was TRIED" (failed login, refused / failed operator action).
 *    The row must survive whatever happens to the surrounding transactions.
 *      * no open central transaction -> written immediately and committed on its own; a
 *        tenant rollback cannot touch it (different connection);
 *      * inside an open central transaction -> written once that transaction finishes,
 *        after its COMMIT or after its ROLLBACK, so the caller's rollback can't erase it.
 *
 * Deferred rows are written by a callback on the transaction record; the returned model
 * has `exists === false` until then. A failure while writing a deferred row is reported
 * (logged) instead of thrown: the business transaction has already finished at that
 * point and must not look failed to its caller.
 */
final class CentralAuditLogger
{
    public const REDACTED = '[REDACTED]';

    public const USER_AGENT_MAX = 512;

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
     * Audit a change that happened (see the class doc for the transaction semantics).
     *
     * @param  array<string, mixed>  $properties  free-form context; secrets are redacted before storage
     * @param  CentralUser|null  $actor  the operator who acted; null for anonymous events (system jobs)
     * @param  Model|null  $subject  the record acted on; a Tenant subject also fills `tenant_id`
     * @param  string|null  $tenantId  explicit tenant id (wins over the subject's)
     * @return CentralAuditLog the row; `exists === false` while it waits for a tenant commit
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

        $tenantTransaction = $this->innermostTenantTransaction();
        if ($tenantTransaction !== null) {
            // Discarded by Laravel if the tenant transaction (or a parent) rolls back.
            $tenantTransaction->addCallback(fn () => $this->persistDeferred($log));

            return $log;
        }

        $this->persist($log);

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
     * @return CentralAuditLog the row; `exists === false` while it waits for the open central transaction to finish
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

        $centralTransaction = $this->outermostCentralTransaction();
        if ($centralTransaction !== null) {
            // Exactly one of the two callbacks runs, when the outermost central transaction
            // finishes; a rolled-back inner savepoint does not drop the row either.
            $centralTransaction->addCallback(fn () => $this->persistDeferred($log));
            $centralTransaction->addCallbackForRollback(fn () => $this->persistDeferred($log));

            return $log;
        }

        // No central transaction: autocommits now, independent of any tenant transaction.
        $this->persist($log);

        return $log;
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

        return $log;
    }

    private function persist(CentralAuditLog $log): void
    {
        $log->save();
    }

    private function persistDeferred(CentralAuditLog $log): void
    {
        if ($log->exists) {
            return;
        }

        try {
            $this->persist($log);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The innermost open transaction on any connection other than the central one (the
     * tenant DB), or null. Uses Laravel's transaction manager so the callback binds to
     * that exact transaction: it runs after the tenant's outermost commit and is dropped
     * on rollback.
     */
    private function innermostTenantTransaction(): ?DatabaseTransactionRecord
    {
        $central = $this->centralConnectionName();

        return $this->openTransactions()
            ->filter(static fn (DatabaseTransactionRecord $transaction): bool => $transaction->connection !== $central)
            ->last();
    }

    /** The outermost open transaction on the central connection, or null. */
    private function outermostCentralTransaction(): ?DatabaseTransactionRecord
    {
        $central = $this->centralConnectionName();

        return $this->openTransactions()
            ->filter(static fn (DatabaseTransactionRecord $transaction): bool => $transaction->connection === $central)
            ->first();
    }

    /**
     * Transactions that accept callbacks (in tests, the RefreshDatabase wrapper is excluded
     * by Laravel's testing manager, exactly like DB::afterCommit()).
     *
     * @return Collection<int, DatabaseTransactionRecord>
     */
    private function openTransactions(): Collection
    {
        $manager = app()->bound('db.transactions') ? app('db.transactions') : null;

        if (! $manager instanceof DatabaseTransactionsManager) {
            return new Collection;
        }

        return $manager->callbackApplicableTransactions()->values();
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
