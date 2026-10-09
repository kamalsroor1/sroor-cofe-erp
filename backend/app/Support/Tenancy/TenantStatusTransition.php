<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Models\CentralUser;
use App\Models\TenantLifecycleEvent;
use InvalidArgumentException;

/**
 * Input of App\Actions\Tenants\TransitionTenantStatusAction (IDEN-3.3).
 *
 *  - to():        move a tenant to an explicit status (state machine in TenantStatus);
 *  - unarchive(): restore an archived tenant to `status_before_archive` (CTO W1 Q2). The
 *                 target is read under the row lock, never trusted from the caller.
 *
 * $expectFrom: the statuses the caller believes the tenant is in (what the operator saw on
 * screen, or what the sweep evaluated). Empty = no expectation. A mismatch is a 409, so a
 * stale decision is never applied on top of a concurrent change.
 */
final readonly class TenantStatusTransition
{
    /**
     * @param  list<TenantStatus>  $expectFrom
     * @param  array<string, scalar|null>  $context  extra audit context (e.g. billing_payment_id); no secrets
     */
    private function __construct(
        public string $tenantId,
        public ?TenantStatus $to,
        public TenantLifecycleActor $actor,
        public array $expectFrom,
        public ?TenantSuspensionReason $reason,
        public ?string $note,
        public ?CentralUser $causer,
        public array $context,
    ) {
        if ($actor === TenantLifecycleActor::SuperAdmin && $causer === null) {
            throw new InvalidArgumentException('A SuperAdmin tenant transition needs the acting CentralUser.');
        }

        if ($note !== null && mb_strlen($note) > TenantLifecycleEvent::NOTE_MAX) {
            throw new InvalidArgumentException('A tenant transition note is limited to 500 characters.');
        }
    }

    /**
     * @param  list<TenantStatus>|TenantStatus  $expectFrom
     * @param  array<string, scalar|null>  $context
     */
    public static function to(
        string $tenantId,
        TenantStatus $to,
        TenantLifecycleActor $actor,
        array|TenantStatus $expectFrom = [],
        ?TenantSuspensionReason $reason = null,
        ?string $note = null,
        ?CentralUser $causer = null,
        array $context = [],
    ): self {
        return new self(
            $tenantId,
            $to,
            $actor,
            $expectFrom instanceof TenantStatus ? [$expectFrom] : $expectFrom,
            $reason,
            self::normalizeNote($note),
            $causer,
            $context,
        );
    }

    /**
     * @param  array<string, scalar|null>  $context
     */
    public static function unarchive(string $tenantId, CentralUser $causer, ?string $note = null, array $context = []): self
    {
        return new self(
            $tenantId,
            null,
            TenantLifecycleActor::SuperAdmin,
            [], // the action itself requires `archived` under the lock (409 not_archived)
            null,
            self::normalizeNote($note),
            $causer,
            $context,
        );
    }

    public function isUnarchive(): bool
    {
        return $this->to === null;
    }

    private static function normalizeNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $note = trim($note);

        return $note === '' ? null : $note;
    }
}
