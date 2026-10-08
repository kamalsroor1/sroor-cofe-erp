<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class SendOverdueShiftTelegramAlertCommand extends Command
{
    protected $signature = 'notify:overdue-shifts';

    protected $description = 'Check and send alerts for cash shifts open for more than 24 hours';

    public function handle(TelegramService $telegramService): int
    {
        $this->info('Checking overdue open shifts...');
        $res = $telegramService->sendOverdueShiftNotification();

        if ($res['success']) {
            $this->info('✅ '.$res['message']);

            return Command::SUCCESS;
        }

        $this->error('❌ '.$res['message']);

        return Command::FAILURE;
    }
}
