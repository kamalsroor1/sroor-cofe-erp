<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Store;
use App\Models\Setting;
use App\Services\ActivityLogService;
use Illuminate\Validation\ValidationException;

final class ApiQuickLoginAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    /**
     * Authenticate workspace user quickly without password and issue Sanctum token
     */
    public function execute(string $login, string $deviceName = 'quick-login', ?string $deviceIp = null): array
    {
        // 1. Find user by phone or email or ID
        $user = User::where(function ($query) use ($login) {
            $query->where('phone', $login)
                  ->orWhere('email', $login)
                  ->orWhere('id', $login);
        })->first();

        // 2. Validate user existence
        if (!$user) {
            $this->activityLogService->log(
                module: 'auth',
                action: 'api_quick_login_failed',
                description: "محاولة دخول سريع غير ناجحة - الحساب غير موجود [{$login}]",
                properties: ['login' => $login, 'ip' => $deviceIp]
            );

            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        // 3. Check active status
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'login' => __('auth.account_disabled'),
            ]);
        }

        // 4. Create Sanctum token
        $tokenName = $deviceName . '-' . now()->format('YmdHis');
        $token = $user->createToken($tokenName, ['*'])->plainTextToken;

        // 5. Update user metadata
        $user->update([
            'api_token'     => $token,
            'last_login_at' => now(),
        ]);

        $user->loadMissing('stores');

        // 6. Store Context
        $currentStore = $user->getCurrentStore();
        $userStores = $user->hasRole('admin')
            ? Store::where('is_active', true)->orderBy('is_main', 'desc')->get(['id', 'name', 'code', 'type', 'is_main'])
            : $user->stores()->where('is_active', true)->get(['stores.id', 'name', 'code', 'type', 'is_main']);

        // 7. Log success
        $this->activityLogService->log(
            module: 'auth',
            action: 'api_quick_login',
            description: "دخول سريع ناجح للمستخدم [{$user->name}] بدون كلمة مرور",
            subject: $user,
            userId: $user->id,
            properties: ['device' => $deviceName, 'ip' => $deviceIp]
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
