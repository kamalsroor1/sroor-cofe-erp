<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

/**
 * Minimal, structurally valid release binaries for tests (APP-3 extension, W2 batch 5).
 *
 *  - apk(): a ZIP with AndroidManifest.xml + classes.dex and an APK Signing Block
 *    ("APK Sig Block 42", one v2 or v3 pair) inserted right before the central directory,
 *    with the EOCD central-directory offset fixed up, exactly where Android puts it.
 *  - exe(): MZ header → PE\0\0 → COFF header → PE32+ optional header with 16 data
 *    directories (optionally a certificate table, i.e. an Authenticode-shaped file).
 *
 * Every option breaks ONE property so ReleaseBinaryInspector tests can target each check.
 * Files live in the system temp dir; call cleanup() in tearDown().
 */
final class FakeReleaseBinaries
{
    public const V2_ID = 0x7109871A;

    public const V3_ID = 0xF05368C0;

    /** @var list<string> */
    private static array $files = [];

    /**
     * @param  array{signing?: 'v2'|'v3'|'none'|'bad_magic'|'unknown_pair'|'bad_size'|'oversized', manifest?: bool, dex?: bool, entries?: array<string, string>}  $options
     */
    public static function apk(array $options = []): string
    {
        $path = self::tempPath('apk');
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create fake APK.');
        }

        if ($options['manifest'] ?? true) {
            $zip->addFromString('AndroidManifest.xml', "\x03\x00\x08\x00fake-binary-manifest");
        }
        if ($options['dex'] ?? true) {
            $zip->addFromString('classes.dex', "dex\n035\0".str_repeat("\0", 32));
        }
        $zip->addFromString('res/values/strings.xml', '<resources/>');
        foreach ($options['entries'] ?? [] as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        $signing = $options['signing'] ?? 'v2';
        if ($signing !== 'none') {
            self::insertSigningBlock($path, $signing);
        }

        return $path;
    }

    /**
     * @param  array{machine?: int, dll?: bool, executable?: bool, authenticode?: bool, mz?: bool, pe?: bool, lfanew?: int}  $options
     */
    public static function exe(array $options = []): string
    {
        $lfanew = $options['lfanew'] ?? 0x80;
        $dos = ($options['mz'] ?? true ? 'MZ' : 'ZM').str_repeat("\0", 0x3C - 2).pack('V', $lfanew);
        $dos = str_pad($dos, 0x80, "\0");

        $characteristics = 0x0020; // LARGE_ADDRESS_AWARE
        if ($options['executable'] ?? true) {
            $characteristics |= 0x0002;
        }
        if ($options['dll'] ?? false) {
            $characteristics |= 0x2000;
        }

        $optionalSize = 240; // PE32+: 112 + 16 directories * 8
        $coff = ($options['pe'] ?? true ? "PE\0\0" : "XX\0\0")
            .pack('v', $options['machine'] ?? 0x8664) // Machine
            .pack('v', 0)                              // NumberOfSections
            .pack('V', 0)                              // TimeDateStamp
            .pack('V', 0)                              // PointerToSymbolTable
            .pack('V', 0)                              // NumberOfSymbols
            .pack('v', $optionalSize)                  // SizeOfOptionalHeader
            .pack('v', $characteristics);              // Characteristics

        $body = str_repeat("\x90", 512);
        $headerLength = strlen($dos) + strlen($coff) + $optionalSize;
        $certificate = pack('V', 16).pack('v', 0x0200).pack('v', 0x0002).str_repeat("\x30", 8);
        $certificateOffset = ($options['authenticode'] ?? false) ? $headerLength + strlen($body) : 0;
        $certificateSize = ($options['authenticode'] ?? false) ? strlen($certificate) : 0;

        $optional = pack('v', 0x20B).str_repeat("\0", 106).pack('V', 16);
        $directories = '';
        for ($i = 0; $i < 16; $i++) {
            $directories .= $i === 4 ? pack('V', $certificateOffset).pack('V', $certificateSize) : pack('V', 0).pack('V', 0);
        }
        $optional .= $directories;

        $bytes = $dos.$coff.$optional.$body.($certificateSize > 0 ? $certificate : '');
        $path = self::tempPath('exe');
        file_put_contents($path, $bytes);

        return $path;
    }

    /** Any other content (e.g. a text file pretending to be a release). */
    public static function plain(string $content, string $extension = 'bin'): string
    {
        $path = self::tempPath($extension);
        file_put_contents($path, $content);

        return $path;
    }

    /** Wrap $path as an upload with the given client file name (test mode, no is_uploaded_file()). */
    public static function upload(string $path, string $clientName): UploadedFile
    {
        return new UploadedFile($path, $clientName, null, null, true);
    }

    public static function cleanup(): void
    {
        foreach (self::$files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        self::$files = [];
    }

    private static function insertSigningBlock(string $path, string $variant): void
    {
        $bytes = (string) file_get_contents($path);
        $eocd = strrpos($bytes, "PK\x05\x06");
        if ($eocd === false) {
            throw new RuntimeException('Fake APK has no EOCD.');
        }

        $offset = unpack('V', substr($bytes, $eocd + 16, 4));
        $centralDirectory = is_array($offset) ? (int) $offset[1] : 0;

        $id = match ($variant) {
            'v3' => self::V3_ID,
            'unknown_pair' => 0x42726577,
            default => self::V2_ID,
        };
        $value = 'fake-signed-data-not-a-real-certificate';
        $pairs = pack('P', 4 + strlen($value)).pack('V', $id).$value;
        if ($variant === 'oversized') {
            // A valid v2 pair after an unknown pair one byte over the inspector's cap (N5).
            $padding = str_repeat("\0", 8 * 1024 * 1024 + 1);
            $pairs = pack('P', 4 + strlen($padding)).pack('V', 0x42726577).$padding.$pairs;
        }
        $size = strlen($pairs) + 8 + 16;
        $leadingSize = $variant === 'bad_size' ? $size + 8 : $size;
        $magic = $variant === 'bad_magic' ? 'APK Sig Block 41' : 'APK Sig Block 42';
        $block = pack('P', $leadingSize).$pairs.pack('P', $size).$magic;

        $patched = substr($bytes, 0, $centralDirectory).$block.substr($bytes, $centralDirectory);
        $newEocd = $eocd + strlen($block);
        $patched = substr_replace($patched, pack('V', $centralDirectory + strlen($block)), $newEocd + 16, 4);

        file_put_contents($path, $patched);
    }

    private static function tempPath(string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), 'relbin');
        if ($base === false) {
            throw new RuntimeException('Cannot create a temp file.');
        }
        @unlink($base);
        $path = $base.'.'.$extension;
        self::$files[] = $path;

        return $path;
    }
}
