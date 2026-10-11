<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\AppReleases\ReleaseFileLocator;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Throwable;

/**
 * APP-3 extension (W2 batch 5, CTO Q9): copy release binaries uploaded before the private
 * release disk existed (storage/app/public/apks/**, web-reachable through the /storage
 * symlink) to AppVersion::RELEASE_DISK under the SAME relative path, so app_versions.apk_path
 * stays valid and no database row changes.
 *
 *   php artisan app-releases:relocate                 copy + verify, keep the public copy
 *   php artisan app-releases:relocate --prune-legacy  also delete each public copy whose
 *                                                     private copy has the same SHA-256
 *   php artisan app-releases:relocate --dry-run       list only
 *
 * Two phases (R1) because a deploy runs the plain copy BEFORE the release switch, while the
 * old release (which only knows the public disk) still serves downloads and may be rolled back
 * to. scripts/ops/deploy.sh runs `--prune-legacy` only after the new release passed its
 * post-switch health check. The new code reads the private disk first (ReleaseFileLocator), so
 * serving is correct after either phase.
 *
 * Idempotent: a file already on the private disk with the same SHA-256 is left alone (and, with
 * --prune-legacy, loses its public copy). Each copy's SHA-256 is re-checked on the private disk;
 * a public copy is NEVER deleted unless the private copy's SHA-256 matches it. Same path with
 * DIFFERENT content on both disks is a conflict: nothing is touched and the command fails
 * (exit 1) for a human.
 *
 * Stray binaries directly under public/ (public/*.apk, *.exe, *.ipa) are reported, never
 * deleted: the operator removes them (docs/07-operations/vps-runbook.md).
 */
final class RelocateAppReleasesCommand extends Command
{
    protected $signature = 'app-releases:relocate
        {--prune-legacy : Also delete public copies whose private copy has the same SHA-256}
        {--dry-run : List what would change, change nothing}';

    private const DIRECTORY = 'apks';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('app_releases.relocate.description'));
    }

    public function handle(): int
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            $this->error((string) __('app_releases.relocate.tenant_context'));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $prune = (bool) $this->option('prune-legacy');
        $legacy = ReleaseFileLocator::legacyDisk();
        $private = ReleaseFileLocator::privateDisk();
        $counts = ['copied' => 0, 'verified' => 0, 'pruned' => 0, 'conflicts' => 0, 'failed' => 0];

        if ($dryRun) {
            $this->info((string) __('app_releases.relocate.dry_run'));
        }

        $files = $legacy->allFiles(self::DIRECTORY);

        if ($files === []) {
            $this->info((string) __('app_releases.relocate.nothing_to_do'));
        }

        foreach ($files as $path) {
            $outcome = $this->copy($legacy, $private, $path, $dryRun);
            if ($outcome === null) {
                continue;
            }

            $counts[$outcome]++;

            if ($prune && ($outcome === 'copied' || $outcome === 'verified') && $this->prune($legacy, $private, $path)) {
                $counts['pruned']++;
            }
        }

        if ($prune && ! $dryRun && $files !== [] && $legacy->allFiles(self::DIRECTORY) === []) {
            $legacy->deleteDirectory(self::DIRECTORY);
        }

        $this->warnAboutPublicBinaries();

        $this->info((string) __('app_releases.relocate.summary', $counts));

        return $counts['conflicts'] + $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Copy $path to the private disk and verify it; the public copy is never touched here.
     *
     * @return 'copied'|'verified'|'conflicts'|'failed'|null null = dry run
     */
    private function copy(Filesystem $legacy, Filesystem $private, string $path, bool $dryRun): ?string
    {
        $sourceHash = $this->sha256($legacy, $path);

        if ($private->exists($path)) {
            if ($sourceHash === null || $sourceHash !== $this->sha256($private, $path)) {
                $this->error((string) __('app_releases.relocate.conflict', ['path' => $path]));

                return 'conflicts';
            }

            $this->line((string) __('app_releases.relocate.already_private', ['path' => $path]));

            return $dryRun ? null : 'verified';
        }

        if ($dryRun) {
            $this->line((string) __('app_releases.relocate.would_copy', ['path' => $path]));

            return null;
        }

        try {
            $stream = $legacy->readStream($path);
            $written = is_resource($stream) && $private->writeStream($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (! $written || $sourceHash === null || $this->sha256($private, $path) !== $sourceHash) {
                $private->delete($path);
                $this->error((string) __('app_releases.relocate.failed', ['path' => $path]));

                return 'failed';
            }
        } catch (Throwable $e) {
            $private->delete($path);
            $this->error((string) __('app_releases.relocate.failed', ['path' => $path]));
            report($e);

            return 'failed';
        }

        $this->line((string) __('app_releases.relocate.copied', ['path' => $path]));

        return 'copied';
    }

    /** Delete the public copy of $path, only while the private copy's SHA-256 still matches it. */
    private function prune(Filesystem $legacy, Filesystem $private, string $path): bool
    {
        $publicHash = $this->sha256($legacy, $path);

        if ($publicHash === null || $publicHash !== $this->sha256($private, $path)) {
            $this->error((string) __('app_releases.relocate.prune_skipped', ['path' => $path]));

            return false;
        }

        $legacy->delete($path);
        $this->line((string) __('app_releases.relocate.pruned', ['path' => $path]));

        return true;
    }

    private function sha256(Filesystem $disk, string $path): ?string
    {
        $absolute = $disk->path($path);
        $hash = is_file($absolute) ? hash_file('sha256', $absolute) : false;

        return is_string($hash) ? $hash : null;
    }

    private function warnAboutPublicBinaries(): void
    {
        foreach (glob(public_path('*')) ?: [] as $file) {
            if (! is_file($file) || preg_match('/\.(apk|exe|ipa)$/i', $file) !== 1) {
                continue;
            }

            $this->warn((string) __('app_releases.relocate.public_binary_found', ['file' => basename($file)]));
        }
    }
}
