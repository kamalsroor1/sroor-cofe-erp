<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a signup request (central `signup_requests.status`). Never rename a value: it is stored.
 *
 *  PRD-05 assisted flow: pending -> approved | rejected (operator decision).
 *  SIGN-11 self-signup:  awaiting_verification -> verified (the emailed code matched).
 *  SIGN-12:              verified -> provisioning (tenant registered) -> provisioned | failed;
 *                        verified -> pending (daily cap reached: the manual queue above).
 */
enum SignupRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case AwaitingVerification = 'awaiting_verification';
    case Verified = 'verified';
    case Provisioning = 'provisioning';
    case Provisioned = 'provisioned';
    case Failed = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
