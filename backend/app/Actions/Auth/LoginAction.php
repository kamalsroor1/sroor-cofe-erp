<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\LoginDTO;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Auth;

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

        // No central fallback: a central `users` admin is never a tenant credential
        // (security audit, W2 lane 3I).
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
