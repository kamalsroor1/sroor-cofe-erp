<?php

declare(strict_types=1);

namespace App\Support\AppReleases;

use App\Enums\AppPlatform;
use finfo;
use ZipArchive;

/**
 * APP-3 extension (W2 batch 5): structural checks of an uploaded release binary before it is
 * stored and advertised to every installed app.
 *
 * Android (.apk):
 *   - libmagic type is a zip / java-archive / android-package (or generic octet-stream);
 *   - starts with the local file header `PK\x03\x04` and opens with ZipArchive;
 *   - no entry name is absolute, a drive path, contains a NUL or a `..` segment;
 *   - contains `AndroidManifest.xml` and at least one root `classes*.dex`;
 *   - carries an APK Signing Block ("APK Sig Block 42", sizes consistent) holding a v2 or
 *     v3 signature pair (CTO decision Q8: the block's presence is required, the signer
 *     certificate itself is NOT parsed server side; Android checks it at install).
 *
 * Windows (.exe):
 *   - libmagic type is a DOS/PE executable (or generic octet-stream);
 *   - `MZ` header, e_lfanew within the file, `PE\0\0` signature;
 *   - Machine is x64 (0x8664), x86 (0x014c) or ARM64 (0xAA64);
 *   - IMAGE_FILE_EXECUTABLE_IMAGE set and IMAGE_FILE_DLL not set;
 *   - valid PE32 / PE32+ optional header. The security (Authenticode) data directory is only
 *     RECORDED (authenticodePresent), never enforced until a certificate exists (Q11).
 *
 * The file is streamed (fopen/fseek); nothing is extracted, executed or fully loaded into memory.
 */
final class ReleaseBinaryInspector
{
    /** @var array<string, list<string>> */
    private const ALLOWED_MIMES = [
        'android' => [
            'application/zip',
            'application/java-archive',
            'application/x-java-archive',
            'application/vnd.android.package-archive',
            'application/octet-stream',
        ],
        'windows' => [
            'application/x-dosexec',
            'application/vnd.microsoft.portable-executable',
            'application/x-msdownload',
            'application/octet-stream',
        ],
    ];

    private const APK_SIG_BLOCK_MAGIC = 'APK Sig Block 42';

    private const APK_SIGNATURE_SCHEME_V2_ID = 0x7109871A;

    private const APK_SIGNATURE_SCHEME_V3_ID = 0xF05368C0;

    private const APK_SIGNATURE_SCHEME_V31_ID = 0x1B93AD61;

    private const EOCD_SIGNATURE = "PK\x05\x06";

    /** EOCD (22 bytes) + the longest possible zip comment (65535). */
    private const EOCD_SEARCH_WINDOW = 22 + 65535;

    private const MACHINE_I386 = 0x014C;

    private const MACHINE_AMD64 = 0x8664;

    private const MACHINE_ARM64 = 0xAA64;

    private const IMAGE_FILE_EXECUTABLE_IMAGE = 0x0002;

    private const IMAGE_FILE_DLL = 0x2000;

    private const PE32_MAGIC = 0x10B;

    private const PE32_PLUS_MAGIC = 0x20B;

    /**
     * N5: upper bound of the APK Signing Block read into memory. Real blocks are a few KB to a
     * few hundred KB (certificate chains, padding to 4096); anything larger is refused.
     */
    public const MAX_SIGNING_BLOCK_BYTES = 8 * 1024 * 1024;

    /** Upper bound of zip entries walked (a real APK has a few thousand). */
    private const MAX_ZIP_ENTRIES = 200_000;

    public function inspect(string $path, AppPlatform $platform): ReleaseBinaryReport
    {
        $mime = $this->mime($path);

        if (! is_file($path) || ! is_readable($path) || (int) filesize($path) <= 0) {
            return ReleaseBinaryReport::invalid(ReleaseBinaryReport::UNREADABLE, $mime);
        }

        if (! in_array($mime, self::ALLOWED_MIMES[$platform->value], true)) {
            return ReleaseBinaryReport::invalid(ReleaseBinaryReport::MIME_NOT_ALLOWED, $mime);
        }

        return match ($platform) {
            AppPlatform::Android => $this->inspectApk($path, $mime),
            AppPlatform::Windows => $this->inspectExe($path, $mime),
        };
    }

    private function mime(string $path): string
    {
        if (! is_file($path)) {
            return 'application/octet-stream';
        }

        $detected = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        return is_string($detected) && $detected !== '' ? $detected : 'application/octet-stream';
    }

    // ---------------------------------------------------------------- Android

    private function inspectApk(string $path, string $mime): ReleaseBinaryReport
    {
        if ($this->readAt($path, 0, 4) !== "PK\x03\x04") {
            return ReleaseBinaryReport::invalid(ReleaseBinaryReport::ZIP_MAGIC, $mime);
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return ReleaseBinaryReport::invalid(ReleaseBinaryReport::ZIP_UNREADABLE, $mime);
        }

        try {
            $entryFailure = $this->apkEntriesFailure($zip);
        } finally {
            $zip->close();
        }

        if ($entryFailure !== null) {
            return ReleaseBinaryReport::invalid($entryFailure, $mime);
        }

        $signingFailure = $this->signingBlockFailure($path);
        if ($signingFailure !== null) {
            return ReleaseBinaryReport::invalid($signingFailure, $mime);
        }

        return ReleaseBinaryReport::valid($mime, apkSigningBlock: true);
    }

    private function apkEntriesFailure(ZipArchive $zip): ?string
    {
        if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ZIP_ENTRIES) {
            return ReleaseBinaryReport::ZIP_UNREADABLE;
        }

        $manifest = false;
        $dex = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i, ZipArchive::FL_UNCHANGED);

            if (! is_string($name) || ! $this->isSafeEntryName($name)) {
                return ReleaseBinaryReport::UNSAFE_ENTRY;
            }

            if ($name === 'AndroidManifest.xml') {
                $manifest = true;
            } elseif (preg_match('/^classes\d*\.dex$/', $name) === 1) {
                $dex = true;
            }
        }

        if (! $manifest) {
            return ReleaseBinaryReport::MANIFEST_MISSING;
        }

        return $dex ? null : ReleaseBinaryReport::DEX_MISSING;
    }

    private function isSafeEntryName(string $name): bool
    {
        if ($name === '' || str_contains($name, "\0")) {
            return false;
        }

        $normalized = str_replace('\\', '/', $name);

        if (str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:/', $normalized) === 1) {
            return false;
        }

        return ! in_array('..', explode('/', $normalized), true);
    }

    /**
     * APK Signing Block layout, immediately before the central directory:
     *   uint64 size | pairs (uint64 len, uint32 id, value) ... | uint64 size | "APK Sig Block 42"
     */
    private function signingBlockFailure(string $path): ?string
    {
        $missing = ReleaseBinaryReport::SIGNING_BLOCK_MISSING;
        $centralDirectoryOffset = $this->centralDirectoryOffset($path);

        if ($centralDirectoryOffset === null || $centralDirectoryOffset < 32) {
            return $missing;
        }

        $footer = $this->readAt($path, $centralDirectoryOffset - 24, 24);
        if ($footer === null || substr($footer, 8) !== self::APK_SIG_BLOCK_MAGIC) {
            return $missing;
        }

        $blockSize = $this->uint64(substr($footer, 0, 8));
        // size counts everything after the leading size field: pairs + trailing size + magic.
        if ($blockSize === null || $blockSize < 24 || $blockSize > $centralDirectoryOffset - 8) {
            return $missing;
        }

        // N5: never read an attacker-sized block into memory.
        if ($blockSize > self::MAX_SIGNING_BLOCK_BYTES) {
            return ReleaseBinaryReport::SIGNING_BLOCK_TOO_LARGE;
        }

        $blockStart = $centralDirectoryOffset - $blockSize - 8;
        $leadingSize = $this->uint64((string) $this->readAt($path, $blockStart, 8));
        if ($leadingSize !== $blockSize) {
            return $missing;
        }

        $pairs = $this->readAt($path, $blockStart + 8, $blockSize - 24);

        return $pairs !== null && $this->hasSignatureSchemePair($pairs) ? null : $missing;
    }

    private function hasSignatureSchemePair(string $pairs): bool
    {
        $offset = 0;
        $length = strlen($pairs);

        while ($offset + 12 <= $length) {
            $pairLength = $this->uint64(substr($pairs, $offset, 8));
            if ($pairLength === null || $pairLength < 4 || $offset + 8 + $pairLength > $length) {
                return false;
            }

            $id = unpack('V', substr($pairs, $offset + 8, 4));
            $id = is_array($id) ? (int) $id[1] : -1;

            if (in_array($id, [self::APK_SIGNATURE_SCHEME_V2_ID, self::APK_SIGNATURE_SCHEME_V3_ID, self::APK_SIGNATURE_SCHEME_V31_ID], true)) {
                return true;
            }

            $offset += 8 + $pairLength;
        }

        return false;
    }

    /** Offset of the central directory from the End Of Central Directory record, or null. */
    private function centralDirectoryOffset(string $path): ?int
    {
        $size = (int) filesize($path);
        if ($size < 22) {
            return null;
        }

        $window = min($size, self::EOCD_SEARCH_WINDOW);
        $tail = $this->readAt($path, $size - $window, $window);
        if ($tail === null) {
            return null;
        }

        $position = strrpos($tail, self::EOCD_SIGNATURE);
        if ($position === false || $position + 22 > strlen($tail)) {
            return null;
        }

        $fields = unpack('Voffset', substr($tail, $position + 16, 4));
        $offset = is_array($fields) ? (int) $fields['offset'] : 0xFFFFFFFF;

        // 0xFFFFFFFF = zip64; a release is capped far below 4 GB, so treat it as malformed.
        return $offset === 0xFFFFFFFF || $offset >= $size ? null : $offset;
    }

    // ---------------------------------------------------------------- Windows

    private function inspectExe(string $path, string $mime): ReleaseBinaryReport
    {
        $size = (int) filesize($path);
        $dos = $this->readAt($path, 0, 64);

        if ($dos === null || ! str_starts_with($dos, 'MZ')) {
            return ReleaseBinaryReport::invalid(ReleaseBinaryReport::MZ_MAGIC, $mime);
        }

        $lfanew = $this->uint32(substr($dos, 0x3C, 4));
        // PE signature (4) + COFF header (20) must fit after e_lfanew.
        if ($lfanew === null || $lfanew < 0x40 || $lfanew + 24 > $size) {
            return ReleaseBinaryReport::invalid(ReleaseBinaryReport::PE_HEADER, $mime);
        }

        $coff = $this->readAt($path, $lfanew, 24);
        if ($coff === null || substr($coff, 0, 4) !== "PE\0\0") {
            return ReleaseBinaryReport::invalid(ReleaseBinaryReport::PE_HEADER, $mime);
        }

        $machine = $this->uint16(substr($coff, 4, 2));
        if (! in_array($machine, [self::MACHINE_AMD64, self::MACHINE_I386, self::MACHINE_ARM64], true)) {
            return ReleaseBinaryReport::invalid(ReleaseBinaryReport::MACHINE_NOT_ALLOWED, $mime);
        }

        $characteristics = (int) $this->uint16(substr($coff, 22, 2));
        if (($characteristics & self::IMAGE_FILE_DLL) !== 0) {
            return ReleaseBinaryReport::invalid(ReleaseBinaryReport::IS_DLL, $mime);
        }
        if (($characteristics & self::IMAGE_FILE_EXECUTABLE_IMAGE) === 0) {
            return ReleaseBinaryReport::invalid(ReleaseBinaryReport::NOT_EXECUTABLE, $mime);
        }

        $authenticode = $this->authenticodePresent($path, $lfanew + 24, (int) $this->uint16(substr($coff, 20, 2)), $size);
        if ($authenticode === null) {
            return ReleaseBinaryReport::invalid(ReleaseBinaryReport::PE_HEADER, $mime);
        }

        return ReleaseBinaryReport::valid($mime, authenticodePresent: $authenticode, machine: $machine);
    }

    /**
     * Reads the optional header's IMAGE_DIRECTORY_ENTRY_SECURITY (index 4). Null when the
     * optional header itself is malformed; true when a certificate table is present and
     * inside the file. Its signature is NOT verified here (Q11).
     */
    private function authenticodePresent(string $path, int $optionalHeaderOffset, int $optionalHeaderSize, int $fileSize): ?bool
    {
        if ($optionalHeaderSize < 96 || $optionalHeaderOffset + $optionalHeaderSize > $fileSize) {
            return null;
        }

        $optional = $this->readAt($path, $optionalHeaderOffset, $optionalHeaderSize);
        if ($optional === null) {
            return null;
        }

        [$countOffset, $directoriesOffset] = match ($this->uint16(substr($optional, 0, 2))) {
            self::PE32_MAGIC => [92, 96],
            self::PE32_PLUS_MAGIC => [108, 112],
            default => [null, null],
        };

        if ($countOffset === null || $directoriesOffset === null || $countOffset + 4 > $optionalHeaderSize) {
            return null;
        }

        $directoryCount = (int) $this->uint32(substr($optional, $countOffset, 4));
        $securityEntry = $directoriesOffset + (4 * 8);

        if ($directoryCount <= 4 || $securityEntry + 8 > $optionalHeaderSize) {
            return false;
        }

        // For the security directory the "VirtualAddress" is a raw file offset.
        $tableOffset = (int) $this->uint32(substr($optional, $securityEntry, 4));
        $tableSize = (int) $this->uint32(substr($optional, $securityEntry + 4, 4));

        return $tableOffset > 0 && $tableSize > 0 && $tableOffset + $tableSize <= $fileSize;
    }

    // ---------------------------------------------------------------- bytes

    private function readAt(string $path, int $offset, int $length): ?string
    {
        if ($offset < 0 || $length < 0) {
            return null;
        }
        if ($length === 0) {
            return '';
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            if (fseek($handle, $offset) !== 0) {
                return null;
            }

            $buffer = '';
            while (strlen($buffer) < $length && ! feof($handle)) {
                $chunk = fread($handle, max(1, $length - strlen($buffer)));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $buffer .= $chunk;
            }

            return strlen($buffer) === $length ? $buffer : null;
        } finally {
            fclose($handle);
        }
    }

    private function uint16(string $bytes): ?int
    {
        $value = strlen($bytes) === 2 ? unpack('v', $bytes) : false;

        return is_array($value) ? (int) $value[1] : null;
    }

    private function uint32(string $bytes): ?int
    {
        $value = strlen($bytes) === 4 ? unpack('V', $bytes) : false;

        return is_array($value) ? (int) $value[1] : null;
    }

    /** Little-endian uint64; null when it does not fit a signed PHP int. */
    private function uint64(string $bytes): ?int
    {
        $value = strlen($bytes) === 8 ? unpack('P', $bytes) : false;
        if (! is_array($value)) {
            return null;
        }

        $int = (int) $value[1];

        return $int < 0 ? null : $int;
    }
}
