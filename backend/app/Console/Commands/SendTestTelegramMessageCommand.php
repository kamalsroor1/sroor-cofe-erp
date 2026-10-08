<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class SendTestTelegramMessageCommand extends Command
{
    protected $signature = 'notify:test {--chat_id= : Custom target chat ID}';

    protected $description = 'Send a test notification message via Telegram';

    public function handle(TelegramService $telegramService): int
    {
        $chatId = $this->option('chat_id');
        $this->info('Sending test message via Telegram Bot...');

        $res = $telegramService->sendTestNotification($chatId);

        if ($res['success']) {
            $this->info('✅ '.$res['message']);

            return Command::SUCCESS;
        }

        $this->error('❌ '.$res['message']);

        return Command::FAILURE;
    }
}
