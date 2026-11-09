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
    protected $description = 'Exports Laravel PHP lang files to per-locale frontend translations JSON (Single Source of Truth)';

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
            $localePath = $langPath.DIRECTORY_SEPARATOR.$locale;
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
            $jsonFile = $langPath.DIRECTORY_SEPARATOR."{$locale}.json";
            if (File::exists($jsonFile)) {
                $jsonData = json_decode(File::get($jsonFile), true);
                if (is_array($jsonData)) {
                    $result[$locale]['_json'] = $jsonData;
                }
            }
        }

        // One file per locale: the SPA lazily imports only the active one, and only as a fallback
        // when the server did not inline window.spaTranslations (resources/js/helpers/trans.js).
        $targets = [];
        foreach ($locales as $locale) {
            $target = resource_path("js/helpers/defaultTranslations.{$locale}.json");
            File::put($target, json_encode($result[$locale] ?? [], JSON_UNESCAPED_UNICODE));
            $targets[] = $target;
        }

        $arGroups = count($result['ar'] ?? []);
        $enGroups = count($result['en'] ?? []);

        $this->info('✅ Successfully exported translations:');
        $this->line("   - Arabic (ar): {$arGroups} groups");
        $this->line("   - English (en): {$enGroups} groups");
        foreach ($targets as $target) {
            $this->line("   - Generated: {$target}");
        }

        return self::SUCCESS;
    }
}
