<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\ImportType;
use App\Support\TenantCache;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * PRD-06: the uploaded file of one wizard run. The file sits on the tenant-suffixed private `local`
 * disk under a server-generated name; the token (cache, 1 hour) ties it to a user and a type.
 */
final class ImportSessionStore
{
    public const TTL_SECONDS = 3600;

    private const DIRECTORY = 'imports';

    /** Files abandoned after a preview are swept after this long. */
    private const STALE_FILE_SECONDS = 86400;

    public function create(UploadedFile $file, ImportType $type, int $userId): string
    {
        $this->sweepStaleFiles();

        $token = Str::random(40);
        $extension = strtolower($file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';
        $path = self::DIRECTORY.'/'.$token.'.'.$extension;

        Storage::disk('local')->putFileAs(self::DIRECTORY, $file, $token.'.'.$extension);

        Cache::put(TenantCache::key('import:'.$token), [
            'user_id' => $userId,
            'type' => $type->value,
            'path' => $path,
            'extension' => $extension,
        ], self::TTL_SECONDS);

        return $token;
    }

    /**
     * @return array{path: string, absolute_path: string, extension: string}|null null for an unknown, expired,
     *                                                                            foreign or wrong-type token
     */
    public function find(string $token, ImportType $type, int $userId): ?array
    {
        $entry = Cache::get(TenantCache::key('import:'.$token));

        if (! is_array($entry)
            || ($entry['user_id'] ?? null) !== $userId
            || ($entry['type'] ?? null) !== $type->value
            || ! Storage::disk('local')->exists((string) $entry['path'])) {
            return null;
        }

        return [
            'path' => (string) $entry['path'],
            'absolute_path' => Storage::disk('local')->path((string) $entry['path']),
            'extension' => (string) $entry['extension'],
        ];
    }

    public function forget(string $token, string $path): void
    {
        Storage::disk('local')->delete($path);
        Cache::forget(TenantCache::key('import:'.$token));
    }

    private function sweepStaleFiles(): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->files(self::DIRECTORY) as $file) {
            if ($disk->lastModified($file) < now()->subSeconds(self::STALE_FILE_SECONDS)->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }
}
