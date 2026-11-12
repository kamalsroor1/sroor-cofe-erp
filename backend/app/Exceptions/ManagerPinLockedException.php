<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SEC-21: the approver reached ManagerPinVerifier::MAX_DAILY_FAILURES_PER_APPROVER wrong PINs
 * today; their PIN approvals are refused (even with the right PIN) until the next day on the
 * tenant clock or until the PIN is changed.
 *
 * Still a ThrottleRequestsException (429 + Retry-After) for every caller that catches it as one.
 * It renders itself (Handler::render runs an exception's own render() before the registered
 * callbacks) so the client gets the specific message and a stable `error_code` instead of the
 * generic too-many-requests text. The message never says whether the approver id exists: unknown
 * ids are counted and locked the same way.
 */
final class ManagerPinLockedException extends ThrottleRequestsException
{
    public const ERROR_CODE = 'pos.override_locked_today';

    public static function untilTomorrow(int $retryAfterSeconds): self
    {
        return new self(__('pos.override_locked_today'), null, ['Retry-After' => (string) $retryAfterSeconds]);
    }

    public function render(Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        $retryAfter = $this->getHeaders()['Retry-After'] ?? null;

        return response()->json([
            'success' => false,
            'message' => __('pos.override_locked_today'),
            'error_code' => self::ERROR_CODE,
            'retry_after' => is_numeric($retryAfter) ? (int) $retryAfter : null,
        ], 429, $this->getHeaders());
    }
}
