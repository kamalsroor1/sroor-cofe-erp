<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * BUG-07: a write lost a lock conflict (deadlock 1213, lock wait timeout 1205 or MariaDB's
 * "record has changed since last read" 1020) and was not, or could no longer be, retried.
 * Everything it did was rolled back, so the client may simply send it again.
 *
 * Raised by App\Support\Database\TransactionRetry after its last attempt, and mapped from a
 * raw QueryException of that kind by the exception handler (bootstrap/app.php), so every
 * endpoint answers 409 with a translated "busy, try again" message instead of a 500.
 *
 * It IS reported (logged / Sentry): a run of these is an operational signal, unlike a refused
 * business rule.
 */
final class DatabaseBusyException extends RuntimeException
{
    public const ERROR_CODE = 'common.database_busy';

    /** Seconds the client should wait before sending the same request again. */
    public const RETRY_AFTER_SECONDS = 1;

    public function __construct(
        public readonly int $attempts = 1,
        ?Throwable $previous = null,
    ) {
        // Developer-facing (logs); the client gets the translated message from render().
        parent::__construct('Database busy: lock conflict after '.$attempts.' attempt(s).', Response::HTTP_CONFLICT, $previous);
    }

    public static function fromThrowable(Throwable $previous, int $attempts = 1): self
    {
        return new self($attempts, $previous);
    }

    public function render(Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => __(self::ERROR_CODE),
            'error_code' => self::ERROR_CODE,
        ], Response::HTTP_CONFLICT, ['Retry-After' => (string) self::RETRY_AFTER_SECONDS]);
    }
}
