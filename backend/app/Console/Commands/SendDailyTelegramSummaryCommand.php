<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class SendDailyTelegramSummaryCommand extends Command
{
    protected $signature = 'notify:daily-summary {--date= : Custom target date (YYYY-MM-DD)}';

    protected $description = 'Send daily EOD business summary report via Telegram';

    public function handle(TelegramService $telegramService): int
    {
        $date = $this->option('date');
        $this->info('Sending daily business summary for date: '.($date ?: now()->toDateString()));

        $res = $telegramService->sendDailySummaryNotification($date);

        if ($res['success']) {
            $this->info('✅ '.$res['message']);

            return Command::SUCCESS;
        }

        $this->error('❌ '.$res['message']);

        return Command::FAILURE;
    }
}
