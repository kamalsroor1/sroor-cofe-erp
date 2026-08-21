<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\LoginDTO;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class SuperAdminLoginAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    public function execute(LoginDTO $dto): User
    {
        $phoneOrEmail = $dto->phone;

        $user = User::where(function ($q) use ($phoneOrEmail) {
            $q->where('phone', $phoneOrEmail)
              ->orWhere('email', $phoneOrEmail);
        })->where('is_active', true)->first();

        if (!$user || !Hash::check($dto->password, $user->password)) {
            $this->activityLogService->log(
                module: 'super_admin_auth',
                action: 'login_failed',
                description: __('super.login_failed_log', ['identifier' => $phoneOrEmail]),
                properties: ['attempted_identifier' => $phoneOrEmail]
            );

            throw ValidationException::withMessages([
                'phone' => __('auth.failed'),
            ]);
        }

        // Strict Check: User MUST have admin role or be authorized for Super Admin
        if (!$user->hasRole('admin') && !$user->can('super_admin.access')) {
            $this->activityLogService->log(
                module: 'super_admin_auth',
                action: 'unauthorized_attempt',
                description: __('super.unauthorized_attempt_log', ['user' => $user->name]),
                subject: $user,
                userId: $user->id
            );

            throw ValidationException::withMessages([
                'phone' => __('super.unauthorized_super_admin_access'),
            ]);
        }

        Auth::login($user, $dto->remember);

        $this->activityLogService->log(
            module: 'super_admin_auth',
            action: 'login',
            description: __('super.login_success_log', ['user' => $user->name]),
            subject: $user,
            userId: $user->id
        );

        return $user;
    }
}
