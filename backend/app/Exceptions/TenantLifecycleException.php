<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A tenant lifecycle rule refused a request (IDEN-3.3 / IDEN-3.5).
 *
 * Renders itself (no bootstrap/app.php callback needed) as:
 *   { "success": false, "message": "<translated>", "error_code": "subscription.<code>", "details": {…} }
 *
 * | factory                       | HTTP | error_code                               |
 * |-------------------------------|------|------------------------------------------|
 * | readOnly()                    | 423  | subscription.read_only                   |
 * | blocked($status)              | 403  | subscription.access_blocked              |
 * | activationRequiresPayment()   | 403  | subscription.activation_requires_payment |
 * | invalidTransition()           | 409  | subscription.invalid_transition          |
 * | statusConflict()              | 409  | subscription.status_conflict             |
 * | archiveNotAllowed()           | 409  | subscription.archive_not_allowed         |
 * | notArchived()                 | 409  | subscription.not_archived                |
 * | reasonRequired()              | 422  | subscription.suspension_reason_required  |
 *
 * It extends HttpException so it is never reported as a server error, and so the status
 * is kept even if something renders it without calling render().
 */
final class TenantLifecycleException extends HttpException
{
    /**
     * @param  array<string, mixed>  $details  machine-readable context (no secrets, no other tenants' data)
     */
    private function __construct(
        int $status,
        private readonly string $errorCode,
        string $message,
        private readonly array $details = [],
    ) {
        parent::__construct($status, $message);
    }

    /** Read-only tenant tried to write (HTTP 423). */
    public static function readOnly(): self
    {
        return new self(Response::HTTP_LOCKED, 'subscription.read_only', (string) __('subscription.errors.read_only'), [
            'status' => TenantStatus::ReadOnly->value,
        ]);
    }

    /** Suspended / cancelled / archived tenant (HTTP 403). */
    public static function blocked(TenantStatus $status): self
    {
        return new self(Response::HTTP_FORBIDDEN, 'subscription.access_blocked', (string) __('subscription.errors.access_blocked', [
            'status' => $status->label(),
        ]), [
            'status' => $status->value,
        ]);
    }

    /** Only a verified payment (actor Billing) may make a tenant active (HTTP 403). */
    public static function activationRequiresPayment(TenantLifecycleActor $actor): self
    {
        return new self(Response::HTTP_FORBIDDEN, 'subscription.activation_requires_payment', (string) __('subscription.errors.activation_requires_payment'), [
            'actor' => $actor->value,
        ]);
    }

    /** The state machine has no such move for this actor (HTTP 409). */
    public static function invalidTransition(string $from, TenantStatus $to, TenantLifecycleActor $actor): self
    {
        $fromStatus = TenantStatus::tryFrom($from);

        return new self(Response::HTTP_CONFLICT, 'subscription.invalid_transition', (string) __('subscription.errors.invalid_transition', [
            'from' => $fromStatus?->label() ?? $from,
            'to' => $to->label(),
        ]), [
            'from' => $from,
            'to' => $to->value,
            'actor' => $actor->value,
        ]);
    }

    /**
     * The tenant is no longer in the status the caller expected (stale screen / concurrent
     * change, HTTP 409).
     *
     * @param  list<TenantStatus>  $expected
     */
    public static function statusConflict(array $expected, string $actual): self
    {
        $actualStatus = TenantStatus::tryFrom($actual);

        return new self(Response::HTTP_CONFLICT, 'subscription.status_conflict', (string) __('subscription.errors.status_conflict', [
            'status' => $actualStatus?->label() ?? $actual,
        ]), [
            'expected' => array_map(static fn (TenantStatus $status): string => $status->value, $expected),
            'current' => $actual,
        ]);
    }

    /** Archive only from suspended or cancelled (CTO W1 Q2, HTTP 409). */
    public static function archiveNotAllowed(string $from): self
    {
        $fromStatus = TenantStatus::tryFrom($from);

        return new self(Response::HTTP_CONFLICT, 'subscription.archive_not_allowed', (string) __('subscription.errors.archive_not_allowed', [
            'status' => $fromStatus?->label() ?? $from,
        ]), [
            'from' => $from,
        ]);
    }

    /** Unarchive of a tenant that is not archived (HTTP 409). */
    public static function notArchived(string $current): self
    {
        return new self(Response::HTTP_CONFLICT, 'subscription.not_archived', (string) __('subscription.errors.not_archived'), [
            'current' => $current,
        ]);
    }

    /** A super-admin suspension needs a reason code (HTTP 422). */
    public static function reasonRequired(): self
    {
        return new self(Response::HTTP_UNPROCESSABLE_ENTITY, 'subscription.suspension_reason_required', (string) __('subscription.errors.suspension_reason_required'));
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }

    public function render(Request $request): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ];

        if ($this->details !== []) {
            $payload['details'] = $this->details;
        }

        return new JsonResponse($payload, $this->getStatusCode(), $this->getHeaders());
    }
}
