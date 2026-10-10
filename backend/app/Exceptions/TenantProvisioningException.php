<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\TenantProvisioningStatus;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A request refused because of the tenant's provisioning state (OPS-2).
 *
 * Renders itself as:
 *   { "success": false, "message": "<translated>", "error_code": "provisioning.<code>", "provisioning_status": "<status>" }
 *
 * | factory               | HTTP | error_code                          |
 * |-----------------------|------|-------------------------------------|
 * | workspaceNotReady()   | 503  | provisioning.workspace_not_ready    |
 * | requiresReadyWorkspace() | 409 | provisioning.workspace_not_ready  |
 * | retryNotAllowed()     | 409  | provisioning.retry_not_allowed      |
 * | retryUnavailable()    | 409  | provisioning.retry_unavailable      |
 *
 * workspaceNotReady() adds `Retry-After` while the workspace is still being prepared.
 */
final class TenantProvisioningException extends HttpException
{
    /** Seconds a client should wait before asking again for a pending/running workspace. */
    public const RETRY_AFTER_SECONDS = 30;

    /**
     * @param  array<string, string>  $headers
     */
    private function __construct(
        int $status,
        private readonly string $errorCode,
        string $message,
        private readonly TenantProvisioningStatus $provisioningStatus,
        array $headers = [],
    ) {
        parent::__construct($status, $message, null, $headers);
    }

    /** The tenant exists but its workspace is not ready (pending, running or failed). */
    public static function workspaceNotReady(TenantProvisioningStatus $status): self
    {
        return new self(
            Response::HTTP_SERVICE_UNAVAILABLE,
            'provisioning.workspace_not_ready',
            (string) __('provisioning.workspace_not_ready'),
            $status,
            $status->isInProgress() ? ['Retry-After' => (string) self::RETRY_AFTER_SECONDS] : [],
        );
    }

    /**
     * A super-admin operation that needs the tenant DATABASE (run migrations, impersonate,
     * write tenant settings) or that would change it under a running provisioning (DB
     * config) on a tenant that is not `ready`. 409: a business conflict, not an outage.
     */
    public static function requiresReadyWorkspace(TenantProvisioningStatus $status): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            'provisioning.workspace_not_ready',
            (string) __('provisioning.requires_ready_workspace', ['status' => $status->label()]),
            $status,
        );
    }

    /** Retry is only allowed for a failed (or stale) provisioning. */
    public static function retryNotAllowed(TenantProvisioningStatus $status): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            'provisioning.retry_not_allowed',
            (string) __('provisioning.retry_not_allowed', ['status' => $status->label()]),
            $status,
        );
    }

    /** The stored first-admin data needed to re-run the job is gone. */
    public static function retryUnavailable(TenantProvisioningStatus $status): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            'provisioning.retry_unavailable',
            (string) __('provisioning.retry_unavailable'),
            $status,
        );
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function provisioningStatus(): TenantProvisioningStatus
    {
        return $this->provisioningStatus;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'provisioning_status' => $this->provisioningStatus->value,
        ], $this->getStatusCode(), $this->getHeaders());
    }
}
