<?php

namespace App\Jobs;

use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTelegramDatabaseBackupJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public ?string $chatId;

    public function __construct(?string $chatId = null)
    {
        $this->chatId = $chatId;
    }

    public function handle(TelegramService $telegramService): void
    {
        $telegramService->sendDatabaseBackupNotification($this->chatId);
    }
}
