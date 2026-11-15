<?php

namespace App\Jobs;

use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDailySummaryReportJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public ?string $date;

    public function __construct(?string $date = null)
    {
        $this->date = $date;
    }

    /** OPS-24: per-shop alert, sent only with the shop's own bot and chat (never the platform bot). */
    public function handle(TelegramService $telegramService): void
    {
        $telegramService->forShopOnly()->sendDailySummaryNotification($this->date);
    }
}
