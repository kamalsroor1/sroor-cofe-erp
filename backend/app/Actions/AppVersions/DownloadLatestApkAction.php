<?php

declare(strict_types=1);

namespace App\Actions\AppVersions;

use App\Models\AppVersion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DownloadLatestApkAction
{
    /**
     * Serve the latest active release binary for the platform.
     *
     * Only files uploaded through the super-admin AppVersion flow (public disk) are
     * served. There is deliberately no fallback to files in public/ or outside the
     * app: that served the wrong binary (e.g. an Android APK to iOS) and made the
     * response depend on whatever happened to sit on the server's filesystem.
     */
    public function execute(string $platform = 'android'): BinaryFileResponse
    {
        $latest = AppVersion::forPlatform($platform)
            ->active()
            ->whereNotNull('apk_path')
            ->orderByDesc('version_code')
            ->first();

        if (! $latest instanceof AppVersion || ! Storage::disk('public')->exists($latest->apk_path)) {
            throw new NotFoundHttpException(__('app_update.file_not_available'));
        }

        $contentType = match ($platform) {
            'windows' => 'application/vnd.microsoft.portable-executable',
            'android' => 'application/vnd.android.package-archive',
            default => 'application/octet-stream',
        };

        // Atomic SQL increment; no lock needed.
        $latest->increment('download_count');

        $appNameSlug = Str::slug((string) config('app.name', 'erp-pos')) ?: 'erp-pos';
        $defaultFilename = $platform === 'windows' ? $appNameSlug.'-Setup.exe' : $appNameSlug.'.apk';

        return response()->download(
            Storage::disk('public')->path($latest->apk_path),
            $latest->apk_filename ?? $defaultFilename,
            [
                'Content-Type' => $contentType,
                'Cache-Control' => 'no-cache, private',
            ],
        );
    }
}
