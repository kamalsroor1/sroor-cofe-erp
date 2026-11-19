<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * MOB-05: lifecycle of a public account-deletion request (central `account_deletion_requests.status`).
 * pending -> handled (an operator verified the requester and carried the deletion out manually,
 * or closed it with a note). Never rename a value: it is stored.
 */
enum AccountDeletionRequestStatus: string
{
    case Pending = 'pending';
    case Handled = 'handled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
