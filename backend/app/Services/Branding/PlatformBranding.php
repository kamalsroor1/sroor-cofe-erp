<?php

declare(strict_types=1);

namespace App\Services\Branding;

use App\DTOs\Branding\PlatformBrandingDTO;
use App\Enums\PlatformSettingKey;
use App\Models\PlatformSetting;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * BRND-1: the ONLY way to read the platform (SaaS operator) branding.
 *
 * Resolution per key: CENTRAL `platform_settings` row → config/branding.php (env).
 *
 * The stored overrides are cached under an EXPLICIT central key
 * (TenantCache::centralKey), so a tenant request (`/system/context`, `/branding`) and
 * a central request share one entry, and a super-admin save (which bumps the central
 * version through PlatformSetting's model events) is visible to every tenant on the
 * next request — no TTL wait.
 *
 * Never throws on read: if the central DB/table or the cache is unavailable (fresh
 * install before `migrate`, `package:discover` without a DB...) it serves the config
 * fallback and caches nothing, so the real values appear as soon as they are readable.
 */
final class PlatformBranding
{
    public const CACHE_NAMESPACE = 'platform_branding';

    private const HEX_COLOR = '/^#[0-9a-f]{6}$/i';

    /** Last resort if config/branding.php itself holds an invalid color. */
    private const FALLBACK_COLOR = '#059669';

    public function get(): PlatformBrandingDTO
    {
        $stored = $this->storedValues();

        return PlatformBrandingDTO::fromArray([
            'name' => $this->string(PlatformSettingKey::Name, $stored),
            'short_name' => $this->string(PlatformSettingKey::ShortName, $stored),
            'subtitle' => $this->string(PlatformSettingKey::Subtitle, $stored),
            'legal_name' => $this->string(PlatformSettingKey::LegalName, $stored),
            'logo_light' => $this->asset(PlatformSettingKey::LogoLight, $stored),
            'logo_dark' => $this->asset(PlatformSettingKey::LogoDark, $stored),
            'favicon' => $this->asset(PlatformSettingKey::Favicon, $stored),
            'app_icon' => $this->asset(PlatformSettingKey::AppIcon, $stored),
            'primary_color' => $this->color($stored),
            'support_email' => $this->string(PlatformSettingKey::SupportEmail, $stored),
            'support_phone' => $this->string(PlatformSettingKey::SupportPhone, $stored),
            'website_url' => $this->string(PlatformSettingKey::WebsiteUrl, $stored),
            'powered_by_enabled' => $this->bool(PlatformSettingKey::PoweredByEnabled, $stored),
        ]);
    }

    /**
     * Upsert platform settings in one central transaction. Values are not validated
     * here beyond the key whitelist (the super-admin FormRequest does that, BRND-2);
     * reads never serve a blank required value or a non-hex color anyway.
     *
     * @param  array<string, string|bool|null>  $values  PlatformSettingKey value => new value (null = back to config)
     *
     * @throws InvalidArgumentException on a key that is not a PlatformSettingKey
     */
    public function update(array $values, ?int $updatedBy = null): PlatformBrandingDTO
    {
        $normalized = [];
        foreach ($values as $key => $value) {
            $settingKey = PlatformSettingKey::tryFrom((string) $key);

            if ($settingKey === null) {
                throw new InvalidArgumentException("Unknown platform setting [{$key}].");
            }

            $normalized[$settingKey->value] = [$settingKey, $this->toStorage($settingKey, $value)];
        }

        // Consistent lock order across concurrent saves.
        ksort($normalized);

        DB::connection($this->centralConnection())->transaction(function () use ($normalized, $updatedBy): void {
            foreach ($normalized as $key => [$settingKey, $value]) {
                $setting = PlatformSetting::query()->where('key', $key)->lockForUpdate()->first()
                    ?? new PlatformSetting(['key' => $key]);

                $setting->fill([
                    'value' => $value,
                    'type' => $settingKey->type(),
                    'updated_by' => $updatedBy,
                ])->save();
            }
        });

        return $this->get();
    }

    /**
     * Push the platform name into the config Laravel itself reads (mail from-name,
     * notifications, Pulse/Telescope titles). Called at boot and on every tenancy
     * initialize/end so long-lived workers pick up a rename.
     */
    public function applyToConfig(): void
    {
        $name = $this->get()->name;

        config([
            'app.name' => $name,
            'mail.from.name' => $name,
        ]);
    }

    /** Invalidate the cached branding for every context (central and all tenants). */
    public static function forget(): void
    {
        try {
            TenantCache::centralBump(self::CACHE_NAMESPACE);
        } catch (Throwable) {
            // Cache store unavailable: nothing cached can be served stale either.
        }
    }

    /** Current cache key of the stored overrides (version-stamped, central scope). */
    public static function cacheKey(): string
    {
        return TenantCache::centralKey(self::CACHE_NAMESPACE.':'.TenantCache::centralVersion(self::CACHE_NAMESPACE));
    }

    /**
     * @return array<string, string|null> stored key => raw value
     */
    private function storedValues(): array
    {
        try {
            $cacheKey = self::cacheKey();
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                /** @var array<string, string|null> $cached */
                return $cached;
            }
        } catch (Throwable) {
            $cacheKey = null;
        }

        try {
            $rows = [];
            foreach (PlatformSetting::query()->get(['key', 'value']) as $setting) {
                $rows[$setting->key] = $setting->value;
            }
        } catch (Throwable) {
            // Table or central DB not reachable: serve config, cache nothing.
            return [];
        }

        if ($cacheKey !== null) {
            try {
                Cache::put($cacheKey, $rows, max(60, (int) config('branding.cache_ttl_seconds', 3600)));
            } catch (Throwable) {
                // A cache write failure only costs a query on the next read.
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, string|null>  $stored
     */
    private function string(PlatformSettingKey $key, array $stored): string
    {
        $value = $stored[$key->value] ?? null;

        if ($value !== null && (! $key->isRequired() || trim($value) !== '')) {
            return $value;
        }

        return $this->configString('branding.'.$key->value);
    }

    /**
     * @param  array<string, string|null>  $stored
     */
    private function asset(PlatformSettingKey $key, array $stored): ?string
    {
        $value = $stored[$key->value] ?? null;

        if ($value !== null && trim($value) !== '') {
            return $value;
        }

        $default = $this->configString('branding.assets.'.$key->value);

        return $default !== '' ? $default : null;
    }

    /**
     * @param  array<string, string|null>  $stored
     */
    private function color(array $stored): string
    {
        foreach ([$stored[PlatformSettingKey::PrimaryColor->value] ?? null, $this->configString('branding.primary_color')] as $candidate) {
            if ($candidate !== null && preg_match(self::HEX_COLOR, $candidate) === 1) {
                return strtolower($candidate);
            }
        }

        return self::FALLBACK_COLOR;
    }

    /**
     * @param  array<string, string|null>  $stored
     */
    private function bool(PlatformSettingKey $key, array $stored): bool
    {
        $value = $stored[$key->value] ?? null;

        if ($value !== null) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return filter_var(config('branding.'.$key->value, false), FILTER_VALIDATE_BOOL);
    }

    private function toStorage(PlatformSettingKey $key, string|bool|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($key->type() === 'bool') {
            return filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0';
        }

        return is_bool($value) ? ($value ? '1' : '0') : $value;
    }

    private function configString(string $key): string
    {
        $value = config($key);

        return is_scalar($value) ? (string) $value : '';
    }

    private function centralConnection(): string
    {
        return (string) config('tenancy.database.central_connection', config('database.default'));
    }
}
