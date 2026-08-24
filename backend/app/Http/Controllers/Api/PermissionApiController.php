<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Permissions\GetPermissionsTreeAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PermissionApiController extends Controller
{
    public function __construct(
        private readonly GetPermissionsTreeAction $getPermissionsTreeAction
    ) {}

    /**
     * Get system permissions catalog & current authenticated user permissions
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => __('auth.unauthorized'),
            ], 401);
        }

        $data = $this->getPermissionsTreeAction->execute($user);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ], 200);
    }
}
