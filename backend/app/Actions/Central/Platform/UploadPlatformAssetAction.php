<?php

declare(strict_types=1);

namespace App\Actions\Central\Platform;

use App\DTOs\Branding\PlatformBrandingDTO;
use App\Enums\CentralAuditEvent;
use App\Enums\PlatformAssetSlot;
use App\Models\CentralUser;
use App\Models\PlatformSetting;
use App\Services\Branding\PlatformBranding;
use App\Services\CentralAuditLogger;
use App\Support\Media\ImageSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * BRND-2: replaces one platform brand image (logo light/dark, favicon, app icon).
 *
 *  1. ImageSanitizer: magic-byte type check (no SVG/GIF/ICO), size and dimensions per slot,
 *     GD re-encode (metadata and appended payloads dropped) — a 422 before anything is written;
 *  2. the clean bytes go to the CENTRAL public disk as branding/<uuid>.<ext> (server-generated);
 *  3. one central transaction: lock the slot row, point it at the new file, audit;
 *  4. the previous file (only one this feature wrote) is deleted AFTER the commit;
 *     if the DB write fails, the new file is deleted and the error rethrown.
 */
final class UploadPlatformAssetAction
{
    public function __construct(
        private readonly ImageSanitizer $sanitizer,
        private readonly PlatformBranding $branding,
        private readonly CentralAuditLogger $auditLogger,
    ) {}

    public function execute(PlatformAssetSlot $slot, UploadedFile $file, CentralUser $operator): PlatformBrandingDTO
    {
        $image = $this->sanitizer->sanitize($file, $slot->spec(), 'file');

        $disk = Storage::disk(PlatformAssetSlot::DISK);
        $path = PlatformAssetSlot::DIRECTORY.'/'.Str::uuid()->toString().'.'.$image->extension;

        if (! $disk->put($path, $image->binary)) {
            // Developer-facing; the handler renders a generic 500.
            throw new RuntimeException("Platform asset [{$slot->value}] could not be written to the central disk.");
        }

        $connection = DB::connection((string) (new PlatformSetting)->getConnectionName());

        try {
            return $connection->transaction(function () use ($connection, $slot, $path, $image, $operator): PlatformBrandingDTO {
                $previous = PlatformSetting::query()
                    ->where('key', $slot->settingKey()->value)
                    ->lockForUpdate()
                    ->value('value');
                $previous = is_string($previous) ? $previous : null;

                $branding = $this->branding->update([$slot->settingKey()->value => $path], (int) $operator->getKey());

                $this->auditLogger->record(
                    CentralAuditEvent::PlatformAssetUploaded,
                    [
                        'slot' => $slot->value,
                        'path' => $path,
                        'previous_path' => $previous,
                        'mime' => $image->mime,
                        'width' => $image->width,
                        'height' => $image->height,
                        'sha256' => $image->sha256,
                    ],
                    actor: $operator,
                );

                if ($previous !== $path && PlatformAssetSlot::isManagedPath($previous)) {
                    $connection->afterCommit(static fn () => self::deleteFile((string) $previous));
                }

                return $branding;
            });
        } catch (Throwable $e) {
            self::deleteFile($path);

            throw $e;
        }
    }

    private static function deleteFile(string $path): void
    {
        try {
            Storage::disk(PlatformAssetSlot::DISK)->delete($path);
        } catch (Throwable $e) {
            // An orphan file is harmless; never fail the request for it.
            Log::warning('Platform asset file could not be deleted', ['path' => $path, 'exception' => $e]);
        }
    }
}
