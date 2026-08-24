<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Roles\GetRolesMatrixAction;
use App\Actions\Roles\UpdateRolePermissionsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRolePermissionsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RoleController extends Controller
{
    public function __construct(
        private readonly GetRolesMatrixAction $getRolesMatrixAction,
        private readonly UpdateRolePermissionsAction $updateRolePermissionsAction
    ) {}

    /**
     * Get roles list and permissions matrix
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('roles.manage')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $roleId = $request->input('role_id') ? (int)$request->input('role_id') : null;
        $matrix = $this->getRolesMatrixAction->execute($roleId);

        return response()->json([
            'success' => true,
            'data'    => $matrix,
        ], 200);
    }

    /**
     * Update permissions assigned to a role
     */
    public function updatePermissions(UpdateRolePermissionsRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();
        $permissions = $validated['permissions'] ?? [];

        $role = $this->updateRolePermissionsAction->execute($id, $permissions);

        return response()->json([
            'success' => true,
            'message' => __('auth.role_permissions_updated') ?: 'تم تحديث مصفوفة صلاحيات الدور بنجاح',
            'data'    => [
                'id'          => $role->id,
                'name'        => $role->name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ],
        ], 200);
    }
}
