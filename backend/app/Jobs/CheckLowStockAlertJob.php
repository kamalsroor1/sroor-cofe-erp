<?php

namespace App\Jobs;

use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckLowStockAlertJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /** OPS-24: shop stock goes only through the shop's own bot and chat, never the platform's. */
    public function handle(TelegramService $telegramService): void
    {
        $telegramService->forShopOnly()->sendLowStockNotification();
    }
}
