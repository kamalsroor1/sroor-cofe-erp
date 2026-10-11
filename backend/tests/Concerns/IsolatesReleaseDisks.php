<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\AppVersion;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Release-binary tests get FRESH, per-test fake roots for the release disks (private, legacy and
 * `public`), instead of Storage::fake()'s fixed storage/framework/testing/disks/<disk>.
 *
 * Why: Storage::fake() empties the fixed root with a silent best-effort delete. On Windows a
 * freshly written .apk/.exe is often still held (antivirus scan, a handle not yet collected), the
 * delete fails silently, and the next test finds a previous test's binary on "its" empty disk
 * (AppReleaseUploadValidationTest::assertNothingStored flaked in the full suite). A unique root
 * per test cannot inherit anything; the roots are removed in tearDown (best effort) and stale
 * ones from earlier runs are swept on the next setUp.
 *
 * Usage: `use IsolatesReleaseDisks;` — Laravel calls setUp/tearDown of the trait automatically.
 */
trait IsolatesReleaseDisks
{
    /** @var list<string> */
    private array $isolatedReleaseDiskRoots = [];

    protected function setUpIsolatesReleaseDisks(): void
    {
        $this->sweepStaleReleaseDiskRoots();

        $run = bin2hex(random_bytes(6));
        foreach ([AppVersion::RELEASE_DISK, AppVersion::LEGACY_RELEASE_DISK, 'public'] as $disk) {
            $root = self::releaseDiskBase().DIRECTORY_SEPARATOR.$run.DIRECTORY_SEPARATOR.$disk;
            (new Filesystem)->ensureDirectoryExists($root);
            Storage::set($disk, Storage::createLocalDriver([
                'root' => $root,
                'throw' => false,
                'serve' => false,
            ]));
            $this->isolatedReleaseDiskRoots[] = dirname($root);
        }
    }

    protected function tearDownIsolatesReleaseDisks(): void
    {
        gc_collect_cycles();
        foreach (array_unique($this->isolatedReleaseDiskRoots) as $root) {
            (new Filesystem)->deleteDirectory($root);
        }
        $this->isolatedReleaseDiskRoots = [];
    }

    private static function releaseDiskBase(): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'sroor-release-disks';
    }

    /** Roots left behind by a delete that failed earlier (older than an hour). */
    private function sweepStaleReleaseDiskRoots(): void
    {
        $filesystem = new Filesystem;
        if (! is_dir(self::releaseDiskBase())) {
            return;
        }

        foreach ($filesystem->directories(self::releaseDiskBase()) as $directory) {
            if (@filemtime($directory) < time() - 3600) {
                $filesystem->deleteDirectory($directory);
            }
        }
    }
}
