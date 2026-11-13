<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\ApiLoginDTO;
use App\Http\Middleware\EnsureTenantActive;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\Tenancy\TenantLifecyclePolicy;
use Carbon\CarbonImmutable;
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

        // 0. SEC-1 (M1): tenant users only, never the CENTRAL `users` table. ResolveApiTenancy
        //    already answers 404 without a tenant; this is defense in depth (same answer as a
        //    wrong password, so nothing about the central database leaks).
        if (! tenancy()->initialized) {
            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

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

        // 3. SELL-07: a shop suspended for non-payment lets only ACTIVE billing managers in, and only
        //    to pay (EnsureTenantActive confines the token to /billing/*). Anyone else gets exactly the
        //    wrong-password answer (same message, same failed-login log): a distinct error for a
        //    correct password would be a password oracle while the shop is suspended.
        $paymentRecovery = $this->isPaymentRecovery();
        if ($paymentRecovery && (! $user->is_active || ! $user->can('billing.manage'))) {
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

        // 3b. Check active status
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => __('auth.account_disabled'),
            ]);
        }

        // 4. Create Sanctum token
        $tokenName = $dto->deviceName.'-'.now()->format('YmdHis');
        $token = $user->createToken(
            $tokenName,
            ['*'],
            now()->addMinutes(max(1, (int) config('auth.tokens.tenant_ttl_minutes', 720))),
        )->plainTextToken;

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
        $payload = $this->buildAuthPayload->execute($user, $token);

        if ($paymentRecovery) {
            $payload['subscription_blocked'] = true;
            $payload['user']['permissions'] = ['billing.manage'];
        }

        return $payload;
    }

    private function isPaymentRecovery(): bool
    {
        $tenant = tenant();
        if (! $tenant instanceof Tenant) {
            return false;
        }

        $decision = app(TenantLifecyclePolicy::class)->evaluate($tenant->lifecycleSnapshot(), CarbonImmutable::now());

        return EnsureTenantActive::isPaymentRecovery($decision->effectiveStatus, $tenant);
    }
}
