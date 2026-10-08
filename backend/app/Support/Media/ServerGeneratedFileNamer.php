<?php

declare(strict_types=1);

namespace App\Support\Media;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\Support\FileNamer\DefaultFileNamer;

/**
 * W1 hardening note 5: every medialibrary file name on disk is generated server-side.
 *
 * The package names the stored file after the client's upload name. Here the base name is
 * replaced by a fresh lowercase ULID, so nothing the client typed (path tricks, unicode,
 * names that collide or leak personal data) reaches the filesystem. FileAdder then appends
 * the extension, which is constrained by media-library.allowed_extensions /
 * disallowed_extensions and by each FormRequest's mime validation.
 *
 * Conversions and responsive images derive from the stored (already generated) name.
 * The client name is kept only as the `name` display column of the media row.
 */
class ServerGeneratedFileNamer extends DefaultFileNamer
{
    public function originalFileName(string $fileName): string
    {
        return Str::lower((string) Str::ulid());
    }
}
