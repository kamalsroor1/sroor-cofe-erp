<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SAL-333: short-window PIN rate-limit exception thrown by ManagerPinVerifier when the
 * per-approver or per-actor burst cap is exceeded (e.g. 6th attempt within 5 minutes).
 *
 * Extends ThrottleRequestsException (429 + Retry-After) and renders itself so the API
 * returns the specific localized PIN lockout/attempts message and `error_code` instead of
 * the generic `auth.too_many_requests` handler in bootstrap/app.php.
 */
final class ManagerPinThrottledException extends ThrottleRequestsException
{
    public const ERROR_CODE = 'pos.override_throttled';

    public function __construct(
        string $message = '',
        array $headers = [],
        private readonly string $errorCode = self::ERROR_CODE,
    ) {
        parent::__construct($message, null, $headers);
    }

    public static function forWait(
        int $retryAfterSeconds,
        ?string $message = null,
        string $errorCode = self::ERROR_CODE,
    ): self {
        return new self(
            $message ?? __('pos.override_throttled'),
            ['Retry-After' => (string) max(1, $retryAfterSeconds)],
            $errorCode,
        );
    }

    public function render(Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        $retryAfter = $this->getHeaders()['Retry-After'] ?? null;

        return response()->json([
            'success' => false,
            'message' => $this->getMessage() !== '' ? $this->getMessage() : __('pos.override_throttled'),
            'error_code' => $this->errorCode,
            'retry_after' => is_numeric($retryAfter) ? (int) $retryAfter : null,
        ], 429, $this->getHeaders());
    }
}
