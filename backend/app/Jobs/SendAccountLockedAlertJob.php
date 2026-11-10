<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\TenantSettings;
use App\Services\TelegramService;
use App\Support\TenantClock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SEC-05: text-only Telegram alert that an account was locked after repeated failed sign-ins.
 * Dispatched from tenant context (QueueTenancyBootstrapper restores the tenant in the worker).
 * Carries no password, token or secret.
 */
final class SendAccountLockedAlertJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $userId,
        public readonly string $login,
        public readonly ?string $ip,
        public readonly int $lockMinutes,
    ) {}

    public function handle(TelegramService $telegram, TenantSettings $settings, TenantClock $clock): void
    {
        try {
            // Only the shop's own chat: never fall back to the platform-wide config chat id,
            // which would leak this shop's user name, login and IP to the platform operators.
            if (trim((string) Setting::get('telegram_chat_id')) === '') {
                return;
            }

            $user = User::query()->find($this->userId);
            if ($user === null) {
                return;
            }

            $locale = $settings->defaultLocale();

            $telegram->sendMessage(__('auth.telegram_account_locked', [
                'shop' => htmlspecialchars($this->shopName(), ENT_QUOTES, 'UTF-8'),
                'name' => htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8'),
                'login' => htmlspecialchars($this->login, ENT_QUOTES, 'UTF-8'),
                'time' => $clock->now()->format('Y-m-d H:i'),
                'minutes' => $this->lockMinutes,
                'ip' => htmlspecialchars($this->ip ?? '-', ENT_QUOTES, 'UTF-8'),
            ], $locale));
        } catch (Throwable $e) {
            Log::warning('Account lock Telegram alert failed: '.$e::class);
        }
    }

    private function shopName(): string
    {
        $name = trim((string) Setting::get('company_name'));
        if ($name !== '') {
            return $name;
        }

        $tenant = tenancy()->initialized ? tenant() : null;

        return $tenant !== null ? (string) $tenant->getAttribute('name') : '-';
    }
}
