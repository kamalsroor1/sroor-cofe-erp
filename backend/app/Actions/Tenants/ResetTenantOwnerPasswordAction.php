<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\DTOs\Users\TemporaryPasswordResult;
use App\Enums\CentralAuditEvent;
use App\Exceptions\TenantProvisioningException;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CentralAuditLogger;
use App\Support\Auth\AccountLockout;
use App\Support\Auth\TemporaryPassword;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * SELL-01: the platform console resets the password of a shop's OWNER who forgot it.
 *
 * The owner is the ACTIVE tenant user holding the `admin` role whose email is the tenant's
 * email (the account provisioning created; matched_by `email`), else the oldest active `admin`
 * account (matched_by `fallback`, shown to the operator and audited so identity can be
 * confirmed before the password is read out). Inside THAT tenant's
 * database only, in one tenant transaction on the locked owner row: a server-generated temporary
 * password is hashed in, `must_change_password` is set and every token of the owner is revoked.
 * The sign-in lockout of the owner is lifted. Then the central audit row is written (no password).
 *
 * Tenancy is initialised by hand and the previous context (central, or another tenant) always
 * restored in `finally`, like RevokeTenantTokensAction: run() does not restore it on a throw.
 * 404 unknown tenant; 409 workspace not ready (provisioning / purged) or no admin account.
 */
final class ResetTenantOwnerPasswordAction
{
    public const MATCHED_BY_EMAIL = 'email';

    public const MATCHED_BY_FALLBACK = 'fallback';

    public function __construct(
        private readonly CentralAuditLogger $auditLogger,
    ) {}

    public function execute(string $tenantId, CentralUser $operator): TemporaryPasswordResult
    {
        $tenant = Tenant::query()->whereKey($tenantId)->first();
        if (! $tenant instanceof Tenant) {
            throw (new ModelNotFoundException)->setModel(Tenant::class, [$tenantId]);
        }

        if (! $tenant->isProvisioned()) {
            throw TenantProvisioningException::requiresReadyWorkspace($tenant->provisioningStatus());
        }

        $temporaryPassword = TemporaryPassword::generate();
        $hash = Hash::make($temporaryPassword);

        [$owner, $revoked, $matchedBy] = $this->resetInsideTenant($tenant, $hash);

        $this->auditLogger->record(
            CentralAuditEvent::TenantOwnerPasswordReset,
            // Key names avoid the logger's redacted fragments (`password`, `token`): plain facts only.
            ['owner_user_id' => (int) $owner->id, 'matched_by' => $matchedBy, 'sessions_revoked' => $revoked, 'forced_change_on_next_login' => true],
            actor: $operator,
            subject: $tenant,
        );

        return new TemporaryPasswordResult($owner, $temporaryPassword, $matchedBy);
    }

    /**
     * @return array{0: User, 1: int, 2: string} the owner (as saved), the number of tokens revoked, matched_by
     */
    private function resetInsideTenant(Tenant $tenant, string $hash): array
    {
        $tenancy = tenancy();
        $previous = $tenancy->initialized ? $tenancy->tenant : null;

        $tenancy->initialize($tenant);

        try {
            $result = DB::transaction(function () use ($tenant, $hash): array {
                [$owner, $matchedBy] = $this->lockOwner($tenant);

                $owner->forceFill([
                    'password' => $hash,
                    'must_change_password' => true,
                ])->save();

                return [$owner, (int) $owner->tokens()->delete(), $matchedBy];
            });

            AccountLockout::clearForIdentifiers($result[0]->phone, $result[0]->email);

            return $result;
        } finally {
            if ($previous instanceof TenantContract && $previous->getTenantKey() !== $tenant->getTenantKey()) {
                $tenancy->initialize($previous);
            } elseif (! $previous instanceof TenantContract) {
                $tenancy->end();
            }
        }
    }

    /**
     * @return array{0: User, 1: string} the locked owner row and matched_by (`email` | `fallback`)
     */
    private function lockOwner(Tenant $tenant): array
    {
        $activeAdmins = fn () => User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin'));

        $email = trim((string) $tenant->email);
        $ownerId = $email !== '' ? $activeAdmins()->where('email', $email)->value('id') : null;
        $matchedBy = self::MATCHED_BY_EMAIL;

        if ($ownerId === null) {
            $ownerId = $activeAdmins()->orderBy('id')->value('id');
            $matchedBy = self::MATCHED_BY_FALLBACK;
        }

        if ($ownerId === null) {
            throw new ConflictHttpException((string) __('super.tenant_owner_not_found'));
        }

        return [User::query()->whereKey($ownerId)->lockForUpdate()->firstOrFail(), $matchedBy];
    }
}
