<?php

declare(strict_types=1);

namespace App\Services\Settings;

/**
 * SETG-7: write-only tenant settings (secrets).
 *
 * A secret is accepted on write but never echoed back: read payloads carry an empty
 * string under the key (so older clients that bind the field keep working) plus a
 * `has_<key>` boolean. Because the client never receives the value, a blank value on
 * write means "keep the stored secret"; removing it needs the explicit `clear_<key>`
 * flag.
 *
 * Tenant scope: operates on the tenant `settings` table values handed to it — it does
 * no I/O itself.
 */
final class SettingSecrets
{
    /** @var list<string> */
    public const KEYS = ['telegram_bot_token'];

    public static function hasFlag(string $key): string
    {
        return 'has_'.$key;
    }

    public static function clearFlag(string $key): string
    {
        return 'clear_'.$key;
    }

    /**
     * Replace every secret with '' and add its `has_<key>` flag.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function mask(array $settings): array
    {
        foreach (self::KEYS as $key) {
            $value = $settings[$key] ?? null;

            $settings[$key] = '';
            $settings[self::hasFlag($key)] = is_string($value) && trim($value) !== '';
        }

        return $settings;
    }

    /**
     * Turn validated input into the values to persist: drop blank secrets (keep the
     * stored one), honour `clear_<key>` (a new non-blank value wins over the flag) and
     * never persist the flags themselves.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepareForWrite(array $data): array
    {
        foreach (self::KEYS as $key) {
            $flag = self::clearFlag($key);
            $clear = filter_var($data[$flag] ?? false, FILTER_VALIDATE_BOOLEAN);
            unset($data[$flag]);

            $value = $data[$key] ?? null;
            $blank = ! is_string($value) || trim($value) === '';

            if ($blank) {
                unset($data[$key]);

                if ($clear) {
                    $data[$key] = '';
                }

                continue;
            }

            $data[$key] = trim($value);
        }

        return $data;
    }
}
