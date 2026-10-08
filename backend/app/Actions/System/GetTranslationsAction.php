<?php

declare(strict_types=1);

namespace App\Actions\System;

final class GetTranslationsAction
{
    /**
     * Server-side allowlist of locales. The locale is used to build filesystem
     * paths (lang/{locale}), so it must never come from raw client input.
     */
    public const SUPPORTED_LOCALES = ['ar', 'en'];

    public const DEFAULT_LOCALE = 'ar';

    /**
     * Load full translation dictionary for the given locale.
     * Unsupported/malicious locale values are normalized to the default.
     */
    public function execute(?string $locale = null): array
    {
        $locale = self::normalizeLocale($locale);
        $translations = [];

        // 1. Load all PHP translation groups for the (allowlisted) locale
        foreach ($this->groupsFor($locale) as $group) {
            $translations[$group] = trans($group, [], $locale);
        }

        // 2. Fallback to 'ar' for groups missing in the current locale
        if ($locale !== 'ar') {
            foreach ($this->groupsFor('ar') as $group) {
                if (! isset($translations[$group])) {
                    $translations[$group] = trans($group, [], 'ar');
                }
            }
        }

        // 3. Merge JSON translation strings if existing
        $jsonFile = lang_path($locale.'.json');
        if (is_file($jsonFile)) {
            $json = json_decode((string) file_get_contents($jsonFile), true) ?: [];
            $translations = array_merge($translations, $json);
        }

        return $translations;
    }

    /**
     * Pure helper: map any input to a supported locale (never echoes raw input).
     */
    public static function normalizeLocale(?string $locale): string
    {
        if (in_array($locale, self::SUPPORTED_LOCALES, true)) {
            return $locale;
        }

        $appLocale = app()->getLocale();

        return in_array($appLocale, self::SUPPORTED_LOCALES, true) ? $appLocale : self::DEFAULT_LOCALE;
    }

    /**
     * @return list<string>
     */
    private function groupsFor(string $locale): array
    {
        $langPath = lang_path($locale);
        if (! is_dir($langPath)) {
            return [];
        }

        $groups = [];
        foreach (glob($langPath.'/*.php') ?: [] as $file) {
            $group = basename($file, '.php');
            if (preg_match('/^[a-z_]+$/', $group) === 1) {
                $groups[] = $group;
            }
        }

        return $groups;
    }
}
