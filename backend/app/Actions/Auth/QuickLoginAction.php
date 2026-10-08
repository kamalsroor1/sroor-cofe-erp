<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\QuickLoginDTO;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\PlatformSuperAdmin;
use App\Support\QuickLoginGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Testing-only passwordless login (tenant context only, gated by QuickLoginGate).
 *
 * Issues a Sanctum token that carries ONLY the `quick-login` ability and expires
 * after `auth.quick_login.token_ttl_minutes`. Never writes users.api_token.
 */
final class QuickLoginAction
{
    public const TOKEN_ABILITY = 'quick-login';

    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly BuildAuthPayload $buildAuthPayload,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(QuickLoginDTO $dto): array
    {
        $user = User::query()
            ->whereKey($dto->userId)
            ->where('is_active', true)
            ->first();

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'user_id' => __('auth.failed'),
            ]);
        }

        if ($user->hasRole('super_admin') || PlatformSuperAdmin::check($user)) {
            Log::warning('Quick login refused for super admin account', [
                'user_id' => $user->id,
                'tenant_id' => $this->tenantId(),
                'ip' => $dto->deviceIp,
            ]);

            throw ValidationException::withMessages([
                'user_id' => __('auth.quick_login_forbidden'),
            ]);
        }

        $expiresAt = now()->addMinutes(QuickLoginGate::tokenTtlMinutes());

        $plainTextToken = DB::transaction(function () use ($user, $dto, $expiresAt): string {
            $token = $user->createToken(
                'quick-login-'.$dto->deviceName,
                [self::TOKEN_ABILITY],
                $expiresAt,
            )->plainTextToken;

            $user->forceFill(['last_login_at' => now()])->save();

            $this->activityLogService->log(
                module: 'auth',
                action: 'api_quick_login',
                description: __('auth.activity_quick_login', [
                    'name' => $user->name,
                    'device' => $dto->deviceName,
                ]),
                subject: $user,
                properties: [
                    'device' => $dto->deviceName,
                    'ip' => $dto->deviceIp,
                    'tenant_id' => $this->tenantId(),
                ],
                userId: $user->id,
            );

            return $token;
        });

        Log::warning('Quick login token issued (testing only)', [
            'user_id' => $user->id,
            'tenant_id' => $this->tenantId(),
            'device' => $dto->deviceName,
            'ip' => $dto->deviceIp,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        return array_merge(
            $this->buildAuthPayload->execute($user, $plainTextToken),
            ['expires_at' => $expiresAt->toIso8601String()],
        );
    }

    private function tenantId(): ?string
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        return $tenant ? (string) $tenant->getTenantKey() : null;
    }
}
