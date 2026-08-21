<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\Setting;

final class UpdateSettingsAction
{
    /**
     * Update system settings dictionary and flush settings cache
     */
    public function execute(array $data): array
    {
        $excludeKeys = ['logo_file', 'logo_light_file', 'logo_dark_file'];

        foreach ($data as $key => $value) {
            if (in_array($key, $excludeKeys, true)) {
                continue;
            }

            if (is_bool($value)) {
                Setting::set($key, $value ? '1' : '0');
            } else {
                Setting::set($key, (string)($value ?? ''));
            }
        }

        Setting::clearCache();

        return Setting::allCached();
    }
}
