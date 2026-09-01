<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\ApiLoginDTO;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Store;
use App\Models\Setting;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class ApiLoginAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    /**
     * Authenticate API user and issue Sanctum token
     */
    public function execute(ApiLoginDTO $dto): array
    {
        $login = $dto->login;

        // 1. Find user by phone or email
        $user = User::where(function ($query) use ($login) {
            $query->where('phone', $login)
                  ->orWhere('email', $login);
        })->first();

        // 2. Central Super Admin Fallback when in Tenant Context
        if (!$user && function_exists('tenant') && tenant()) {
            $centralUser = tenancy()->central(function () use ($login) {
                return User::where('phone', $login)->orWhere('email', $login)->first();
            });

            if ($centralUser && Hash::check($dto->password, $centralUser->password) && $centralUser->hasRole('admin')) {
                $mainStore = Store::first();
                $user = User::firstOrCreate(
                    ['phone' => $centralUser->phone],
                    [
                        'name'                => $centralUser->name,
                        'email'               => $centralUser->email,
                        'password'            => $centralUser->password,
                        'is_active'           => true,
                        'default_store_id'    => $mainStore?->id,
                        'theme_preference'    => $centralUser->theme_preference ?? 'dark',
                        'show_print_subtitle' => (bool)$centralUser->show_print_subtitle,
                    ]
                );

                $adminRole = Role::firstOrCreate(['name' => 'admin']);
                $user->syncRoles([$adminRole]);
            }
        }

        // 3. Verify credentials
        if (!$user || !Hash::check($dto->password, $user->password)) {
            $this->activityLogService->log(
                module: 'auth',
                action: 'api_login_failed',
                description: "محاولة تسجيل دخول API غير ناجحة للحساب [{$login}]",
                properties: ['login' => $login, 'ip' => $dto->deviceIp]
            );

            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        // 4. Check active status
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'login' => __('auth.account_disabled'),
            ]);
        }

        // 5. Create Sanctum token
        $tokenName = $dto->deviceName . '-' . now()->format('YmdHis');
        $token = $user->createToken($tokenName, ['*'])->plainTextToken;

        // 6. Update user metadata
        $user->update([
            'api_token'     => $token,
            'last_login_at' => now(),
        ]);

        $user->loadMissing('stores');

        // 7. Store Context
        $currentStore = $user->getCurrentStore();
        $userStores = $user->hasRole('admin')
            ? Store::where('is_active', true)->orderBy('is_main', 'desc')->get(['id', 'name', 'code', 'type', 'is_main'])
            : $user->stores()->where('is_active', true)->get(['stores.id', 'name', 'code', 'type', 'is_main']);

        // 8. Log success
        $this->activityLogService->log(
            module: 'auth',
            action: 'api_login',
            description: "تسجيل دخول API ناجح للمستخدم [{$user->name}] من جهاز ({$dto->deviceName})",
            subject: $user,
            userId: $user->id,
            properties: ['device' => $dto->deviceName, 'ip' => $dto->deviceIp]
        );

        return [
            'token' => $token,
            'user'  => (new UserResource($user))->resolve(),
            'store' => $currentStore ? [
                'id'      => $currentStore->id,
                'name'    => $currentStore->name,
                'code'    => $currentStore->code,
                'type'    => $currentStore->type,
                'is_main' => (bool)$currentStore->is_main,
            ] : null,
            'stores' => $userStores,
            'system' => [
                'company_name'     => Setting::get('company_name') ?: (function_exists('tenant') && tenant('name') ? tenant('name') : 'مؤسسة تجارية'),
                'company_subtitle' => Setting::get('company_subtitle') ?: '',
                'system_theme'     => Setting::get('system_theme_color', 'emerald'),
                'server_time'      => now()->toDateTimeString(),
            ],
        ];
    }
}
