<?php

declare(strict_types=1);

namespace Tests\Unit\Support\AppReleases;

use App\Enums\AppPlatform;
use App\Support\AppReleases\ReleaseBinaryInspector;
use App\Support\AppReleases\ReleaseBinaryReport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeReleaseBinaries;

/**
 * APP-3 extension (W2 batch 5): every structural check of ReleaseBinaryInspector, one broken
 * property per case (FakeReleaseBinaries).
 */
final class ReleaseBinaryInspectorTest extends TestCase
{
    private ReleaseBinaryInspector $inspector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inspector = new ReleaseBinaryInspector;
    }

    protected function tearDown(): void
    {
        FakeReleaseBinaries::cleanup();
        parent::tearDown();
    }

    public function test_signed_v2_apk_is_valid_and_records_the_signing_block(): void
    {
        $report = $this->inspector->inspect(FakeReleaseBinaries::apk(), AppPlatform::Android);

        $this->assertTrue($report->isValid(), (string) $report->failure);
        $this->assertTrue($report->apkSigningBlock);
        $this->assertFalse($report->authenticodePresent);
        $this->assertContains($report->mime, [
            'application/zip', 'application/java-archive', 'application/x-java-archive',
            'application/vnd.android.package-archive', 'application/octet-stream',
        ]);
    }

    public function test_signed_v3_apk_is_valid(): void
    {
        $this->assertTrue($this->inspector->inspect(FakeReleaseBinaries::apk(['signing' => 'v3']), AppPlatform::Android)->isValid());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function brokenApks(): array
    {
        return [
            'no signing block (v1 / unsigned)' => [['signing' => 'none'], ReleaseBinaryReport::SIGNING_BLOCK_MISSING],
            'wrong block magic' => [['signing' => 'bad_magic'], ReleaseBinaryReport::SIGNING_BLOCK_MISSING],
            'block without a v2/v3 pair' => [['signing' => 'unknown_pair'], ReleaseBinaryReport::SIGNING_BLOCK_MISSING],
            'block sizes disagree' => [['signing' => 'bad_size'], ReleaseBinaryReport::SIGNING_BLOCK_MISSING],
            'signing block over the 8 MB cap (N5)' => [['signing' => 'oversized'], ReleaseBinaryReport::SIGNING_BLOCK_TOO_LARGE],
            'no AndroidManifest.xml' => [['manifest' => false], ReleaseBinaryReport::MANIFEST_MISSING],
            'no classes.dex' => [['dex' => false], ReleaseBinaryReport::DEX_MISSING],
            'zip-slip entry' => [['entries' => ['../../evil.so' => 'x']], ReleaseBinaryReport::UNSAFE_ENTRY],
            'nested traversal entry' => [['entries' => ['lib/../../evil.so' => 'x']], ReleaseBinaryReport::UNSAFE_ENTRY],
            'absolute entry' => [['entries' => ['/etc/cron.d/evil' => 'x']], ReleaseBinaryReport::UNSAFE_ENTRY],
            'windows drive entry' => [['entries' => ['C:/evil.dll' => 'x']], ReleaseBinaryReport::UNSAFE_ENTRY],
            'backslash traversal entry' => [['entries' => ['..\\evil.so' => 'x']], ReleaseBinaryReport::UNSAFE_ENTRY],
        ];
    }

    /**
     * @param  array{signing?: 'v2'|'v3'|'none'|'bad_magic'|'unknown_pair'|'bad_size'|'oversized', manifest?: bool, dex?: bool, entries?: array<string, string>}  $options
     */
    #[DataProvider('brokenApks')]
    public function test_broken_apks_are_rejected_with_the_reason(array $options, string $failure): void
    {
        $report = $this->inspector->inspect(FakeReleaseBinaries::apk($options), AppPlatform::Android);

        $this->assertFalse($report->isValid());
        $this->assertSame($failure, $report->failure);
        $this->assertFalse($report->apkSigningBlock);
    }

    public function test_a_plain_text_file_named_apk_is_rejected_by_content_type(): void
    {
        $path = $this->tempFile('this is not an android package');

        $this->assertSame(ReleaseBinaryReport::MIME_NOT_ALLOWED, $this->inspector->inspect($path, AppPlatform::Android)->failure);
    }

    public function test_octet_stream_without_zip_magic_is_rejected(): void
    {
        $path = $this->tempFile(random_bytes(4096));

        $failure = $this->inspector->inspect($path, AppPlatform::Android)->failure;

        // Random bytes are octet-stream (allowed type) but have no PK\x03\x04 header.
        $this->assertContains($failure, [ReleaseBinaryReport::ZIP_MAGIC, ReleaseBinaryReport::MIME_NOT_ALLOWED]);
    }

    public function test_missing_or_empty_file_is_unreadable(): void
    {
        $this->assertSame(ReleaseBinaryReport::UNREADABLE, $this->inspector->inspect(sys_get_temp_dir().'/does-not-exist-'.bin2hex(random_bytes(4)).'.apk', AppPlatform::Android)->failure);
        $this->assertSame(ReleaseBinaryReport::UNREADABLE, $this->inspector->inspect($this->tempFile(''), AppPlatform::Windows)->failure);
    }

    public function test_an_exe_is_not_an_apk_and_an_apk_is_not_an_exe(): void
    {
        $this->assertFalse($this->inspector->inspect(FakeReleaseBinaries::exe(), AppPlatform::Android)->isValid());
        $this->assertFalse($this->inspector->inspect(FakeReleaseBinaries::apk(), AppPlatform::Windows)->isValid());
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function allowedMachines(): array
    {
        return ['x64' => [0x8664], 'x86' => [0x014C], 'arm64' => [0xAA64]];
    }

    #[DataProvider('allowedMachines')]
    public function test_windows_executables_for_supported_machines_are_valid(int $machine): void
    {
        $report = $this->inspector->inspect(FakeReleaseBinaries::exe(['machine' => $machine]), AppPlatform::Windows);

        $this->assertTrue($report->isValid(), (string) $report->failure);
        $this->assertSame($machine, $report->machine);
        $this->assertFalse($report->apkSigningBlock);
    }

    public function test_authenticode_table_is_recorded_not_required(): void
    {
        $unsigned = $this->inspector->inspect(FakeReleaseBinaries::exe(), AppPlatform::Windows);
        $signed = $this->inspector->inspect(FakeReleaseBinaries::exe(['authenticode' => true]), AppPlatform::Windows);

        $this->assertTrue($unsigned->isValid());
        $this->assertFalse($unsigned->authenticodePresent);
        $this->assertTrue($signed->isValid());
        $this->assertTrue($signed->authenticodePresent);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function brokenExes(): array
    {
        return [
            'DLL' => [['dll' => true], ReleaseBinaryReport::IS_DLL],
            'not executable image' => [['executable' => false], ReleaseBinaryReport::NOT_EXECUTABLE],
            'unsupported machine (ARM thumb)' => [['machine' => 0x01C4], ReleaseBinaryReport::MACHINE_NOT_ALLOWED],
            'no PE signature' => [['pe' => false], ReleaseBinaryReport::PE_HEADER],
            'e_lfanew past end of file' => [['lfanew' => 0x7FFFFFF0], ReleaseBinaryReport::PE_HEADER],
            'e_lfanew inside DOS header' => [['lfanew' => 0x10], ReleaseBinaryReport::PE_HEADER],
        ];
    }

    /**
     * @param  array{machine?: int, dll?: bool, executable?: bool, authenticode?: bool, mz?: bool, pe?: bool, lfanew?: int}  $options
     */
    #[DataProvider('brokenExes')]
    public function test_broken_executables_are_rejected_with_the_reason(array $options, string $failure): void
    {
        $this->assertSame($failure, $this->inspector->inspect(FakeReleaseBinaries::exe($options), AppPlatform::Windows)->failure);
    }

    public function test_missing_mz_header_is_rejected(): void
    {
        $failure = $this->inspector->inspect(FakeReleaseBinaries::exe(['mz' => false]), AppPlatform::Windows)->failure;

        // Without MZ libmagic may already call it octet-stream (allowed) → MZ check; either way rejected.
        $this->assertContains($failure, [ReleaseBinaryReport::MZ_MAGIC, ReleaseBinaryReport::MIME_NOT_ALLOWED]);
    }

    public function test_every_failure_code_is_translated_in_both_locales(): void
    {
        $base = dirname(__DIR__, 4).'/lang';
        foreach (['ar', 'en'] as $locale) {
            $lang = require $base.'/'.$locale.'/app_releases.php';
            foreach (ReleaseBinaryReport::FAILURES as $code) {
                $this->assertArrayHasKey($code, $lang['binary_errors'], $locale.': '.$code);
            }
        }
    }

    private function tempFile(string $content): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'relbin');
        file_put_contents($path, $content);
        register_shutdown_function(static function () use ($path): void {
            if (is_file($path)) {
                @unlink($path);
            }
        });

        return $path;
    }
}
