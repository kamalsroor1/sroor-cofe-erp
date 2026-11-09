<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SEC-04: a non-admin actor tried to grant privileges they do not hold themselves.
 *
 * Renders itself as 403 { "success": false, "message": "<translated>" } for API/JSON requests;
 * other AuthorizationExceptions (policies, can: middleware) keep Laravel's default rendering.
 */
final class PermissionEscalationException extends AuthorizationException
{
    public function render(Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => $this->getMessage() !== '' ? $this->getMessage() : __('auth.unauthorized'),
        ], 403);
    }
}
