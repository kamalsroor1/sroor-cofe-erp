<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Actions\Tenants\ResetTenantOwnerPasswordAction;
use App\Enums\CentralAuditEvent;
use App\Models\CentralAuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * SELL-01: POST /api/v1/super-admin/tenants/{id}/reset-owner-password — step-up, audited, writes
 * the owner row in THAT tenant's database only, revokes the owner's tokens, flags the change.
 */
final class TenantOwnerPasswordResetApiTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const OLD_PASSWORD = 'password';

    private Tenant $tenant;

    private Tenant $otherTenant;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->seedCentralPlatformRoles();
        $this->tenant = $this->createTenant();
        $this->otherTenant = $this->createTenant();
    }

    private function uri(?Tenant $tenant = null): string
    {
        return '/api/v1/super-admin/tenants/'.($tenant ?? $this->tenant)->getTenantKey().'/reset-owner-password';
    }

    /** @return array{password: string, flag: bool, tokens: int} */
    private function snapshot(Tenant $tenant, int $userId): array
    {
        return $this->inTenant($tenant, function () use ($userId): array {
            $user = User::query()->findOrFail($userId);

            return ['password' => (string) $user->password, 'flag' => (bool) $user->must_change_password, 'tokens' => $user->tokens()->count()];
        });
    }

    private function auditCount(): int
    {
        return CentralAuditLog::query()->where('event', CentralAuditEvent::TenantOwnerPasswordReset->value)->count();
    }

    public function test_reset_without_a_recent_second_factor_is_step_up_required_and_changes_nothing(): void
    {
        $owner = $this->tenantAdmin($this->tenant);
        $this->tenantToken($this->tenant);
        $before = $this->snapshot($this->tenant, (int) $owner->id);

        $this->postJson($this->uri(), [], $this->centralHeaders($this->centralSuperAdmin()))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'central_auth.step_up_required');

        $this->assertSame($before, $this->snapshot($this->tenant, (int) $owner->id));
        $this->assertSame(0, $this->auditCount());
    }

    public function test_support_operator_is_403_and_guests_401(): void
    {
        $owner = $this->tenantAdmin($this->tenant);
        $before = $this->snapshot($this->tenant, (int) $owner->id);

        $this->postJson($this->uri(), [], $this->steppedUpCentralHeaders($this->centralSupport()))->assertStatus(403);
        $this->postJson($this->uri(), [], ['Accept' => 'application/json'])->assertStatus(401);
        // A tenant admin token is no console identity.
        $this->postJson($this->uri(), [], ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($this->tenant)])->assertStatus(401);

        $after = $this->snapshot($this->tenant, (int) $owner->id);
        $this->assertSame($before['password'], $after['password']);
        $this->assertFalse($after['flag']);
        $this->assertSame(0, $this->auditCount());
    }

    public function test_super_admin_reset_flags_the_owner_of_that_tenant_only_revokes_tokens_and_audits(): void
    {
        $operator = $this->centralSuperAdmin();
        $owner = $this->tenantAdmin($this->tenant);
        $ownerHeaders = $this->tenantHeaders($this->tenant);
        $this->tenantToken($this->tenant);
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $this->tenantToken($this->tenant, $cashier);

        $otherAdmin = $this->tenantAdmin($this->otherTenant);
        $this->tenantToken($this->otherTenant);
        $otherBefore = $this->snapshot($this->otherTenant, (int) $otherAdmin->id);

        $response = $this->postJson($this->uri(), [], $this->steppedUpCentralHeaders($operator))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('super.owner_password_reset_success'))
            ->assertJsonPath('data.tenant_id', (string) $this->tenant->getTenantKey())
            ->assertJsonPath('data.owner.id', (int) $owner->id)
            ->assertJsonPath('data.owner.phone', $owner->phone)
            ->assertJsonPath('data.matched_by', 'fallback')
            ->assertJsonPath('data.must_change_password', true);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $temporary = (string) $response->json('data.temporary_password');
        $this->assertGreaterThanOrEqual(8, strlen($temporary));

        $after = $this->snapshot($this->tenant, (int) $owner->id);
        $this->assertTrue($after['flag']);
        $this->assertSame(0, $after['tokens']);
        $this->assertTrue($this->inTenant($this->tenant, fn (): bool => Hash::check($temporary, (string) User::query()->findOrFail($owner->id)->password)));
        // Other users of the same shop keep their sessions.
        $this->assertSame(1, $this->snapshot($this->tenant, (int) $cashier->id)['tokens']);
        $this->assertFalse($this->snapshot($this->tenant, (int) $cashier->id)['flag']);
        // The other tenant is untouched (same ids are possible across tenant databases).
        $this->assertSame($otherBefore, $this->snapshot($this->otherTenant, (int) $otherAdmin->id));
        $this->assertFalse(tenancy()->initialized, 'The action must restore the central context.');

        $this->getJson('/api/v1/auth/me', $ownerHeaders)->assertStatus(401);

        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantOwnerPasswordReset->value)->sole();
        $this->assertSame((int) $operator->getKey(), (int) $log->causer_id);
        $this->assertSame((string) $this->tenant->getTenantKey(), (string) $log->tenant_id);
        $this->assertSame((int) $owner->id, (int) data_get($log->properties, 'owner_user_id'));
        $this->assertSame('fallback', data_get($log->properties, 'matched_by'));
        $this->assertStringNotContainsString($temporary, (string) json_encode($log->toArray()));

        // The owner signs in with it and must change it first.
        $this->postJson('/api/v1/auth/login', ['login' => $owner->phone, 'password' => $temporary, 'device_name' => 'spa'], [
            'Accept' => 'application/json',
            'X-Tenant' => (string) $this->tenant->getTenantKey(),
        ])->assertOk()->assertJsonPath('data.must_change_password', true);
    }

    public function test_the_admin_with_the_tenant_email_is_the_owner(): void
    {
        $firstAdmin = $this->tenantAdmin($this->tenant);
        $owner = $this->createTenantUser($this->tenant, 'admin', attributes: ['email' => (string) $this->tenant->email]);

        $this->postJson($this->uri(), [], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertOk()
            ->assertJsonPath('data.owner.id', (int) $owner->id)
            ->assertJsonPath('data.owner.email', (string) $this->tenant->email)
            ->assertJsonPath('data.matched_by', 'email');
        $this->assertSame('email', data_get(CentralAuditLog::query()->where('event', CentralAuditEvent::TenantOwnerPasswordReset->value)->sole()->properties, 'matched_by'));

        $this->assertTrue($this->snapshot($this->tenant, (int) $owner->id)['flag']);
        $this->assertFalse($this->snapshot($this->tenant, (int) $firstAdmin->id)['flag']);
    }

    public function test_unknown_tenant_is_404(): void
    {
        $this->postJson('/api/v1/super-admin/tenants/no-such-tenant/reset-owner-password', [], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(404);
        $this->assertSame(0, $this->auditCount());
    }

    public function test_inactive_admins_are_skipped_and_the_oldest_active_admin_is_the_fallback(): void
    {
        $firstAdmin = $this->tenantAdmin($this->tenant);
        $inactiveOwner = $this->createTenantUser($this->tenant, 'admin', attributes: ['email' => (string) $this->tenant->email, 'is_active' => false]);
        $secondAdmin = $this->createTenantUser($this->tenant, 'admin');
        $this->inTenant($this->tenant, fn () => User::query()->whereKey($firstAdmin->id)->update(['is_active' => false]));

        $this->postJson($this->uri(), [], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertOk()
            ->assertJsonPath('data.owner.id', (int) $secondAdmin->id)
            ->assertJsonPath('data.matched_by', 'fallback');

        $this->assertTrue($this->snapshot($this->tenant, (int) $secondAdmin->id)['flag']);
        $this->assertFalse($this->snapshot($this->tenant, (int) $inactiveOwner->id)['flag']);
        $this->assertFalse($this->snapshot($this->tenant, (int) $firstAdmin->id)['flag']);
    }

    public function test_no_active_admin_is_409_and_the_context_is_restored_after_the_failure(): void
    {
        $owner = $this->tenantAdmin($this->tenant);
        $this->inTenant($this->tenant, fn () => User::query()->whereKey($owner->id)->update(['is_active' => false]));
        $before = $this->snapshot($this->tenant, (int) $owner->id);

        $this->postJson($this->uri(), [], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(409)
            ->assertJsonPath('message', __('super.tenant_owner_not_found'));
        $this->assertSame($before, $this->snapshot($this->tenant, (int) $owner->id));
        $this->assertSame(0, $this->auditCount());

        // Thrown inside the tenant context: the caller's context comes back (central here).
        $operator = $this->centralSuperAdmin();
        $action = app(ResetTenantOwnerPasswordAction::class);
        try {
            $action->execute((string) $this->tenant->getTenantKey(), $operator);
            $this->fail('Expected a 409.');
        } catch (ConflictHttpException) {
        }
        $this->assertFalse(tenancy()->initialized);

        // ... and when the caller was inside ANOTHER tenant, that tenant is active again.
        $this->inTenant($this->otherTenant, function () use ($action, $operator): void {
            try {
                $action->execute((string) $this->tenant->getTenantKey(), $operator);
                $this->fail('Expected a 409.');
            } catch (ConflictHttpException) {
            }
            $this->assertTrue(tenancy()->initialized);
            $this->assertSame((string) $this->otherTenant->getTenantKey(), (string) tenant()?->getTenantKey());
        });
    }
}
