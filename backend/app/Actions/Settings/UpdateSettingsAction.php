<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\Setting;
use App\Services\Settings\SettingSecrets;
use Illuminate\Support\Facades\DB;

final class UpdateSettingsAction
{
    /**
     * Update system settings dictionary and flush settings cache.
     *
     * SETG-7: secrets are write-only (see SettingSecrets) — a blank secret keeps the
     * stored value and the returned dictionary never contains a secret.
     *
     * @param  array<string, mixed>  $data  validated UpdateSettingsRequest data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        $excludeKeys = ['logo_file', 'logo_light_file', 'logo_dark_file'];
        $data = SettingSecrets::prepareForWrite($data);

        DB::transaction(function () use ($data, $excludeKeys): void {
            foreach ($data as $key => $value) {
                if (in_array($key, $excludeKeys, true)) {
                    continue;
                }

                if (is_bool($value)) {
                    Setting::set($key, $value ? '1' : '0');
                } else {
                    Setting::set($key, (string) ($value ?? ''));
                }
            }
        });

        Setting::clearCache();

        return SettingSecrets::mask(Setting::allCached());
    }
}
