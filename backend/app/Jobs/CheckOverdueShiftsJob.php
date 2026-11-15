<?php

namespace App\Jobs;

use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckOverdueShiftsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * OPS-24: dispatched from a tenant context by notify:dispatch-tenant-alerts, so
     * QueueTenancyBootstrapper restores the tenant in the worker.
     *
     * @param  list<int>|null  $shiftIds  only these shifts (not alerted yet); null = all overdue
     */
    public function __construct(
        public readonly ?int $hours = null,
        public readonly ?array $shiftIds = null,
    ) {}

    /** OPS-24: per-shop alert, sent only with the shop's own bot and chat (never the platform bot). */
    public function handle(TelegramService $telegramService): void
    {
        $telegramService->forShopOnly()->sendOverdueShiftNotification(false, $this->hours, $this->shiftIds);
    }
}
