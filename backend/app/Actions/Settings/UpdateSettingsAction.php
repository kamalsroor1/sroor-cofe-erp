<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Enums\TenantLogoVariant;
use App\Models\Setting;
use App\Models\TenantBrandProfile;
use App\Services\ActivityLogService;
use App\Services\Branding\TenantBranding;
use App\Services\Settings\PosSaleSettings;
use App\Services\Settings\SettingSecrets;
use App\Services\Settings\TenantSettings;
use App\Support\DashboardCache;
use App\Support\Media\SanitizedImage;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

final class UpdateSettingsAction
{
    /** Multipart file fields of the legacy form: never stored as settings values. */
    private const FILE_KEYS = ['logo_file', 'logo_light_file', 'logo_dark_file'];

    public function __construct(
        private readonly UploadTenantBrandAssetAction $uploadTenantBrandAssetAction,
        private readonly ActivityLogService $activityLogService,
        private readonly PosSaleSettings $posSaleSettings,
    ) {}

    /**
     * Update system settings dictionary and flush settings cache.
     *
     * SETG-7: secrets are write-only (see SettingSecrets) — a blank secret keeps the
     * stored value and the returned dictionary never contains a secret.
     *
     * BRND-5 + W2 batch 4 review (S4): the legacy logo files (already validated and
     * sanitized by UpdateSettingsRequest) are stored per tenant FIRST, through
     * UploadTenantBrandAssetAction (never in public/, never shared between tenants, no file
     * I/O inside a transaction), keeping the previous logo. Then the settings are written in
     * one transaction. If anything fails, the new logo media are deleted again and the old
     * ones stay; on success the replaced logos are removed. A failed request therefore
     * changes nothing (neither half-saved settings nor a lost logo).
     *
     * @param  array<string, mixed>  $data  validated UpdateSettingsRequest data
     * @param  array<string, SanitizedImage>  $logos  TenantLogoVariant value => sanitized logo
     * @return array<string, mixed>
     */
    public function execute(array $data, array $logos = []): array
    {
        $data = SettingSecrets::prepareForWrite($data);
        $variants = array_map(static fn (string $variant): TenantLogoVariant => TenantLogoVariant::from($variant), array_keys($logos));
        $previous = $this->logoMedia($variants);

        try {
            foreach ($logos as $variant => $image) {
                $this->uploadTenantBrandAssetAction->execute(TenantLogoVariant::from($variant), $image, removePrevious: false);
            }

            $this->writeSettings($data);
        } catch (Throwable $e) {
            try {
                $this->removeMedia($this->newLogoMedia($variants, $previous));
            } catch (Throwable $cleanup) {
                report($cleanup); // never hide the original failure
            }

            throw $e;
        }

        Setting::clearCache();
        // PERF-01: the cached dashboard embeds the currency label and the low-stock threshold.
        DashboardCache::invalidate();
        $this->removeMedia($previous);

        return SettingSecrets::mask(Setting::allCached());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeSettings(array $data): void
    {
        DB::transaction(function () use ($data): void {
            $hasNegativeStockField = array_key_exists(TenantSettings::KEY_ALLOW_NEGATIVE_STOCK, $data);
            $previousAllowNegative = Setting::getBool(
                TenantSettings::KEY_ALLOW_NEGATIVE_STOCK,
                TenantSettings::DEFAULT_ALLOW_NEGATIVE_STOCK,
            );

            foreach ($data as $key => $value) {
                if (in_array($key, self::FILE_KEYS, true)) {
                    continue;
                }

                if ($key === TenantBranding::KEY_RECEIPT_HEADER_LINES) {
                    // Stored normalized: trimmed lines, blanks dropped, one per "\n".
                    $value = implode("\n", TenantBranding::parseHeaderLines(is_string($value) ? $value : null));
                }

                if (is_array($value) && $this->posSaleSettings->isListUnchanged((string) $key, $value)) {
                    // SET-01: an untouched list (another tab was saved) is not written, so a default
                    // stays dynamic and a list hidden by a lower cap is not narrowed.
                    continue;
                }

                if (is_array($value)) {
                    // SET-01: list settings (payment methods, denominations, discount presets) are stored as CSV.
                    $value = PosSaleSettings::serialize((string) $key, $value);
                }

                if (is_bool($value)) {
                    Setting::set($key, $value ? '1' : '0');
                } else {
                    Setting::set($key, (string) ($value ?? ''));
                }
            }

            if ($hasNegativeStockField) {
                $newAllowNegative = filter_var(
                    $data[TenantSettings::KEY_ALLOW_NEGATIVE_STOCK],
                    FILTER_VALIDATE_BOOLEAN,
                );

                if ($newAllowNegative !== $previousAllowNegative) {
                    $this->activityLogService->log(
                        module: 'settings',
                        action: 'update',
                        description: $newAllowNegative
                            ? __('settings.activity_allow_negative_stock_enabled')
                            : __('settings.activity_allow_negative_stock_disabled'),
                        properties: [
                            'setting' => TenantSettings::KEY_ALLOW_NEGATIVE_STOCK,
                            'old' => $previousAllowNegative,
                            'new' => $newAllowNegative,
                        ],
                    );
                }
            }
        });
    }

    /**
     * The current media of the given logo slots (none when no logo is uploaded).
     *
     * @param  list<TenantLogoVariant>  $variants
     * @return list<Media>
     */
    private function logoMedia(array $variants): array
    {
        if ($variants === []) {
            return [];
        }

        $profile = TenantBrandProfile::query()->where('singleton_key', TenantBrandProfile::SINGLETON_KEY)->first();
        if ($profile === null) {
            return [];
        }

        return $profile->media()
            ->whereIn('collection_name', array_map(static fn (TenantLogoVariant $variant): string => $variant->collection(), $variants))
            ->get()
            ->all();
    }

    /**
     * Media of the given slots added by this request (not in $previous).
     *
     * @param  list<TenantLogoVariant>  $variants
     * @param  list<Media>  $previous
     * @return list<Media>
     */
    private function newLogoMedia(array $variants, array $previous): array
    {
        $previousIds = array_map(static fn (Media $media): int => (int) $media->getKey(), $previous);

        return array_values(array_filter(
            $this->logoMedia($variants),
            static fn (Media $media): bool => ! in_array((int) $media->getKey(), $previousIds, true),
        ));
    }

    /**
     * Delete media rows + files; a failure is reported, never thrown (readers serve the
     * newest media of a slot).
     *
     * @param  list<Media>  $media
     */
    private function removeMedia(array $media): void
    {
        foreach ($media as $item) {
            try {
                $item->delete();
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
