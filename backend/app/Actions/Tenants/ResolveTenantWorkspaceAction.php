<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Tenant;

class ResolveTenantWorkspaceAction
{
    /**
     * Resolve a tenant workspace by code, slug, id, or domain
     *
     * @param string $rawCode
     * @return array
     */
    public function execute(string $rawCode): array
    {
        $code = $this->sanitizeCode($rawCode);

        if ($code === '') {
            return [
                'success' => false,
                'status'  => 422,
                'message' => __('auth.workspace_not_found'),
            ];
        }

        // Query Tenant on the central database
        $tenant = Tenant::with('domains')
            ->where('id', $code)
            ->orWhere('slug', $code)
            ->orWhereHas('domains', function ($q) use ($code) {
                $q->where('domain', $code)
                  ->orWhere('domain', 'like', "{$code}.%");
            })
            ->first();

        if (!$tenant) {
            return [
                'success' => false,
                'status'  => 404,
                'message' => __('auth.workspace_not_found'),
            ];
        }

        // Check if suspended
        if ($tenant->status === 'suspended' || (method_exists($tenant, 'isSuspended') && $tenant->isSuspended())) {
            return [
                'success' => false,
                'status'  => 403,
                'message' => __('auth.workspace_suspended'),
                'tenant'  => [
                    'tenant_id' => $tenant->id,
                    'name'      => $tenant->name,
                    'status'    => 'suspended',
                ],
            ];
        }

        // Resolve primary domain
        $primaryDomain = $tenant->domains->first()?->domain;
        if (!$primaryDomain) {
            $centralDomain = config('tenancy.central_domains.2', 'baraa-solutions.com');
            $primaryDomain = "{$tenant->id}.{$centralDomain}";
        }

        $serverUrl = str_starts_with($primaryDomain, 'http')
            ? $primaryDomain
            : "https://{$primaryDomain}";

        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $logoUrl = $settings['logo_url'] ?? asset('logo.png');
        $subtitle = $settings['company_subtitle'] ?? null;

        return [
            'success' => true,
            'status'  => 200,
            'data'    => [
                'tenant_id'        => $tenant->id,
                'name'             => $tenant->name,
                'slug'             => $tenant->slug ?? $tenant->id,
                'domain'           => $primaryDomain,
                'server_url'       => $serverUrl,
                'status'           => $tenant->status ?? 'active',
                'logo_url'         => $logoUrl,
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
