<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CentralAuditEvent;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

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
 * Transactions: the row is written on the caller's central connection, so if the
 * caller's DB::transaction() rolls back, the audit row rolls back with the change it
 * describes. Side effects that must survive (alerts, IDEN-1.13) hook in after commit.
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
     * @param  array<string, mixed>  $properties  free-form context; secrets are redacted before storage
     * @param  CentralUser|null  $actor  the operator who acted; null for anonymous events (failed login, system jobs)
     * @param  Model|null  $subject  the record acted on; a Tenant subject also fills `tenant_id`
     * @param  string|null  $tenantId  explicit tenant id (wins over the subject's)
     */
    public function record(
        CentralAuditEvent $event,
        array $properties = [],
        ?CentralUser $actor = null,
        ?Model $subject = null,
        ?string $tenantId = null,
        ?string $description = null,
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
        $log->save();

        return $log;
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
