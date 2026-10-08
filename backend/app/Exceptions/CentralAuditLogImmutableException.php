<?php

declare(strict_types=1);

namespace App\Exceptions;

use LogicException;

/**
 * Thrown when code tries to update or delete a central audit row (IDEN-1.5).
 * This is a programming error, never a user flow: the audit log is append-only.
 */
final class CentralAuditLogImmutableException extends LogicException
{
    public function __construct()
    {
        parent::__construct((string) __('central_audit.immutable'));
    }
}
