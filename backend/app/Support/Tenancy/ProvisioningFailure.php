<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use PDOException;
use RuntimeException;
use Throwable;

/**
 * Internal failure of one provisioning step (OPS-2), carrying the stored error code.
 *
 * Deliberately NOT an HttpException: it must be reported by the queue worker. Clients only
 * ever see the translated ProvisioningErrorCode label.
 *
 * Secrets (W2 batch 4 review): the message is FIXED per error code plus the cause's class
 * and SQLSTATE / driver code. The cause's own message is never copied: a QueryException
 * message embeds the SQL, e.g. `CREATE USER … IDENTIFIED BY '<password>'`. For the steps
 * that handle credentials or the first admin (user creation, seed) the raw cause is not
 * even chained as `previous`, so it cannot reach the log / failed_jobs through the chain.
 * The other steps (database DDL, migrations) keep the cause chained for diagnosis: their
 * SQL carries identifiers only, never a credential.
 */
final class ProvisioningFailure extends RuntimeException
{
    public function __construct(
        public readonly ProvisioningErrorCode $errorCode,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : self::fixedMessage($errorCode), 0, $previous);
    }

    /**
     * Wrap the cause of a failed step. $chain = false for the steps whose cause may carry a
     * secret in its message or SQL (database user creation, seed).
     */
    public static function because(ProvisioningErrorCode $code, Throwable $previous, bool $chain = true): self
    {
        if ($previous instanceof self) {
            return $previous;
        }

        return new self($code, self::fixedMessage($code).' '.self::describe($previous), $chain ? $previous : null);
    }

    public static function fixedMessage(ProvisioningErrorCode $code): string
    {
        return 'Tenant provisioning failed: '.$code->value;
    }

    /** "(<class>[, SQLSTATE <state>][, driver code <code>])", never the cause's message. */
    private static function describe(Throwable $cause): string
    {
        $parts = [$cause::class];
        $pdo = self::pdoException($cause);

        if ($pdo !== null) {
            $info = is_array($pdo->errorInfo) ? $pdo->errorInfo : [];
            $state = $info[0] ?? $pdo->getCode();
            if (is_string($state) || is_int($state)) {
                $parts[] = 'SQLSTATE '.(string) $state;
            }

            $driverCode = $info[1] ?? null;
            if (is_int($driverCode) || is_string($driverCode)) {
                $parts[] = 'driver code '.(string) $driverCode;
            }
        }

        return '('.implode(', ', $parts).')';
    }

    private static function pdoException(Throwable $cause): ?PDOException
    {
        for ($current = $cause; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof PDOException) {
                return $current;
            }
        }

        return null;
    }
}
