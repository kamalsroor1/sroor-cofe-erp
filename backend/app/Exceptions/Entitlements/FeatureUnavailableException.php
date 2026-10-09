<?php

declare(strict_types=1);

namespace App\Exceptions\Entitlements;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The tenant's plan / add-ons / overrides do not include a feature (ENTI-2.1).
 *
 * Thrown by the backend feature gate (ENTI-2.6 `feature:<key>` middleware, default-deny).
 * Renders itself as HTTP 403:
 *   {
 *     "success": false,
 *     "message": "<translated subscription.errors.feature_unavailable>",
 *     "error_code": "subscription.feature_unavailable",
 *     "details": { "feature": "mixes.manage" }
 *   }
 */
final class FeatureUnavailableException extends HttpException
{
    public const ERROR_CODE = 'subscription.feature_unavailable';

    private function __construct(private readonly string $feature, string $message)
    {
        parent::__construct(Response::HTTP_FORBIDDEN, $message);
    }

    public static function for(string $feature): self
    {
        return new self($feature, (string) __('subscription.errors.feature_unavailable'));
    }

    public function feature(): string
    {
        return $this->feature;
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => self::ERROR_CODE,
            'details' => [
                'feature' => $this->feature,
            ],
        ], $this->getStatusCode(), $this->getHeaders());
    }
}
