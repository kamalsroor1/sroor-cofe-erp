<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Exceptions\TenantProvisioningException;
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

        // OPS-2: a pending / running / failed tenant has no usable database (409).
        if (! $tenant->isProvisioned()) {
            throw TenantProvisioningException::requiresReadyWorkspace($tenant->provisioningStatus());
        }

        // 1. Resolve Target User inside Tenant's isolated database
        $targetUser = $tenant->run(function () use ($targetUserId) {
            if ($targetUserId) {
                return User::where('id', $targetUserId)->where('is_active', true)->first();
            }

            // Default to first active admin or first active user
            return User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))
                ->where('is_active', true)
                ->first() ?? User::where('is_active', true)->first();
        });

        if (! $targetUser) {
            throw new \RuntimeException(__('super.no_active_user_in_store', ['name' => $tenant->name]));
        }

        // 2. Generate the impersonation token directly (same as stancl's `impersonate` macro,
        // which only exists once the Tenancy singleton has been resolved and its features booted).
        $token = ImpersonationToken::create([
            'tenant_id' => $tenant->getTenantKey(),
            'user_id' => (string) $targetUser->id,
            'redirect_url' => '/',
            'auth_guard' => null,
        ]);

        // 3. Resolve Primary Domain
        $centralDomain = config('tenancy.central_domains.0', 'localhost');
        $primaryDomain = $tenant->domains()->first()?->domain ?? ($tenant->slug.'.'.$centralDomain);

        // Check if port is needed for local dev (e.g. port 8000)
        $hostHeader = request()->header('Host');
        $port = '';
        if ($hostHeader && str_contains($hostHeader, ':')) {
            $parts = explode(':', $hostHeader);
            $port = ':'.end($parts);
        }

        $scheme = request()->getScheme();
        $domainWithPort = str_contains($primaryDomain, ':') ? $primaryDomain : ($primaryDomain.$port);

        return "{$scheme}://{$domainWithPort}/impersonate/{$token->token}";
    }
}
