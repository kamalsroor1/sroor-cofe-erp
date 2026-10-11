<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\ApiLoginDTO;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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

        // 2. Verify credentials. Tenant users only: the legacy fallback that checked a central
        //    `users` admin password and minted/promoted a tenant admin was a master key into
        //    every tenant and is gone (security audit, W2 lane 3I).
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

        // 3. Check active status
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => __('auth.account_disabled'),
            ]);
        }

        // 4. Create Sanctum token
        $tokenName = $dto->deviceName.'-'.now()->format('YmdHis');
        $token = $user->createToken($tokenName, ['*'])->plainTextToken;

        // 5. Update user metadata
        $user->update([
            'api_token' => null,
            'last_login_at' => now(),
        ]);

        // 6. Log success
        $this->activityLogService->log(
            module: 'auth',
            action: 'api_login',
            description: __('auth.activity_login', ['name' => $user->name, 'device' => $dto->deviceName]),
            subject: $user,
            userId: $user->id,
            properties: ['device' => $dto->deviceName, 'ip' => $dto->deviceIp]
        );

        // 7. Shared response payload (same shape as QuickLoginAction)
        return $this->buildAuthPayload->execute($user, $token);
    }
}
