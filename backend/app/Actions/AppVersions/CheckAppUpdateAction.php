<?php

declare(strict_types=1);

namespace App\Actions\AppVersions;

use App\DTOs\AppVersions\CheckUpdateDTO;
use App\Enums\AppPlatform;
use App\Models\AppVersion;
use App\Support\AppReleases\ReleaseFileLocator;
use Illuminate\Support\Collection;

/**
 * Decide what the installed app should be offered (GET /api/v1/app/check-update).
 *
 * Only ADVERTISABLE releases count: active, binary stored and still on a release disk, size
 * > 0 and a valid SHA-256 checksum. A newer row without a binary or checksum is skipped as if
 * it did not exist (APP-3 W1 Q7: an update without checksum is never offered), so a broken
 * upload can never lock devices into a forced update they cannot install.
 *
 * `rollback` is the advertisable release just below `latest` (desktop APP-7: offered after
 * repeated failed boots, never applied silently).
 */
final class CheckAppUpdateAction
{
    /** How many recent releases are considered (latest + rollback, skipping broken ones). */
    private const CANDIDATES = 20;

    /**
     * @return array{dto: CheckUpdateDTO, latest: AppVersion|null, rollback: AppVersion|null, has_update: bool, force_update: bool}
     */
    public function execute(CheckUpdateDTO $dto): array
    {
        [$latest, $rollback] = $this->latestTwo($dto->platform);

        // N1: a missing or 0 version_code is UNKNOWN (desktop builds before APP-7 send '0'): the
        // update is offered (has_update whenever a release exists) but never forced, so a client
        // that cannot tell its own version is never locked behind an uncloseable dialog.
        $current = $dto->versionCode ?? 0;
        $known = $current > 0;
        $hasUpdate = $latest instanceof AppVersion && $latest->version_code > $current;
        $forceUpdate = $known && $hasUpdate && ($latest->is_force_update || $current < $latest->min_version_code);

        return [
            'dto' => $dto,
            'latest' => $latest,
            'rollback' => $rollback,
            'has_update' => $hasUpdate,
            'force_update' => $forceUpdate,
        ];
    }

    /**
     * @return array{0: AppVersion|null, 1: AppVersion|null}
     */
    private function latestTwo(AppPlatform $platform): array
    {
        /** @var Collection<int, AppVersion> $candidates */
        $candidates = AppVersion::query()
            ->forPlatform($platform->value)
            ->advertisable()
            ->orderByDesc('version_code')
            ->limit(self::CANDIDATES)
            ->get();

        $found = [];
        foreach ($candidates as $release) {
            if ($release->hasValidChecksum() && ReleaseFileLocator::diskFor($release) !== null) {
                $found[] = $release;
            }
            if (count($found) === 2) {
                break;
            }
        }

        return [$found[0] ?? null, $found[1] ?? null];
    }
}
