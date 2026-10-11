<?php

declare(strict_types=1);

namespace App\Actions\AppVersions;

use App\DTOs\AppVersions\CheckUpdateDTO;
use App\Models\AppVersion;
use App\Support\AppReleases\ReleaseFileLocator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serve a release binary (GET /api/v1/app/download-apk?platform=&version_code=).
 *
 * version_code given → exactly that ADVERTISABLE release (the URL check-update returns, and
 * the rollback target); omitted → the latest advertisable release, the same one
 * check-update offers. Only files uploaded through the super-admin flow are served, from the
 * private release disk (legacy public root as a read fallback until app-releases:relocate).
 * There is deliberately no fallback to files in public/: that served the wrong binary and
 * made the response depend on whatever sat on the server's filesystem.
 *
 * The response carries X-Checksum-Sha256 so a client can verify before installing.
 */
final class DownloadLatestApkAction
{
    public function __construct(private readonly CheckAppUpdateAction $checkAppUpdate) {}

    public function execute(CheckUpdateDTO $dto): BinaryFileResponse
    {
        $release = $dto->versionCode === null
            ? $this->checkAppUpdate->execute($dto)['latest']
            : $this->exactRelease($dto);

        $absolutePath = $release instanceof AppVersion ? ReleaseFileLocator::absolutePath($release) : null;

        if (! $release instanceof AppVersion || $absolutePath === null || ! $release->hasValidChecksum()) {
            throw new NotFoundHttpException(__('app_update.file_not_available'));
        }

        // Atomic SQL increment; no lock needed.
        $release->increment('download_count');

        $size = filesize($absolutePath);

        $response = response()->download(
            $absolutePath,
            $release->apk_filename ?? ('release.'.$dto->platform->extension()),
            [
                'Content-Type' => $dto->platform->contentType(),
                'Cache-Control' => 'no-cache, private',
                'X-Content-Type-Options' => 'nosniff',
                'X-Checksum-Sha256' => (string) $release->apk_checksum,
            ],
        );

        // BinaryFileResponse marks itself public by default; a release must not sit in shared caches.
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-cache');

        if ($size !== false) {
            $response->headers->set('Content-Length', (string) $size);
        }

        return $response;
    }

    private function exactRelease(CheckUpdateDTO $dto): ?AppVersion
    {
        return AppVersion::query()
            ->forPlatform($dto->platform->value)
            ->advertisable()
            ->where('version_code', $dto->versionCode)
            ->first();
    }
}
