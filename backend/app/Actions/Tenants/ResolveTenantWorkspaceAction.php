<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Tenant;
use App\Services\Branding\TenantBranding;
use App\Support\PlatformHosts;

class ResolveTenantWorkspaceAction
{
    /** A workspace label that is safe inside a LIKE pattern: no "%", "_" or "\". */
    private const PLAIN_LABEL = '/^[a-z0-9-]+$/';

    public function __construct(
        private readonly TenantBranding $tenantBranding,
    ) {}

    /**
     * Resolve a tenant workspace by code, slug, id, or domain
     */
    public function execute(string $rawCode): array
    {
        $code = $this->sanitizeCode($rawCode);

        if ($code === '') {
            return [
                'success' => false,
                'status' => 422,
                'message' => __('auth.workspace_not_found'),
            ];
        }

        // SEC-21: this route is public. The code is matched exactly against id / slug / domain;
        // the "<code>.<anything>" domain prefix match only runs for a plain label of a-z, 0-9
        // and "-", so no LIKE wildcard ("%", "_") from the client ever reaches the query and
        // "ab%" can no longer list the workspaces whose domain starts with "ab".
        $prefixMatch = preg_match(self::PLAIN_LABEL, $code) === 1;

        // Query Tenant on the central database
        $tenant = Tenant::with('domains')
            ->where('id', $code)
            ->orWhere('slug', $code)
            ->orWhereHas('domains', function ($q) use ($code, $prefixMatch) {
                $q->where('domain', $code)
                    ->when($prefixMatch, fn ($q) => $q->orWhere('domain', 'like', $code.'.%'));
            })
            ->first();

        // OPS-9: a purged (or being purged) workspace no longer exists for its users.
        if (! $tenant || $tenant->isPurged()) {
            return [
                'success' => false,
                'status' => 404,
                'message' => __('auth.workspace_not_found'),
            ];
        }

        // OPS-2: a workspace still being provisioned (or failed) has no usable database yet.
        // 409 here (the login screen shows "being set up"); the tenant API itself answers 503.
        if (! $tenant->isProvisioned()) {
            return [
                'success' => false,
                'status' => 409,
                'error_code' => 'provisioning.workspace_not_ready',
                'message' => __('provisioning.workspace_not_ready'),
                'tenant' => [
                    'tenant_id' => $tenant->id,
                    'name' => $tenant->name,
                    'provisioning_status' => $tenant->provisioningStatus()->value,
                ],
            ];
        }

        // Check if suspended
        // SELL-07: a shop suspended for NON-PAYMENT is still resolvable so it can sign in and pay
        // (EnsureTenantActive confines it to /billing/*); a violation stays refused.
        $paymentRecovery = $tenant->suspension_reason === \App\Support\Tenancy\TenantSuspensionReason::NonPayment
            && ($tenant->status === 'suspended' || $tenant->isSuspended());

        if (! $paymentRecovery && ($tenant->status === 'suspended' || (method_exists($tenant, 'isSuspended') && $tenant->isSuspended()))) {
            return [
                'success' => false,
                'status' => 403,
                'message' => __('auth.workspace_suspended'),
                'tenant' => [
                    'tenant_id' => $tenant->id,
                    'name' => $tenant->name,
                    'status' => 'suspended',
                ],
            ];
        }

        // Resolve primary domain
        $primaryDomain = $tenant->domains->first()?->domain;
        if (! $primaryDomain) {
            $primaryDomain = PlatformHosts::tenantHost((string) $tenant->id);
        }

        // Scheme (and port) follow config('app.url'): https in production, http on the
        // local *.test setup. Never hardcoded.
        $serverUrl = str_starts_with($primaryDomain, 'http')
            ? $primaryDomain
            : PlatformHosts::origin($primaryDomain);

        // BRND-5: the shop's own logo (host-bound public route on its domain) and subtitle,
        // read from the tenant DB. Null when the shop has no logo or is not ready: never the
        // shared public/logo.png, which would show another brand.
        $branding = $this->tenantBranding->forTenant($tenant);
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $logoUrl = $branding?->logoLightUrl;
        $subtitle = $branding !== null
            ? ($branding->subtitle !== '' ? $branding->subtitle : null)
            : ($settings['company_subtitle'] ?? null);

        return [
            'success' => true,
            'status' => 200,
            'data' => [
                'tenant_id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug ?? $tenant->id,
                'domain' => $primaryDomain,
                'server_url' => $serverUrl,
                'status' => $tenant->status ?? 'active',
                'payment_required' => $paymentRecovery,
                'logo_url' => $logoUrl,
                'logo_dark_url' => $branding?->logoDarkUrl,
                'company_subtitle' => $subtitle,
            ],
        ];
    }

    /**
     * Sanitize and extract clean code from input string or URL
     */
    private function sanitizeCode(string $input): string
    {
        $cleaned = trim($input);

        // If a full URL was provided (e.g. https://2m.baraa-solutions.com/...)
        if (str_starts_with($cleaned, 'http://') || str_starts_with($cleaned, 'https://')) {
            $host = parse_url($cleaned, PHP_URL_HOST);
            if ($host) {
                $cleaned = $host;
            }
        }

        // If domain ends with central domain like .baraa-solutions.com
        if (str_contains($cleaned, '.')) {
            $parts = explode('.', $cleaned);
            $cleaned = $parts[0];
        }

        return strtolower(trim($cleaned));
    }
}
