<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

final class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string)$request->input('search', ''));
        $role = (string)$request->input('role', 'all');

        $query = User::with(['roles', 'defaultStore']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role !== 'all') {
            $query->role($role);
        }

        $users = $query->latest('id')->paginate(15)->withQueryString();
        $roles = Role::select('id', 'name')->get();
        $stores = Store::where('is_active', true)->select('id', 'name')->get();

        return Inertia::render('Users/Index', [
            'users' => $users->through(fn($u) => [
                'id'                => $u->id,
                'name'              => $u->name,
                'phone'             => $u->phone,
                'email'             => $u->email,
                'is_active'         => (bool)$u->is_active,
                'default_store_id'  => $u->default_store_id,
                'default_store_name'=> $u->defaultStore?->name,
                'roles'             => $u->roles->pluck('name')->toArray(),
                'primary_role'      => $u->roles->first()?->name ?: 'cashier',
                'created_at'        => $u->created_at ? $u->created_at->toDateString() : '',
            ]),
            'roles' => $roles->map(fn($r) => [
                'id'   => $r->name,
                'name' => match ($r->name) {
                    'admin'       => 'مدير النظام (كامل الصلاحيات) 👑',
                    'cashier'     => 'كاشير مبيعات ونقطة بيع 🛒',
                    'storekeeper' => 'أمين مخزن وتوريدات 📦',
                    'accountant'  => 'محاسب ومدقق مالي 💼',
                    default       => $r->name,
                },
            ]),
            'stores' => $stores,
            'filters' => [
                'search' => $search,
                'role'   => $role,
            ],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name'             => $validated['name'],
                'phone'            => $validated['phone'],
                'email'            => $validated['email'] ?? null,
                'password'         => Hash::make($validated['password']),
                'default_store_id' => $validated['default_store_id'] ?? null,
                'is_active'        => $validated['is_active'] ?? true,
            ]);

            $user->syncRoles([$validated['role']]);
        });

        return redirect()->back()->with('success', __('auth.user_created_success'));
    }

    public function update(UpdateUserRequest $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated) {
            $data = [
                'name'             => $validated['name'],
                'phone'            => $validated['phone'],
                'email'            => $validated['email'] ?? null,
                'default_store_id' => $validated['default_store_id'] ?? null,
                'is_active'        => $validated['is_active'] ?? true,
            ];

            if (!empty($validated['password'])) {
                $data['password'] = Hash::make($validated['password']);
            }

            $user->update($data);
            $user->syncRoles([$validated['role']]);
        });

        return redirect()->back()->with('success', __('auth.user_updated_success'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', __('auth.cannot_delete_own_account'));
        }

        DB::transaction(function () use ($user) {
            $user->delete();
        });

        return redirect()->back()->with('success', __('auth.user_deleted_success'));
    }

    public function toggleActive(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', __('auth.cannot_disable_own_account'));
        }

        $user->update(['is_active' => !$user->is_active]);

        return redirect()->back()->with('success', __('auth.user_status_updated'));
    }
}