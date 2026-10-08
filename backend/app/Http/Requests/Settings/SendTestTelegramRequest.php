<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SETG-7: POST /api/v1/settings/telegram/test.
 *
 * Both fields are optional: a blank bot token means "use the stored one" (the settings
 * screen never receives the stored token, see SettingSecrets). The character sets are
 * strict because both values end up in the Telegram API URL / payload.
 */
final class SendTestTelegramRequest extends FormRequest
{
    /** Telegram bot tokens are `<digits>:<base64url>`; never allow '/', '?', '#', spaces. */
    public const BOT_TOKEN_RULE = 'regex:/^[A-Za-z0-9:_-]+$/';

    /** One or more numeric chat ids (groups are negative) or @channel usernames, comma separated. */
    public const CHAT_ID_RULE = 'regex:/^(-?\d+|@[A-Za-z0-9_]{5,32})(\s*,\s*(-?\d+|@[A-Za-z0-9_]{5,32}))*$/';

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('telegram', Setting::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'bot_token' => ['nullable', 'string', 'max:255', self::BOT_TOKEN_RULE],
            'chat_id' => ['nullable', 'string', 'max:255', self::CHAT_ID_RULE],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'bot_token' => __('settings.attr_telegram_bot_token'),
            'chat_id' => __('settings.attr_telegram_chat_id'),
        ];
    }
}
