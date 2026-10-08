<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class SendLowStockTelegramAlertCommand extends Command
{
    protected $signature = 'notify:low-stock';

    protected $description = 'Check and send low stock alert via Telegram';

    public function handle(TelegramService $telegramService): int
    {
        $this->info('Checking low stock items across branches...');
        $res = $telegramService->sendLowStockNotification();

        if ($res['success']) {
            $this->info('✅ '.$res['message']);

            return Command::SUCCESS;
        }

        $this->error('❌ '.$res['message']);

        return Command::FAILURE;
    }
}
