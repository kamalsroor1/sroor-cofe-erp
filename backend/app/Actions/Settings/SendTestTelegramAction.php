<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\DTOs\Settings\TelegramTestDTO;
use App\Models\Setting;
use App\Services\TelegramService;
use Illuminate\Support\Facades\DB;

/**
 * SETG-7: store the (already validated) Telegram credentials that were provided, then
 * send a test message with them. Blank fields keep the stored values. Tenant scope: the
 * tenant `settings` table.
 */
final class SendTestTelegramAction
{
    public function __construct(
        private readonly TelegramService $telegramService,
    ) {}

    /**
     * @return array{success: bool, message: string}
     */
    public function execute(TelegramTestDTO $dto): array
    {
        DB::transaction(function () use ($dto): void {
            if ($dto->botToken !== null) {
                Setting::set('telegram_bot_token', $dto->botToken);
            }

            if ($dto->chatId !== null) {
                Setting::set('telegram_chat_id', $dto->chatId);
            }
        });

        $result = $this->telegramService->sendTestNotification($dto->chatId);

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
        ];
    }
}
