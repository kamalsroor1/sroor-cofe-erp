<?php

declare(strict_types=1);

namespace App\Actions\System;

final class GetTranslationsAction
{
    /**
     * Load full translation dictionary for the given locale
     */
    public function execute(?string $locale = null): array
    {
        $locale = $locale ?: app()->getLocale();
        $translations = [];

        // 1. Load all PHP translation groups for current locale (e.g. auth, nav, pos, dashboard, inventory, etc.)
        $langPath = lang_path($locale);
        if (is_dir($langPath)) {
            foreach (glob($langPath . '/*.php') as $file) {
                $group = basename($file, '.php');
                $translations[$group] = trans($group, [], $locale);
            }
        }

        // 2. Load Fallback 'ar' if current locale is missing groups
        if ($locale !== 'ar') {
            $arPath = lang_path('ar');
            if (is_dir($arPath)) {
                foreach (glob($arPath . '/*.php') as $file) {
                    $group = basename($file, '.php');
                    if (!isset($translations[$group])) {
                        $translations[$group] = trans($group, [], 'ar');
                    }
                }
            }
        }

        // 3. Merge JSON translation strings if existing
        $jsonFile = lang_path($locale . '.json');
        if (file_exists($jsonFile)) {
            $json = json_decode((string)file_get_contents($jsonFile), true) ?: [];
            $translations = array_merge($translations, $json);
        }

        return $translations;
    }
}
