<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class SendTelegramDatabaseBackupCommand extends Command
{
    protected $signature = 'backup:telegram {--chat_id= : Custom target chat or group ID}';

    protected $description = 'Generate and upload a gzipped SQL database backup to Telegram';

    public function handle(TelegramService $telegramService): int
    {
        $chatId = $this->option('chat_id');
        $this->info('Creating and uploading SQL.GZ database backup to Telegram...');

        $res = $telegramService->sendDatabaseBackupNotification($chatId);

        if ($res['success']) {
            $this->info('✅ '.$res['message']);

            return Command::SUCCESS;
        }

        $this->error('❌ '.$res['message']);

        return Command::FAILURE;
    }
}
