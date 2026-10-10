<?php

declare(strict_types=1);

namespace App\Actions\Central\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A refused central-auth operation that is neither a validation error nor a missing token
 * (IDEN-1.12). Rendered by Laravel through render() as the standard envelope:
 *
 *   { "success": false, "message": "<translated>", "error_code": "central_auth.<code>" }
 *
 * error_code is stable for the SPA; message is translated.
 */
final class CentralAuthException extends RuntimeException
{
    public const SETUP_REQUIRED = 'central_auth.two_factor_setup_required';

    public const STEP_UP_REQUIRED = 'central_auth.step_up_required';

    public const ALREADY_CONFIRMED = 'central_auth.two_factor_already_confirmed';

    public const NOT_ENABLED = 'central_auth.two_factor_not_enabled';

    public const PASSWORD_RESET_REQUIRED = 'central_auth.password_reset_required';

    private function __construct(
        string $message,
        private readonly int $status,
        private readonly string $errorCode,
    ) {
        parent::__construct($message);
    }

    /** A 2FA-setup token used on any route other than the setup endpoints. */
    public static function setupRequired(): self
    {
        return new self((string) __('central_auth.two_factor_setup_required'), 403, self::SETUP_REQUIRED);
    }

    /** RequireRecentTwoFactor: no 2FA proof on this token within central.step_up_ttl_minutes. */
    public static function stepUpRequired(): self
    {
        return new self((string) __('central_auth.step_up_required'), 403, self::STEP_UP_REQUIRED);
    }

    /** Correct password, but the account must reset it first (migrated operators, W2-B3). */
    public static function passwordResetRequired(): self
    {
        return new self((string) __('central_auth.password_reset_required'), 403, self::PASSWORD_RESET_REQUIRED);
    }

    public static function alreadyConfirmed(): self
    {
        return new self((string) __('central_auth.two_factor_already_confirmed'), 409, self::ALREADY_CONFIRMED);
    }

    public static function notEnabled(): self
    {
        return new self((string) __('central_auth.two_factor_not_enabled'), 409, self::NOT_ENABLED);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ], $this->status);
    }
}
