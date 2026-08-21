<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Models\Tenant;
use App\Models\User;
use Stancl\Tenancy\Database\Models\ImpersonationToken;

final class ImpersonateTenantAction
{
    /**
     * Generate Impersonation Magic Login Link for a Tenant
     */
    public function execute(string $tenantId, ?int $targetUserId = null): string
    {
        $tenant = Tenant::findOrFail($tenantId);

        // 1. Resolve Target User inside Tenant's isolated database
        $targetUser = $tenant->run(function () use ($targetUserId) {
            if ($targetUserId) {
                return User::where('id', $targetUserId)->where('is_active', true)->first();
            }

            // Default to first active admin or first active user
            return User::whereHas('roles', fn($q) => $q->where('name', 'admin'))
                ->where('is_active', true)
                ->first() ?? User::where('is_active', true)->first();
        });

        if (!$targetUser) {
            throw new \RuntimeException(__('super.no_active_user_in_store', ['name' => $tenant->name]));
        }

        // 2. Generate Impersonation Token via Stancl
        /** @var ImpersonationToken $token */
        $token = tenancy()->impersonate($tenant, (string)$targetUser->id, '/');

        // 3. Resolve Primary Domain
        $centralDomain = config('tenancy.central_domains.0', 'localhost');
        $primaryDomain = $tenant->domains()->first()?->domain ?? ($tenant->slug . '.' . $centralDomain);

        // Check if port is needed for local dev (e.g. port 8000)
        $hostHeader = request()->header('Host');
        $port = '';
        if ($hostHeader && str_contains($hostHeader, ':')) {
            $parts = explode(':', $hostHeader);
            $port = ':' . end($parts);
        }

        $scheme = request()->getScheme();
        $domainWithPort = str_contains($primaryDomain, ':') ? $primaryDomain : ($primaryDomain . $port);

        return "{$scheme}://{$domainWithPort}/impersonate/{$token->token}";
    }
}
