<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\ApiLoginDTO;
use App\Models\Store;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class ApiLoginAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly BuildAuthPayload $buildAuthPayload,
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
        // TODO(Phase 1 central identity, 00-REPORT row 8): replace this central-admin mirror.
        if (! $user && function_exists('tenant') && tenant()) {
            $centralUser = tenancy()->central(function () use ($login) {
                return User::where('phone', $login)->orWhere('email', $login)->first();
            });

            if ($centralUser && Hash::check($dto->password, $centralUser->password) && $centralUser->hasRole('admin')) {
                $mainStore = Store::first();
                $user = User::firstOrCreate(
                    ['phone' => $centralUser->phone],
                    [
                        'name' => $centralUser->name,
                        'email' => $centralUser->email,
                        'password' => $centralUser->password,
                        'is_active' => true,
                        'default_store_id' => $mainStore?->id,
                        'theme_preference' => $centralUser->theme_preference ?? 'dark',
                        'show_print_subtitle' => (bool) $centralUser->show_print_subtitle,
                    ]
                );

                $adminRole = Role::firstOrCreate(['name' => 'admin']);
                $user->syncRoles([$adminRole]);
            }
        }

        // 3. Verify credentials
        if (! $user || ! Hash::check($dto->password, $user->password)) {
            $this->activityLogService->log(
                module: 'auth',
                action: 'api_login_failed',
                description: __('auth.activity_login_failed', ['login' => $login]),
                properties: ['login' => $login, 'ip' => $dto->deviceIp]
            );

            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        // 4. Check active status
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => __('auth.account_disabled'),
            ]);
        }

        // 5. Create Sanctum token
        $tokenName = $dto->deviceName.'-'.now()->format('YmdHis');
        $token = $user->createToken($tokenName, ['*'])->plainTextToken;

        // 6. Update user metadata
        $user->update([
            'api_token' => null,
            'last_login_at' => now(),
        ]);

        // 7. Log success
        $this->activityLogService->log(
            module: 'auth',
            action: 'api_login',
            description: __('auth.activity_login', ['name' => $user->name, 'device' => $dto->deviceName]),
            subject: $user,
            userId: $user->id,
            properties: ['device' => $dto->deviceName, 'ip' => $dto->deviceIp]
        );

        // 8. Shared response payload (same shape as QuickLoginAction)
        return $this->buildAuthPayload->execute($user, $token);
    }
}
