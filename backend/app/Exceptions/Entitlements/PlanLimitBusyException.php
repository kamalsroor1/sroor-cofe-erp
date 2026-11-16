<?php

declare(strict_types=1);

namespace App\Exceptions\Entitlements;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * PlanEnforcer could not take its per-tenant lock in time (another limit check or upload of the
 * same tenant held it): HTTP 409 with the translated "operation failed, try again" message.
 * Always a 4xx, so callers that turn unexpected failures into a 500 can let it through.
 */
final class PlanLimitBusyException extends HttpException
{
    public function __construct()
    {
        parent::__construct(Response::HTTP_CONFLICT, (string) __('common.operation_failed'));
    }
}
