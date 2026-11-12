<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Services\Settings\SettingSecrets;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SEC-21 (tenant DB): the shop's Telegram bot token (settings.telegram_bot_token) is stored
 * encrypted with APP_KEY. Re-encrypts a plain-text value written before the change. Idempotent
 * (an already encrypted value is left alone); down() writes the plain text back, so the previous
 * release (which reads the value as is) keeps working after a rollback.
 *
 * Reading stays tolerant of plain text (SettingSecrets::decrypt), so the order of deploy and
 * migration does not matter.
 */
return new class extends Migration
{
    private const KEY = 'telegram_bot_token';

    public function up(): void
    {
        $this->rewrite(static fn (string $value): ?string => SettingSecrets::looksEncrypted($value) ? null : Crypt::encryptString($value));
    }

    public function down(): void
    {
        $this->rewrite(static fn (string $value): ?string => SettingSecrets::looksEncrypted($value) ? Crypt::decryptString($value) : null);
    }

    /**
     * @param  Closure(string): ?string  $transform  null = leave the row unchanged
     */
    private function rewrite(Closure $transform): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $rows = DB::table('settings')->where('key', self::KEY)->get(['id', 'value']);
        $changed = false;

        foreach ($rows as $row) {
            $value = is_string($row->value) ? trim($row->value) : '';
            if ($value === '') {
                continue;
            }

            $new = $transform($value);
            if ($new !== null) {
                DB::table('settings')->where('id', $row->id)->update(['value' => $new]);
                $changed = true;
            }
        }

        if ($changed) {
            // The cached settings dictionary of this tenant holds the old value.
            Cache::forget(Setting::getCacheKey());
        }
    }
};
