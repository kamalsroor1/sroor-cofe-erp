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
 * SEC-21: text-only Telegram alert that a manager's approval PIN was locked for the day after
 * repeated wrong PINs (ManagerPinVerifier). Dispatched after commit from tenant context
 * (QueueTenancyBootstrapper restores the tenant in the worker). Carries ids only: never a PIN.
 * Sent to the shop's own chat only, never to the platform-wide chat.
 */
final class SendManagerPinLockedAlertJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $approverId,
        public readonly ?int $actorId,
        public readonly int $failures,
    ) {}

    public function handle(TelegramService $telegram, TenantSettings $settings, TenantClock $clock): void
    {
        try {
            if (trim((string) Setting::get('telegram_chat_id')) === '') {
                return;
            }

            $approver = User::query()->find($this->approverId);
            if ($approver === null) {
                return;
            }

            $actor = $this->actorId !== null ? User::query()->find($this->actorId) : null;

            $telegram->sendMessage(__('pos.telegram_manager_pin_locked', [
                'shop' => htmlspecialchars($this->shopName(), ENT_QUOTES, 'UTF-8'),
                'approver' => htmlspecialchars($approver->name, ENT_QUOTES, 'UTF-8'),
                'actor' => htmlspecialchars($actor !== null ? $actor->name : '-', ENT_QUOTES, 'UTF-8'),
                'failures' => $this->failures,
                'time' => $clock->now()->format('Y-m-d H:i'),
            ], $settings->defaultLocale()));
        } catch (Throwable $e) {
            Log::warning('Manager PIN lock Telegram alert failed: '.$e::class);
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
