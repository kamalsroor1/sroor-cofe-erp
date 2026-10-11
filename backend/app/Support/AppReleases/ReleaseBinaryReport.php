<?php

declare(strict_types=1);

namespace App\Support\AppReleases;

/**
 * Result of ReleaseBinaryInspector::inspect(). Immutable.
 *
 * `failure` is a stable machine code (null when valid); its user-facing text is
 * `app_releases.binary_errors.<failure>`.
 */
final readonly class ReleaseBinaryReport
{
    public const MIME_NOT_ALLOWED = 'mime_not_allowed';

    public const UNREADABLE = 'unreadable';

    public const ZIP_MAGIC = 'zip_magic';

    public const ZIP_UNREADABLE = 'zip_unreadable';

    public const UNSAFE_ENTRY = 'unsafe_entry';

    public const MANIFEST_MISSING = 'manifest_missing';

    public const DEX_MISSING = 'dex_missing';

    public const SIGNING_BLOCK_MISSING = 'signing_block_missing';

    /** N5: the APK Signing Block is larger than ReleaseBinaryInspector::MAX_SIGNING_BLOCK_BYTES. */
    public const SIGNING_BLOCK_TOO_LARGE = 'signing_block_too_large';

    public const MZ_MAGIC = 'mz_magic';

    public const PE_HEADER = 'pe_header';

    public const MACHINE_NOT_ALLOWED = 'machine_not_allowed';

    public const NOT_EXECUTABLE = 'not_executable';

    public const IS_DLL = 'is_dll';

    /** Every failure code, for lang parity tests. */
    public const FAILURES = [
        self::MIME_NOT_ALLOWED,
        self::UNREADABLE,
        self::ZIP_MAGIC,
        self::ZIP_UNREADABLE,
        self::UNSAFE_ENTRY,
        self::MANIFEST_MISSING,
        self::DEX_MISSING,
        self::SIGNING_BLOCK_MISSING,
        self::SIGNING_BLOCK_TOO_LARGE,
        self::MZ_MAGIC,
        self::PE_HEADER,
        self::MACHINE_NOT_ALLOWED,
        self::NOT_EXECUTABLE,
        self::IS_DLL,
    ];

    private function __construct(
        public ?string $failure,
        public string $mime,
        public bool $apkSigningBlock,
        public bool $authenticodePresent,
        public ?int $machine,
    ) {}

    public static function valid(string $mime, bool $apkSigningBlock = false, bool $authenticodePresent = false, ?int $machine = null): self
    {
        return new self(null, $mime, $apkSigningBlock, $authenticodePresent, $machine);
    }

    public static function invalid(string $failure, string $mime): self
    {
        return new self($failure, $mime, false, false, null);
    }

    public function isValid(): bool
    {
        return $this->failure === null;
    }
}
