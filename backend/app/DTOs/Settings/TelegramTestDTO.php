<?php

declare(strict_types=1);

namespace App\DTOs\Settings;

/**
 * SETG-7: input of SendTestTelegramAction. Blank values are normalised to null
 * ("use what is stored").
 */
final readonly class TelegramTestDTO
{
    public function __construct(
        public ?string $botToken = null,
        public ?string $chatId = null,
    ) {}

    /**
     * @param  array{bot_token?: string|null, chat_id?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            botToken: self::clean($data['bot_token'] ?? null),
            chatId: self::clean($data['chat_id'] ?? null),
        );
    }

    /**
     * @return array{bot_token: string|null, chat_id: string|null}
     */
    public function toArray(): array
    {
        return [
            'bot_token' => $this->botToken,
            'chat_id' => $this->chatId,
        ];
    }

    private static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
