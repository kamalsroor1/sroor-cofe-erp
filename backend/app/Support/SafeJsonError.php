<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * SEC-1 (L4): the body of a controller `catch`. Exceptions that already carry a safe, translated
 * answer (validation 422, authorization 403, any HTTP exception or prepared response) are
 * rethrown for the framework to render. A missing record is a translated 404 in the API
 * envelope (never the model class name). Anything else is reported to the log and answered
 * with a translated generic message: raw exception text (SQL, paths, class names) never
 * reaches the client.
 */
final class SafeJsonError
{
    public static function respond(Throwable $e, string $messageKey = 'common.operation_failed', int $status = 422): JsonResponse
    {
        if ($e instanceof ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => __('common.record_not_found'),
            ], 404);
        }

        if ($e instanceof ValidationException
            || $e instanceof AuthorizationException
            || $e instanceof AuthenticationException
            || $e instanceof HttpResponseException
            || $e instanceof HttpExceptionInterface) {
            throw $e;
        }

        report($e);

        return response()->json([
            'success' => false,
            'message' => __($messageKey),
        ], $status);
    }
}
