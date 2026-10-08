<?php

declare(strict_types=1);

namespace App\Enums;

use Symfony\Component\HttpFoundation\Response;

/**
 * What a tenant may do in its current (derived) lifecycle status (IDEN-3.1).
 *
 * CTO decision Q-L2 (2026-10-08): a read-only tenant gets HTTP 423 Locked on writes;
 * a blocked tenant (suspended / cancelled / archived) gets HTTP 403 on everything.
 * The write allowlist (logout, store switch, payment receipt upload) is applied by
 * the enforcing middleware (IDEN-3.5), not here.
 */
enum TenantAccessLevel: string
{
    case Full = 'full';
    case ReadOnly = 'read_only';
    case Blocked = 'blocked';

    public function allowsReads(): bool
    {
        return $this !== self::Blocked;
    }

    public function allowsWrites(): bool
    {
        return $this === self::Full;
    }

    /**
     * HTTP status a request must be refused with, or null when it is allowed.
     */
    public function deniedStatus(bool $isWrite): ?int
    {
        return match ($this) {
            self::Full => null,
            self::ReadOnly => $isWrite ? Response::HTTP_LOCKED : null,
            self::Blocked => Response::HTTP_FORBIDDEN,
        };
    }

    public function translationKey(): string
    {
        return 'subscription.access_levels.'.$this->value;
    }

    public function label(?string $locale = null): string
    {
        return (string) __($this->translationKey(), [], $locale);
    }
}
