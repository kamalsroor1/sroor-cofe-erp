<?php

declare(strict_types=1);

namespace App\Services\Branding;

use App\DTOs\Branding\TenantBrandingDTO;
use App\Enums\TenantLogoVariant;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\TenantBrandProfile;
use App\Support\PlatformHosts;
use Closure;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * BRND-5: the ONLY way to read the shop (tenant) branding.
 *
 * Text values live in the tenant `settings` k/v table (Setting, per-tenant cache); the
 * logos are media of the tenant's TenantBrandProfile on the tenant-suffixed disk. Nothing
 * is ever read from or written to public/logo*.png (those files are shared by every tenant).
 *
 * Name fallback: settings company_name → tenant name → platform name (PlatformBranding).
 *
 * get(), logoFile() and logoDataUri() must run with tenancy initialized (tenant DB and
 * tenant disk). forTenant() is the safe entry point from a central context.
 */
final class TenantBranding
{
    public const KEY_RECEIPT_HEADER_LINES = 'receipt_header_lines';

    public const KEY_RECEIPT_FOOTER_TEXT = 'receipt_footer_text';

    public const KEY_THEME_COLOR = 'system_theme_color';

    public const MAX_HEADER_LINES = 6;

    public const MAX_HEADER_LINE_LENGTH = 80;

    public const MAX_FOOTER_LENGTH = 500;

    /** Preset ids of the SPA theme palette (resources/js/helpers/themeHelper.js). */
    public const THEME_PALETTE = ['amber', 'emerald', 'blue', 'purple', 'rose', 'orange', 'teal', 'indigo'];

    public const DEFAULT_THEME_COLOR = 'emerald';

    /** Same list UpdateSettingsRequest accepts for invoice_primary_color. */
    public const INVOICE_COLORS = ['amber', 'emerald', 'blue', 'slate'];

    public const HEX_COLOR_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    /** Characters refused in printed receipt text (plain text only, never markup). */
    public const MARKUP_PATTERN = '/[<>]/';

    /** Stored extension => served Content-Type. Anything else is never served. */
    private const MIME_BY_EXTENSION = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    public function __construct(
        private readonly PlatformBranding $platformBranding,
    ) {}

    public function get(): TenantBrandingDTO
    {
        $tenant = $this->currentTenant();
        $media = $this->latestLogoMedia();

        $name = trim((string) Setting::get('company_name', ''));
        if ($name === '') {
            $name = trim((string) $tenant->name);
        }
        if ($name === '') {
            $name = $this->platformBranding->get()->name;
        }

        $footer = trim((string) Setting::get(self::KEY_RECEIPT_FOOTER_TEXT, ''));
        if ($footer === '') {
            $footer = trim((string) Setting::get('invoice_footer_note', ''));
        }

        return TenantBrandingDTO::fromArray([
            'name' => $name,
            'subtitle' => trim((string) Setting::get('company_subtitle', '')),
            'phone' => trim((string) Setting::get('company_phone', '')),
            'address' => trim((string) Setting::get('company_address', '')),
            'theme_color' => $this->themeColor(),
            'invoice_color' => $this->invoiceColor(),
            'receipt_header_lines' => self::parseHeaderLines(Setting::get(self::KEY_RECEIPT_HEADER_LINES)),
            'receipt_footer_text' => mb_substr($footer, 0, self::MAX_FOOTER_LENGTH),
            'commercial_register' => trim((string) Setting::get('commercial_register', '')),
            'tax_registration_no' => trim((string) Setting::get('tax_registration_no', '')),
            'show_logo' => Setting::getBool('show_print_logo', true),
            'show_name' => Setting::getBool('show_print_company_name', true),
            'show_subtitle' => Setting::getBool('show_print_subtitle', true),
            'logo_light_url' => $this->logoUrl($tenant, TenantLogoVariant::Light, $media[TenantLogoVariant::Light->value] ?? null),
            'logo_dark_url' => $this->logoUrl($tenant, TenantLogoVariant::Dark, $media[TenantLogoVariant::Dark->value] ?? null),
        ]);
    }

    /**
     * The branding of $tenant from ANY context (central resolver, super-admin). Initializes
     * the tenant, restores the previous context afterwards (stancl's run() does not on
     * exceptions) and returns null if the tenant is not ready or its database is unreadable.
     */
    public function forTenant(Tenant $tenant): ?TenantBrandingDTO
    {
        if (! $tenant->isProvisioned()) {
            return null;
        }

        return $this->runInTenant($tenant, fn (): TenantBrandingDTO => $this->get());
    }

    /**
     * The stored logo bytes for the public logo route. Null when the shop has none.
     *
     * @return array{binary: string, mime: string, sha256: string}|null
     */
    public function logoFile(TenantLogoVariant $variant): ?array
    {
        $this->currentTenant();

        $media = $this->latestLogoMedia()[$variant->value] ?? null;
        $mime = $media !== null ? $this->servedMime($media) : null;

        if ($media === null || $mime === null) {
            return null;
        }

        $binary = Storage::disk($media->disk)->get($media->getPathRelativeToRoot());

        if (! is_string($binary) || $binary === '') {
            return null;
        }

        $sha256 = $media->getCustomProperty('sha256');

        return [
            'binary' => $binary,
            'mime' => $mime,
            'sha256' => is_string($sha256) && $sha256 !== '' ? $sha256 : hash('sha256', $binary),
        ];
    }

    /**
     * data: URI of the logo for server-rendered prints (no network fetch, no shared file).
     * The dark logo falls back to the light one.
     */
    public function logoDataUri(TenantLogoVariant $variant = TenantLogoVariant::Light): ?string
    {
        $file = $this->logoFile($variant);

        if ($file === null && $variant === TenantLogoVariant::Dark) {
            $file = $this->logoFile(TenantLogoVariant::Light);
        }

        return $file !== null ? 'data:'.$file['mime'].';base64,'.base64_encode($file['binary']) : null;
    }

    /**
     * Stored receipt header (one line per "\n") as a clean list: trimmed, blanks dropped,
     * capped to MAX_HEADER_LINES lines of MAX_HEADER_LINE_LENGTH characters.
     *
     * @return list<string>
     */
    public static function parseHeaderLines(?string $raw): array
    {
        $lines = [];

        foreach (preg_split('/\R/u', (string) $raw) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '') {
                $lines[] = mb_substr($line, 0, self::MAX_HEADER_LINE_LENGTH);
            }
        }

        return array_slice($lines, 0, self::MAX_HEADER_LINES);
    }

    public static function isValidThemeColor(string $value): bool
    {
        return in_array($value, self::THEME_PALETTE, true) || preg_match(self::HEX_COLOR_PATTERN, $value) === 1;
    }

    /** Origin of the tenant's primary host (first domain record, else "<slug>.<base domain>"). */
    public static function tenantOrigin(Tenant $tenant): string
    {
        $domain = $tenant->domains->first()?->domain;
        $host = is_string($domain) && $domain !== ''
            ? $domain
            : PlatformHosts::tenantHost((string) ($tenant->slug ?: $tenant->getTenantKey()));

        return str_starts_with($host, 'http://') || str_starts_with($host, 'https://')
            ? rtrim($host, '/')
            : PlatformHosts::origin($host);
    }

    private function themeColor(): string
    {
        $value = trim((string) Setting::get(self::KEY_THEME_COLOR, ''));

        return self::isValidThemeColor($value) ? strtolower($value) : self::DEFAULT_THEME_COLOR;
    }

    private function invoiceColor(): string
    {
        $value = (string) Setting::get('invoice_primary_color', '');

        return in_array($value, self::INVOICE_COLORS, true) ? $value : self::DEFAULT_THEME_COLOR;
    }

    private function logoUrl(Tenant $tenant, TenantLogoVariant $variant, ?Media $media): ?string
    {
        if ($media === null || $this->servedMime($media) === null) {
            return null;
        }

        $sha256 = $media->getCustomProperty('sha256');
        $version = is_string($sha256) && $sha256 !== '' ? substr($sha256, 0, 16) : (string) $media->getKey();

        return self::tenantOrigin($tenant).'/api/v1/branding/logo/'.$variant->value.'?v='.$version;
    }

    /**
     * Newest media per variant (an interrupted replacement can leave two rows for a moment;
     * the newest one is the current logo).
     *
     * @return array<string, Media>
     */
    private function latestLogoMedia(): array
    {
        $profile = TenantBrandProfile::query()->where('singleton_key', TenantBrandProfile::SINGLETON_KEY)->first();

        if ($profile === null) {
            return [];
        }

        $byCollection = [];
        foreach (TenantLogoVariant::cases() as $variant) {
            $byCollection[$variant->collection()] = $variant->value;
        }

        $latest = [];
        $media = $profile->media()
            ->whereIn('collection_name', array_keys($byCollection))
            ->orderByDesc('id')
            ->get();

        foreach ($media as $item) {
            $variant = $byCollection[$item->collection_name] ?? null;

            if ($variant !== null && ! isset($latest[$variant])) {
                $latest[$variant] = $item;
            }
        }

        return $latest;
    }

    /** Content-Type fixed by the stored (server-generated, re-encoded) extension. */
    private function servedMime(Media $media): ?string
    {
        $extension = strtolower(pathinfo((string) $media->file_name, PATHINFO_EXTENSION));

        return self::MIME_BY_EXTENSION[$extension] ?? null;
    }

    private function currentTenant(): Tenant
    {
        $tenant = tenancy()->initialized ? tenancy()->tenant : null;

        if (! $tenant instanceof Tenant) {
            // Developer error: the shop branding lives in the tenant DB.
            throw new LogicException('TenantBranding needs an initialized tenant; use forTenant() from a central context.');
        }

        return $tenant;
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn|null
     */
    private function runInTenant(Tenant $tenant, Closure $callback): mixed
    {
        $previous = tenancy()->initialized ? tenancy()->tenant : null;

        try {
            tenancy()->initialize($tenant);

            return $callback();
        } catch (Throwable $e) {
            report($e);

            return null;
        } finally {
            if ($previous !== null) {
                tenancy()->initialize($previous);
            } elseif (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }
}
