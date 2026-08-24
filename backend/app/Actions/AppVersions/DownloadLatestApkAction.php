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

        if (!$latest || !Storage::disk('public')->exists($latest->apk_path)) {
            // Check fallback in public folder or root if exists
            $fallbacks = [
                public_path('sroor-cofe-erp-2m.apk'),
                public_path('app.apk'),
                base_path('../sroor-cofe-erp-2m.apk'),
                base_path('../mobile/sroor-coffee-erp-v1.0.apk'),
            ];

            foreach ($fallbacks as $fallbackPath) {
                if (file_exists($fallbackPath)) {
                    return response()->download($fallbackPath, 'sroor-cofe-erp-latest.apk', [
                        'Content-Type' => 'application/vnd.android.package-archive',
                        'Cache-Control' => 'no-cache, private',
                    ]);
                }
            }

            throw new NotFoundHttpException('ملف التحديث غير متوفر حالياً على السيرفر.');
        }

        // Increment download counter
        $latest->increment('download_count');

        $fullPath = Storage::disk('public')->path($latest->apk_path);

        return response()->download($fullPath, $latest->apk_filename ?? 'sroor-coffee-erp.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Cache-Control' => 'no-cache, private',
        ]);
    }
}
