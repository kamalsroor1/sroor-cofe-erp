<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\LoginDTO;
use App\Models\Store;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

final class LoginAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    public function execute(LoginDTO $dto): bool
    {
        $cleanPhone = $dto->phone;

        // 1. Direct Tenant / Local DB Attempt by Phone
        $attempt = Auth::attempt([
            'phone' => $cleanPhone,
            'password' => $dto->password,
            'is_active' => true,
        ], $dto->remember);

        // 2. Direct Attempt by Email Fallback
        if (! $attempt) {
            $attempt = Auth::attempt([
                'email' => $cleanPhone,
                'password' => $dto->password,
                'is_active' => true,
            ], $dto->remember);
        }

        // 3. Central Super Admin Fallback when in Tenant Context
        if (! $attempt && function_exists('tenant') && tenant()) {
            $centralUser = tenancy()->central(function () use ($cleanPhone) {
                return User::where('phone', $cleanPhone)->orWhere('email', $cleanPhone)->first();
            });

            if ($centralUser && Hash::check($dto->password, $centralUser->password) && $centralUser->hasRole('admin')) {
                $mainStore = Store::first();
                $tenantUser = User::firstOrCreate(
                    ['phone' => $centralUser->phone],
                    [
                        'name' => $centralUser->name,
                        'email' => $centralUser->email,
                        'password' => $centralUser->password,
                        'is_active' => true,
                        'default_store_id' => $mainStore?->id,
                        'theme_preference' => $centralUser->theme_preference ?? 'dark',
                    ]
                );

                $adminRole = Role::firstOrCreate(['name' => 'admin']);
                $tenantUser->syncRoles([$adminRole]);

                Auth::login($tenantUser, $dto->remember);
                $attempt = true;
            }
        }

        if (! $attempt) {
            $this->activityLogService->log(
                module: 'auth',
                action: 'login_failed',
                description: __('auth.activity_web_login_failed', ['phone' => $cleanPhone]),
                properties: ['attempted_phone' => $cleanPhone]
            );

            return false;
        }

        $user = Auth::user();
        $this->activityLogService->log(
            module: 'auth',
            action: 'login',
            description: __('auth.activity_web_login', ['name' => $user->name, 'phone' => $user->phone]),
            subject: $user,
            userId: $user->id
        );

        return true;
    }
}
