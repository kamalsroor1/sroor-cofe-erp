<?php

declare(strict_types=1);

namespace App\Exceptions\Entitlements;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Creating one more record would exceed the tenant's plan limit (ENTI-2.1).
 *
 * Thrown by the entitlement enforcement (ENTI-2.3+) inside the create transaction.
 * Renders itself as HTTP 403:
 *   {
 *     "success": false,
 *     "message": "<translated subscription.errors.limit_reached>",
 *     "error_code": "subscription.limit_reached",
 *     "details": { "limit": "stores", "max": 3 }
 *   }
 * `limit` is one of the plan limit keys (users, stores, warehouses, vans, items,
 * invoices_month, storage_mb); `max` is the tenant's effective limit (never null here:
 * null means unlimited and can't be exceeded).
 */
final class PlanLimitExceededException extends HttpException
{
    public const ERROR_CODE = 'subscription.limit_reached';

    private function __construct(
        private readonly string $limit,
        private readonly int $max,
        string $message,
    ) {
        parent::__construct(Response::HTTP_FORBIDDEN, $message);
    }

    public static function for(string $limit, int $max): self
    {
        $resourceKey = 'subscription.limit_resources.'.$limit;
        $resource = __($resourceKey);
        $label = is_string($resource) && $resource !== $resourceKey
            ? $resource
            : (string) __('subscription.limit_resources.other');

        return new self($limit, $max, (string) __('subscription.errors.limit_reached', [
            'resource' => $label,
            'max' => (string) $max,
        ]));
    }

    public function limit(): string
    {
        return $this->limit;
    }

    public function max(): int
    {
        return $this->max;
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => self::ERROR_CODE,
            'details' => [
                'limit' => $this->limit,
                'max' => $this->max,
            ],
        ], $this->getStatusCode(), $this->getHeaders());
    }
}
