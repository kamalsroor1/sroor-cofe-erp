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

    public function handle(TelegramService $telegramService): void
    {
        $telegramService->sendDailySummaryNotification($this->date);
    }
}
