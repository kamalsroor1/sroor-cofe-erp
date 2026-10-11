<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\AppVersion;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\IsolatesReleaseDisks;
use Tests\TenantTestCase;

/**
 * APP-3 extension (W2 batch 5, CTO Q9): `app-releases:relocate` copies legacy binaries from the
 * public root to the private release disk under the same relative path and keeps the public
 * copy (the old release may still serve it, R1); `--prune-legacy` (run by deploy.sh after the
 * post-switch health check) deletes a public copy only when the private SHA-256 matches. Runs on
 * every deploy, so it must be idempotent and must never lose or corrupt a binary.
 */
final class RelocateAppReleasesCommandTest extends TenantTestCase
{
    use IsolatesReleaseDisks;

    protected function setUp(): void
    {
        parent::setUp();

        AppVersion::query()->delete();
    }

    private function legacyRelease(string $path, string $content, int $code): AppVersion
    {
        Storage::disk(AppVersion::LEGACY_RELEASE_DISK)->put($path, $content);

        return AppVersion::query()->create([
            'platform' => 'android',
            'version_name' => '1.0.'.$code,
            'version_code' => $code,
            'release_notes_ar' => 'قديم',
            'apk_path' => $path,
            'apk_filename' => 'erp-v'.$code.'.apk',
            'apk_size_bytes' => strlen($content),
            'apk_checksum' => hash('sha256', $content),
            'is_active' => true,
            'published_at' => now(),
        ]);
    }

    public function test_a_plain_run_copies_and_verifies_but_keeps_every_public_copy(): void
    {
        $a = $this->legacyRelease('apks/android/01hzzzzzzzzzzzzzzzzzzzzzza.apk', 'APK-A', 3);
        $b = $this->legacyRelease('apks/old-flat-name.apk', 'APK-B', 2);

        $this->artisan('app-releases:relocate')
            ->expectsOutput((string) __('app_releases.relocate.copied', ['path' => (string) $a->apk_path]))
            ->assertExitCode(0);

        $private = Storage::disk(AppVersion::RELEASE_DISK);
        $legacy = Storage::disk(AppVersion::LEGACY_RELEASE_DISK);
        $this->assertSame('APK-A', $private->get((string) $a->apk_path));
        $this->assertSame('APK-B', $private->get((string) $b->apk_path));

        // R1: the old release still serves from (and may be rolled back to) the public disk.
        $this->assertSame('APK-A', $legacy->get((string) $a->apk_path));
        $this->assertSame('APK-B', $legacy->get((string) $b->apk_path));

        // The new code already serves the private copy; no row changed.
        $this->assertSame((string) $a->apk_path, (string) $a->fresh()?->apk_path);
        $this->get('/api/v1/app/download-apk?platform=android&version_code=3')
            ->assertOk()
            ->assertHeader('X-Checksum-Sha256', hash('sha256', 'APK-A'));
    }

    public function test_prune_legacy_deletes_only_verified_public_copies_and_keeps_the_rows_valid(): void
    {
        $a = $this->legacyRelease('apks/android/01hzzzzzzzzzzzzzzzzzzzzzza.apk', 'APK-A', 3);
        $b = $this->legacyRelease('apks/old-flat-name.apk', 'APK-B', 2);

        $this->artisan('app-releases:relocate')->assertExitCode(0);
        $this->artisan('app-releases:relocate', ['--prune-legacy' => true])
            ->expectsOutput((string) __('app_releases.relocate.pruned', ['path' => (string) $a->apk_path]))
            ->assertExitCode(0);

        $private = Storage::disk(AppVersion::RELEASE_DISK);
        $legacy = Storage::disk(AppVersion::LEGACY_RELEASE_DISK);
        $this->assertSame('APK-A', $private->get((string) $a->apk_path));
        $this->assertSame('APK-B', $private->get((string) $b->apk_path));
        $this->assertSame([], $legacy->allFiles());
        $this->assertFalse($legacy->directoryExists('apks'));

        $this->get('/api/v1/app/download-apk?platform=android&version_code=3')
            ->assertOk()
            ->assertHeader('X-Checksum-Sha256', hash('sha256', 'APK-A'));
    }

    public function test_prune_legacy_alone_copies_first_then_prunes(): void
    {
        $this->legacyRelease('apks/android/x.apk', 'APK-X', 1);

        $this->artisan('app-releases:relocate', ['--prune-legacy' => true])->assertExitCode(0);

        $this->assertSame('APK-X', Storage::disk(AppVersion::RELEASE_DISK)->get('apks/android/x.apk'));
        Storage::disk(AppVersion::LEGACY_RELEASE_DISK)->assertMissing('apks/android/x.apk');
    }

    public function test_it_is_idempotent(): void
    {
        $this->legacyRelease('apks/android/x.apk', 'APK-X', 1);

        $this->artisan('app-releases:relocate')->assertExitCode(0);
        $this->artisan('app-releases:relocate')
            ->expectsOutput((string) __('app_releases.relocate.already_private', ['path' => 'apks/android/x.apk']))
            ->assertExitCode(0);
        $this->artisan('app-releases:relocate', ['--prune-legacy' => true])->assertExitCode(0);
        $this->artisan('app-releases:relocate', ['--prune-legacy' => true])
            ->expectsOutput((string) __('app_releases.relocate.nothing_to_do'))
            ->assertExitCode(0);

        Storage::disk(AppVersion::RELEASE_DISK)->assertExists('apks/android/x.apk');
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->legacyRelease('apks/android/x.apk', 'APK-X', 1);

        $this->artisan('app-releases:relocate', ['--dry-run' => true, '--prune-legacy' => true])
            ->expectsOutput((string) __('app_releases.relocate.would_copy', ['path' => 'apks/android/x.apk']))
            ->assertExitCode(0);

        Storage::disk(AppVersion::LEGACY_RELEASE_DISK)->assertExists('apks/android/x.apk');
        Storage::disk(AppVersion::RELEASE_DISK)->assertMissing('apks/android/x.apk');
    }

    public function test_an_identical_private_copy_keeps_the_public_one_until_prune(): void
    {
        $this->legacyRelease('apks/android/x.apk', 'APK-X', 1);
        Storage::disk(AppVersion::RELEASE_DISK)->put('apks/android/x.apk', 'APK-X');

        $this->artisan('app-releases:relocate')->assertExitCode(0);
        Storage::disk(AppVersion::LEGACY_RELEASE_DISK)->assertExists('apks/android/x.apk');

        $this->artisan('app-releases:relocate', ['--prune-legacy' => true])->assertExitCode(0);
        Storage::disk(AppVersion::LEGACY_RELEASE_DISK)->assertMissing('apks/android/x.apk');
        $this->assertSame('APK-X', Storage::disk(AppVersion::RELEASE_DISK)->get('apks/android/x.apk'));
    }

    public function test_a_conflicting_private_copy_fails_and_touches_nothing_even_when_pruning(): void
    {
        $this->legacyRelease('apks/android/x.apk', 'APK-PUBLIC', 1);
        Storage::disk(AppVersion::RELEASE_DISK)->put('apks/android/x.apk', 'APK-PRIVATE-DIFFERENT');

        foreach ([[], ['--prune-legacy' => true]] as $options) {
            $this->artisan('app-releases:relocate', $options)
                ->expectsOutput((string) __('app_releases.relocate.conflict', ['path' => 'apks/android/x.apk']))
                ->assertExitCode(1);
        }

        $this->assertSame('APK-PUBLIC', Storage::disk(AppVersion::LEGACY_RELEASE_DISK)->get('apks/android/x.apk'));
        $this->assertSame('APK-PRIVATE-DIFFERENT', Storage::disk(AppVersion::RELEASE_DISK)->get('apks/android/x.apk'));
    }

    public function test_nothing_to_do_is_a_success(): void
    {
        $this->artisan('app-releases:relocate')
            ->expectsOutput((string) __('app_releases.relocate.nothing_to_do'))
            ->assertExitCode(0);
    }

    public function test_it_refuses_to_run_inside_a_tenant(): void
    {
        $this->legacyRelease('apks/android/x.apk', 'APK-X', 1);
        $tenant = $this->createTenant();

        tenancy()->initialize($tenant);
        try {
            $this->artisan('app-releases:relocate')->assertExitCode(1);
        } finally {
            tenancy()->end();
        }

        Storage::disk(AppVersion::LEGACY_RELEASE_DISK)->assertExists('apks/android/x.apk');
    }
}
