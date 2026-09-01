<?php

namespace App\Actions\AppVersions;

use App\Models\AppVersion;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DownloadLatestApkAction
{
    public function execute(string $platform = 'android'): BinaryFileResponse
    {
        $latest = AppVersion::forPlatform($platform)
            ->active()
            ->whereNotNull('apk_path')
            ->orderByDesc('version_code')
            ->first();

        $contentType = match ($platform) {
            'windows' => 'application/vnd.microsoft.portable-executable',
            'android' => 'application/vnd.android.package-archive',
            default => 'application/octet-stream',
        };

        if (!$latest || !Storage::disk('public')->exists($latest->apk_path)) {
            // Check fallback in public folder or root if exists
            $fallbacks = $platform === 'windows' ? [
                public_path('Sroor-ERP-POS-Setup.exe'),
                public_path('desktop-setup.exe'),
                base_path('../desktop/dist/Sroor-ERP-POS-Setup-1.0.0.exe'),
                base_path('Sroor-ERP-POS-Setup.exe'),
            ] : [
                public_path('sroor-cofe-erp-2m.apk'),
                public_path('app.apk'),
                base_path('../sroor-cofe-erp-2m.apk'),
                base_path('../mobile/sroor-coffee-erp-v1.0.apk'),
            ];

            $appNameSlug = \Illuminate\Support\Str::slug(config('app.name', 'erp-pos')) ?: 'erp-pos';
            $defaultFilename = $platform === 'windows' ? ($appNameSlug . '-Setup-latest.exe') : ($appNameSlug . '-latest.apk');

            foreach ($fallbacks as $fallbackPath) {
                if (file_exists($fallbackPath)) {
                    return response()->download($fallbackPath, $defaultFilename, [
                        'Content-Type' => $contentType,
                        'Cache-Control' => 'no-cache, private',
                    ]);
                }
            }

            throw new NotFoundHttpException('ملف التحديث غير متوفر حالياً على السيرفر.');
        }

        // Increment download counter
        $latest->increment('download_count');

        $fullPath = Storage::disk('public')->path($latest->apk_path);
        $appNameSlug = \Illuminate\Support\Str::slug(config('app.name', 'erp-pos')) ?: 'erp-pos';

        return response()->download($fullPath, $latest->apk_filename ?? ($platform === 'windows' ? ($appNameSlug . '-Setup.exe') : ($appNameSlug . '.apk')), [
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-cache, private',
        ]);
    }
}
