<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Jobs\SendAccountLockedAlertJob;
use App\Models\User;
use App\Support\Auth\AccountLockout;
use App\Support\RateLimitKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SEC-05: queue a Telegram alert when a real account has just been locked. Never throws.
 */
final class DispatchAccountLockAlertAction
{
    private const ACCOUNT_WINDOW_SECONDS = 3600;

    private const TENANT_WINDOW_SECONDS = 3600;

    private const TENANT_MAX_ALERTS = 10;

    public function execute(string $login, ?string $ip): void
    {
        try {
            $user = User::query()
                ->where(fn ($q) => $q->where('phone', $login)->orWhere('email', $login))
                ->first();

            // Unknown logins are not actionable for the owner.
            if ($user === null || ! $this->withinThrottle($login)) {
                return;
            }

            SendAccountLockedAlertJob::dispatch($user->id, $login, $ip, AccountLockout::lockoutMinutes())->afterCommit();
        } catch (Throwable $e) {
            Log::warning('Account lock alert could not be queued: '.$e::class);
        }
    }

    private function withinThrottle(string $login): bool
    {
        $scope = RateLimitKey::scope();

        if (! Cache::add('login-lock-alert|'.$scope.'|'.RateLimitKey::identifier($login), 1, self::ACCOUNT_WINDOW_SECONDS)) {
            return false;
        }

        $tenantKey = 'login-lock-alert-count|'.$scope;
        Cache::add($tenantKey, 0, self::TENANT_WINDOW_SECONDS);

        return (int) Cache::increment($tenantKey) <= self::TENANT_MAX_ALERTS;
    }
}
