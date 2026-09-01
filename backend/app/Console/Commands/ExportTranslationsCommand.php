<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class ExportTranslationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lang:export';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Exports Laravel PHP lang files to unified frontend translations JSON/JS (Single Source of Truth)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Exporting Laravel lang files to frontend...');

        $langPath = base_path('lang');
        $locales = ['ar', 'en'];
        $result = [];

        foreach ($locales as $locale) {
            $localePath = $langPath . DIRECTORY_SEPARATOR . $locale;
            $result[$locale] = [];

            if (File::isDirectory($localePath)) {
                $files = File::files($localePath);
                foreach ($files as $file) {
                    if ($file->getExtension() === 'php') {
                        $group = $file->getFilenameWithoutExtension();
                        $data = include $file->getRealPath();
                        if (is_array($data)) {
                            $result[$locale][$group] = $data;
                        }
                    }
                }
            }

            // Also load JSON lang file if exists (e.g. lang/ar.json)
            $jsonFile = $langPath . DIRECTORY_SEPARATOR . "{$locale}.json";
            if (File::exists($jsonFile)) {
                $jsonData = json_decode(File::get($jsonFile), true);
                if (is_array($jsonData)) {
                    $result[$locale]['_json'] = $jsonData;
                }
            }
        }

        // 1. Save as JSON
        $jsonTarget = resource_path('js/helpers/defaultTranslations.json');
        File::put($jsonTarget, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 2. Save as JS ES Module export for instant tree-shaking & fallback
        $jsTarget = resource_path('js/helpers/defaultTranslations.js');
        $arTranslations = json_encode($result['ar'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $enTranslations = json_encode($result['en'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $jsContent = <<<JS
/**
 * AUTO-GENERATED FILE - DO NOT EDIT DIRECTLY!
 * Generated from PHP backend lang/ directory by `php artisan lang:export`
 * Single Source of Truth: backend/lang/ar/*.php and backend/lang/en/*.php
 */
export const defaultArabicTranslations = {$arTranslations};
export const defaultEnglishTranslations = {$enTranslations};

export default {
    ar: defaultArabicTranslations,
    en: defaultEnglishTranslations,
};
JS;

        File::put($jsTarget, $jsContent);

        $arGroups = count($result['ar'] ?? []);
        $enGroups = count($result['en'] ?? []);

        $this->info("✅ Successfully exported translations:");
        $this->line("   - Arabic (ar): {$arGroups} groups");
        $this->line("   - English (en): {$enGroups} groups");
        $this->line("   - Generated: {$jsonTarget}");
        $this->line("   - Generated: {$jsTarget}");

        return self::SUCCESS;
    }
}
