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

    public function handle(TelegramService $telegramService): void
    {
        $telegramService->sendOverdueShiftNotification();
    }
}
