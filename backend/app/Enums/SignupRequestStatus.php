<?php

declare(strict_types=1);

namespace App\Enums;

/** PRD-05: lifecycle of a public signup request (central `signup_requests.status`). */
enum SignupRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
