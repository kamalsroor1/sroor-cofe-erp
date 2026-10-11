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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * BRND-2: removes one uploaded platform brand image; the slot falls back to the
 * config/branding.php default. One central transaction (row locked, audited); the file
 * is deleted from the central public disk only after the commit, and only if this
 * feature wrote it. Idempotent: an empty slot changes nothing and is not audited.
 */
final class DeletePlatformAssetAction
{
    public function __construct(
        private readonly PlatformBranding $branding,
        private readonly CentralAuditLogger $auditLogger,
    ) {}

    public function execute(PlatformAssetSlot $slot, CentralUser $operator): PlatformBrandingDTO
    {
        $connection = DB::connection((string) (new PlatformSetting)->getConnectionName());

        return $connection->transaction(function () use ($connection, $slot, $operator): PlatformBrandingDTO {
            $previous = PlatformSetting::query()
                ->where('key', $slot->settingKey()->value)
                ->lockForUpdate()
                ->value('value');

            if (! is_string($previous) || trim($previous) === '') {
                return $this->branding->get();
            }

            $branding = $this->branding->update([$slot->settingKey()->value => null], (int) $operator->getKey());

            $this->auditLogger->record(
                CentralAuditEvent::PlatformAssetDeleted,
                ['slot' => $slot->value, 'path' => $previous],
                actor: $operator,
            );

            if (PlatformAssetSlot::isManagedPath($previous)) {
                $connection->afterCommit(static function () use ($previous): void {
                    try {
                        Storage::disk(PlatformAssetSlot::DISK)->delete($previous);
                    } catch (Throwable $e) {
                        Log::warning('Platform asset file could not be deleted', ['path' => $previous, 'exception' => $e]);
                    }
                });
            }

            return $branding;
        });
    }
}
