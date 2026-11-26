<?php

declare(strict_types=1);

namespace App\Actions\System;

use App\Http\Middleware\DenyQuickLoginToken;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\TemporaryAccessService;
use Carbon\CarbonImmutable;

/**
 * #232 UI §3 / item 7: `subscription_temporary_access` of the SPA bootstrap (GET /system/context),
 * the 48-hour temporary-activation banner (TemporaryAccessService::tenantBanner: state none is never
 * returned, null instead). Same audience as `subscription_banner`: users who may open /billing
 * (`billing.manage`), never a quick-login session; everyone else gets null.
 */
final class GetTemporaryAccessBannerAction
{
    public function __construct(private readonly TemporaryAccessService $temporaryAccess) {}

    /**
     * @return array<string, mixed>|null
     */
    public function execute(User $user, ?Tenant $tenant, ?CarbonImmutable $now = null): ?array
    {
        if (! $tenant instanceof Tenant || ! $user->can('billing.manage') || DenyQuickLoginToken::isQuickLoginSession($user)) {
            return null;
        }

        return $this->temporaryAccess->tenantBanner($tenant, $now ?? CarbonImmutable::now());
    }
}
