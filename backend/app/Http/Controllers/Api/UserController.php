<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Users\CreateUserAction;
use App\Actions\Users\DeleteUserAction;
use App\Actions\Users\ToggleUserActiveAction;
use App\Actions\Users\UpdateUserAction;
use App\DTOs\Users\CreateUserDTO;
use App\DTOs\Users\UpdateUserDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Throwable;

final class UserController extends Controller
{
    public function __construct(
        private readonly CreateUserAction $createUserAction,
        private readonly UpdateUserAction $updateUserAction,
        private readonly DeleteUserAction $deleteUserAction,
        private readonly ToggleUserActiveAction $toggleUserActiveAction
    ) {}

    /**
     * List users with search, role and pagination
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->user() && !$request->user()->can('users.manage')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $search = trim((string)$request->input('search', ''));
        $role = (string)$request->input('role', 'all');
        $perPage = (int)$request->input('per_page', 15);

        $query = User::with(['roles', 'defaultStore']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role !== 'all' && $role !== '') {
            $query->role($role);
        }

        $isTenant = function_exists('tenant') && tenant();
        $roles = Role::when($isTenant, fn($q) => $q->where('name', '!=', 'super_admin'))
            ->select('id', 'name')
            ->get();
        $stores = Store::where('is_active', true)->select('id', 'name', 'code')->get();

        $formattedUsers = collect($users->items())->map(function ($u) {
            return [
                'id'                 => $u->id,
                'name'               => $u->name,
                'phone'              => $u->phone,
                'email'              => $u->email,
                'is_active'          => (bool)$u->is_active,
                'default_store_id'   => $u->default_store_id,
                'default_store_name' => $u->defaultStore?->name ?? 'غير محدد',
                'roles'              => $u->roles->pluck('name')->toArray(),
                'primary_role'       => $u->roles->first()?->name ?: 'cashier',
                'created_at'         => $u->created_at ? $u->created_at->toDateString() : '',
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $formattedUsers,
            'roles'   => $roles->map(fn($r) => [
                'id'   => $r->name,
                'name' => match ($r->name) {
                    'admin'       => 'مدير النظام (كامل الصلاحيات) 👑',
                    'cashier'     => 'كاشير مبيعات ونقطة بيع 🛒',
                    'storekeeper' => 'أمين مخزن وتوريدات 📦',
                    'accountant'  => 'محاسب ومدقق مالي 💼',
                    default       => $r->name,
                },
            ]),
            'stores'  => $stores,
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
            ],
        ]);
    }

    /**
     * Get specific user details
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with(['roles', 'defaultStore'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => (new UserResource($user))->resolve(),
        ]);
    }

    /**
     * Store new user via CreateUserAction
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $dto = CreateUserDTO::fromArray($request->validated());
        $user = $this->createUserAction->execute($dto);

        return response()->json([
            'success' => true,
            'message' => __('auth.user_created_success') ?: 'تم إنشاء حساب المستخدم بنجاح',
            'data'    => (new UserResource($user->load(['roles', 'defaultStore'])))->resolve(),
        ], 201);
    }

    /**
     * Update user details via UpdateUserAction
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $dto = UpdateUserDTO::fromArray($id, $request->validated());
        $user = $this->updateUserAction->execute($dto);

        return response()->json([
            'success' => true,
            'message' => __('auth.user_updated_success') ?: 'تم تحديث بيانات المستخدم بنجاح',
            'data'    => (new UserResource($user->load(['roles', 'defaultStore'])))->resolve(),
        ]);
    }

    /**
     * Delete user safely
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->deleteUserAction->execute($id, $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => __('auth.user_deleted_success') ?: 'تم حذف حساب المستخدم بنجاح',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Toggle active state
     */
    public function toggleActive(Request $request, int $id): JsonResponse
    {
        try {
            $user = $this->toggleUserActiveAction->execute($id, $request->user()?->id);

            return response()->json([
                'success'   => true,
                'is_active' => (bool)$user->is_active,
                'message'   => __('auth.user_status_updated') ?: 'تم تحديث حالة نشاط الحساب بنجاح',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
