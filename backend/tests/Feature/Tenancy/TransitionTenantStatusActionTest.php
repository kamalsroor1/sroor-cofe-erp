<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Actions\Tenants\RevokeTenantTokensAction;
use App\Actions\Tenants\TransitionTenantStatusAction;
use App\Enums\CentralAuditEvent;
use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Exceptions\TenantLifecycleException;
use App\Models\CentralAuditLog;
use App\Models\Tenant;
use App\Models\TenantLifecycleEvent;
use App\Support\Tenancy\EndsTenantImpersonationSessions;
use App\Support\Tenancy\TenantStatusTransition;
use App\Support\Tenancy\TenantStatusTransitioned;
use App\Support\Tenancy\TenantSuspensionReason;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * IDEN-3.3: TransitionTenantStatusAction + RevokeTenantTokensAction, on the
 * database-per-tenant harness (tokens live in each tenant's own DB).
 *
 * Rules covered: lock + expectFrom (409 on a stale status), state machine (409),
 * only Billing activates (403), archive only from suspended/cancelled (409 + refused
 * audit), reversible archive (unarchive restores status_before_archive, audited),
 * lifecycle event row + central audit in the same transaction, event after commit,
 * blocking revokes every tenant PAT and nothing central.
 */
final class TransitionTenantStatusActionTest extends TenantTestCase
{
    /** @var list<TenantStatusTransitioned> */
    private array $dispatched = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatched = [];
        Event::listen(TenantStatusTransitioned::class, function (TenantStatusTransitioned $event): void {
            $this->dispatched[] = $event;
        });
    }

    protected function tearDown(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        parent::tearDown();
    }

    public function test_super_admin_suspension_updates_columns_writes_history_audits_and_revokes_tenant_tokens(): void
    {
        $tenant = $this->createTenant(['status' => TenantStatus::Active->value]);
        $other = $this->createTenant(['status' => TenantStatus::Active->value]);
        $super = $this->centralSuperAdmin();
        $centralHeaders = $this->centralHeaders($super);
        $this->tenantToken($tenant);
        $this->tenantToken($tenant);
        $this->tenantToken($other);

        $result = $this->action()->execute(TenantStatusTransition::to(
            (string) $tenant->getKey(),
            TenantStatus::Suspended,
            TenantLifecycleActor::SuperAdmin,
            expectFrom: TenantStatus::Active,
            reason: TenantSuspensionReason::Violation,
            note: 'مخالفة متكررة',
            causer: $super,
        ));

        $this->assertSame(TenantStatus::Suspended->value, $result->status);
        $fresh = Tenant::query()->findOrFail($tenant->getKey());
        $this->assertSame(TenantStatus::Suspended, $fresh->lifecycleStatus());
        $this->assertSame(TenantSuspensionReason::Violation, $fresh->suspension_reason);
        $this->assertNotNull($fresh->status_changed_at);

        $event = TenantLifecycleEvent::query()->where('tenant_id', $tenant->getKey())->sole();
        $this->assertSame(TenantStatus::Active, $event->from_status);
        $this->assertSame(TenantStatus::Suspended, $event->to_status);
        $this->assertSame(TenantLifecycleActor::SuperAdmin, $event->actor);
        $this->assertSame((int) $super->getKey(), $event->central_user_id);
        $this->assertSame(TenantSuspensionReason::Violation, $event->reason);
        $this->assertSame('مخالفة متكررة', $event->note);

        $audit = CentralAuditLog::query()
            ->where('event', CentralAuditEvent::TenantStatusChanged->value)
            ->where('tenant_id', $tenant->getKey())
            ->sole();
        $this->assertSame($super->getMorphClass(), $audit->causer_type);
        $this->assertSame((int) $super->getKey(), $audit->causer_id);
        $this->assertSame('active', $audit->properties['from'] ?? null);
        $this->assertSame('suspended', $audit->properties['to'] ?? null);
        $this->assertSame('violation', $audit->properties['reason'] ?? null);
        $this->assertSame((int) $event->id, $audit->properties['lifecycle_event_id'] ?? null);

        $this->assertSame(0, $this->tenantTokenCount($tenant), 'Blocking a tenant deletes every PAT in its database.');
        $this->assertSame(1, $this->tenantTokenCount($other), 'Another tenant\'s tokens are untouched.');
        $this->assertSame(1, DB::table('central_personal_access_tokens')->count(), 'Central operator tokens are untouched.');
        $this->assertNotEmpty($centralHeaders['Authorization']);
        $this->assertFalse(tenancy()->initialized, 'Revocation must leave the central context as it found it.');

        $this->assertCount(1, $this->dispatched);
        $this->assertSame((string) $tenant->getKey(), $this->dispatched[0]->tenantId);
        $this->assertSame(TenantStatus::Active, $this->dispatched[0]->from);
        $this->assertSame(TenantStatus::Suspended, $this->dispatched[0]->to);
        $this->assertSame((int) $event->id, $this->dispatched[0]->lifecycleEventId);
    }

    public function test_only_billing_can_make_a_tenant_active(): void
    {
        $tenant = $this->createTenant(['status' => TenantStatus::Suspended->value, 'suspension_reason' => 'non_payment']);
        $super = $this->centralSuperAdmin();

        $this->assertLifecycleRefusal(403, 'subscription.activation_requires_payment', fn () => $this->action()->execute(
            TenantStatusTransition::to((string) $tenant->getKey(), TenantStatus::Active, TenantLifecycleActor::SuperAdmin, causer: $super),
        ));
        $this->assertLifecycleRefusal(403, 'subscription.activation_requires_payment', fn () => $this->action()->execute(
            TenantStatusTransition::to((string) $tenant->getKey(), TenantStatus::Active, TenantLifecycleActor::System),
        ));
        $this->assertUnchanged($tenant, TenantStatus::Suspended);

        $this->tenantToken($tenant);
        $this->action()->execute(TenantStatusTransition::to(
            (string) $tenant->getKey(),
            TenantStatus::Active,
            TenantLifecycleActor::Billing,
            context: ['billing_payment_id' => 42],
        ));

        $fresh = Tenant::query()->findOrFail($tenant->getKey());
        $this->assertSame(TenantStatus::Active, $fresh->lifecycleStatus());
        $this->assertNull($fresh->suspension_reason, 'An active tenant carries no suspension reason.');
        $this->assertNull($fresh->read_only_since);
        $this->assertSame(1, $this->tenantTokenCount($tenant), 'Activation never revokes tokens.');

        $event = TenantLifecycleEvent::query()->where('tenant_id', $tenant->getKey())->sole();
        $this->assertSame(TenantLifecycleActor::Billing, $event->actor);
        $this->assertNull($event->central_user_id);
        $this->assertSame(['billing_payment_id' => 42], $event->properties);
    }

    public function test_a_transition_missing_from_the_state_machine_is_409_and_writes_nothing(): void
    {
        $tenant = $this->createTenant(['status' => TenantStatus::Trial->value]);
        $super = $this->centralSuperAdmin();
        $this->tenantToken($tenant);

        // trial -> past_due exists for nobody; active -> past_due is System only.
        $this->assertLifecycleRefusal(409, 'subscription.invalid_transition', fn () => $this->action()->execute(
            TenantStatusTransition::to((string) $tenant->getKey(), TenantStatus::PastDue, TenantLifecycleActor::SuperAdmin, causer: $super),
        ));
        $this->assertLifecycleRefusal(409, 'subscription.invalid_transition', fn () => $this->action()->execute(
            TenantStatusTransition::to((string) $tenant->getKey(), TenantStatus::Trial, TenantLifecycleActor::SuperAdmin, causer: $super),
        ));

        $this->assertUnchanged($tenant, TenantStatus::Trial);
        $this->assertSame(1, $this->tenantTokenCount($tenant));
        $this->assertSame(0, CentralAuditLog::query()->where('tenant_id', $tenant->getKey())->count());
    }

    public function test_a_stale_expected_status_is_409_status_conflict(): void
    {
        $tenant = $this->createTenant(['status' => TenantStatus::ReadOnly->value]);
        $super = $this->centralSuperAdmin();

        try {
            $this->action()->execute(TenantStatusTransition::to(
                (string) $tenant->getKey(),
                TenantStatus::Suspended,
                TenantLifecycleActor::SuperAdmin,
                expectFrom: [TenantStatus::Active, TenantStatus::Trial],
                reason: TenantSuspensionReason::Other,
                causer: $super,
            ));
            $this->fail('A stale expectFrom must be refused.');
        } catch (TenantLifecycleException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertSame('subscription.status_conflict', $e->errorCode());
            $this->assertSame(['expected' => ['active', 'trial'], 'current' => 'read_only'], $e->details());
        }

        $this->assertUnchanged($tenant, TenantStatus::ReadOnly);
    }

    public function test_a_super_admin_suspension_needs_a_reason_and_the_sweep_defaults_to_non_payment(): void
    {
        $tenant = $this->createTenant(['status' => TenantStatus::ReadOnly->value]);
        $super = $this->centralSuperAdmin();

        $this->assertLifecycleRefusal(422, 'subscription.suspension_reason_required', fn () => $this->action()->execute(
            TenantStatusTransition::to((string) $tenant->getKey(), TenantStatus::Suspended, TenantLifecycleActor::SuperAdmin, causer: $super),
        ));
        $this->assertUnchanged($tenant, TenantStatus::ReadOnly);

        $this->action()->execute(TenantStatusTransition::to(
            (string) $tenant->getKey(),
            TenantStatus::Suspended,
            TenantLifecycleActor::System,
            expectFrom: TenantStatus::ReadOnly,
        ));

        $fresh = Tenant::query()->findOrFail($tenant->getKey());
        $this->assertSame(TenantStatus::Suspended, $fresh->lifecycleStatus());
        $this->assertSame(TenantSuspensionReason::NonPayment, $fresh->suspension_reason);

        $audit = CentralAuditLog::query()->where('tenant_id', $tenant->getKey())->sole();
        $this->assertNull($audit->causer_id, 'System moves have no operator.');
        $this->assertSame('system', $audit->properties['actor'] ?? null);
    }

    public function test_archive_from_active_is_refused_with_409_and_the_refusal_is_audited(): void
    {
        $tenant = $this->createTenant(['status' => TenantStatus::Active->value]);
        $super = $this->centralSuperAdmin();
        $this->tenantToken($tenant);

        $this->assertLifecycleRefusal(409, 'subscription.archive_not_allowed', fn () => $this->action()->execute(
            TenantStatusTransition::to((string) $tenant->getKey(), TenantStatus::Archived, TenantLifecycleActor::SuperAdmin, causer: $super),
        ));

        $this->assertUnchanged($tenant, TenantStatus::Active);
        $this->assertSame(1, $this->tenantTokenCount($tenant));

        $refused = CentralAuditLog::query()
            ->where('event', CentralAuditEvent::TenantArchiveRefused->value)
            ->where('tenant_id', $tenant->getKey())
            ->sole();
        $this->assertSame((int) $super->getKey(), $refused->causer_id);
        $this->assertSame('active', $refused->properties['from'] ?? null);
        $this->assertFalse(
            CentralAuditLog::query()->where('event', CentralAuditEvent::TenantArchived->value)->exists(),
            'The refused archive must not leave a success audit behind.',
        );
    }

    public function test_archive_is_reversible_and_unarchive_restores_the_previous_status(): void
    {
        $tenant = $this->createTenant([
            'status' => TenantStatus::Suspended->value,
            'suspension_reason' => TenantSuspensionReason::Violation->value,
        ]);
        $super = $this->centralSuperAdmin();
        $id = (string) $tenant->getKey();

        $this->action()->execute(TenantStatusTransition::to($id, TenantStatus::Archived, TenantLifecycleActor::SuperAdmin, expectFrom: TenantStatus::Suspended, causer: $super));

        $archived = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantStatus::Archived, $archived->lifecycleStatus());
        $this->assertSame(TenantStatus::Suspended, $archived->status_before_archive);
        $this->assertSame(TenantSuspensionReason::Violation, $archived->suspension_reason, 'The reason survives the archive.');
        $this->assertTrue(CentralAuditLog::query()->where('event', CentralAuditEvent::TenantArchived->value)->where('tenant_id', $id)->exists());

        // Leaving archived is unarchive only, never a direct move.
        $this->assertLifecycleRefusal(409, 'subscription.invalid_transition', fn () => $this->action()->execute(
            TenantStatusTransition::to($id, TenantStatus::Cancelled, TenantLifecycleActor::SuperAdmin, reason: TenantSuspensionReason::Other, causer: $super),
        ));

        $this->action()->execute(TenantStatusTransition::unarchive($id, $super, note: 'طلب العميل استرجاع الحساب'));

        $restored = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantStatus::Suspended, $restored->lifecycleStatus());
        $this->assertNull($restored->status_before_archive);
        $this->assertSame(TenantSuspensionReason::Violation, $restored->suspension_reason);

        $unarchived = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantUnarchived->value)->where('tenant_id', $id)->sole();
        $this->assertSame('archived', $unarchived->properties['from'] ?? null);
        $this->assertSame('suspended', $unarchived->properties['to'] ?? null);
        $this->assertSame((int) $super->getKey(), $unarchived->causer_id);

        $history = TenantLifecycleEvent::query()->where('tenant_id', $id)->orderBy('id')->get();
        $this->assertSame(
            [['suspended', 'archived'], ['archived', 'suspended']],
            $history->map(fn (TenantLifecycleEvent $e): array => [$e->from_status?->value, $e->to_status->value])->all(),
        );

        $this->assertLifecycleRefusal(409, 'subscription.not_archived', fn () => $this->action()->execute(
            TenantStatusTransition::unarchive($id, $super),
        ));
    }

    public function test_cancelled_tenant_archives_and_unarchives_back_to_cancelled(): void
    {
        $tenant = $this->createTenant(['status' => TenantStatus::Cancelled->value]);
        $super = $this->centralSuperAdmin();
        $id = (string) $tenant->getKey();

        $this->action()->execute(TenantStatusTransition::to($id, TenantStatus::Archived, TenantLifecycleActor::System));
        $this->action()->execute(TenantStatusTransition::unarchive($id, $super));

        $this->assertSame(TenantStatus::Cancelled, Tenant::query()->findOrFail($id)->lifecycleStatus());
    }

    public function test_the_event_and_the_token_revocation_wait_for_the_outer_commit(): void
    {
        $tenant = $this->createTenant(['status' => TenantStatus::Active->value]);
        $super = $this->centralSuperAdmin();
        $this->tenantToken($tenant);

        $central = DB::connection($this->centralConnectionName());

        try {
            $central->transaction(function () use ($tenant, $super): void {
                $this->action()->execute(TenantStatusTransition::to(
                    (string) $tenant->getKey(),
                    TenantStatus::Cancelled,
                    TenantLifecycleActor::SuperAdmin,
                    reason: TenantSuspensionReason::CustomerRequest,
                    causer: $super,
                ));

                $this->assertSame([], $this->dispatched, 'Nothing is dispatched before the outer commit.');
                $this->assertSame(1, $this->tenantTokenCount($tenant), 'Tokens are not revoked before the outer commit.');

                throw new RuntimeException('caller rolls back');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertUnchanged($tenant, TenantStatus::Active);
        $this->assertSame([], $this->dispatched, 'A rolled-back transition dispatches nothing.');
        $this->assertSame(1, $this->tenantTokenCount($tenant), 'A rolled-back transition revokes nothing.');
        $this->assertSame(0, CentralAuditLog::query()->where('tenant_id', $tenant->getKey())->count());
    }

    public function test_unknown_tenant_is_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->action()->execute(TenantStatusTransition::to('qa-missing-tenant', TenantStatus::ReadOnly, TenantLifecycleActor::System));
    }

    public function test_revoke_restores_the_previous_tenant_context_even_when_deleting_fails(): void
    {
        $tenant = $this->createTenant();
        $other = $this->createTenant();
        $this->tenantToken($tenant);

        tenancy()->initialize($other);

        try {
            $this->assertSame(1, app(RevokeTenantTokensAction::class)->execute($tenant));
            $this->assertTrue(tenancy()->initialized);
            $this->assertSame((string) $other->getKey(), (string) tenancy()->tenant?->getTenantKey());

            Sanctum::usePersonalAccessTokenModel(ExplodingPersonalAccessToken::class);

            try {
                app(RevokeTenantTokensAction::class)->execute($tenant);
                $this->fail('The failing delete must propagate.');
            } catch (RuntimeException $e) {
                $this->assertSame('token store unavailable', $e->getMessage());
            }

            $this->assertSame((string) $other->getKey(), (string) tenancy()->tenant?->getTenantKey(), 'finally restores the previous tenant.');
        } finally {
            $this->endTenancy();
        }

        try {
            app(RevokeTenantTokensAction::class)->execute($tenant);
        } catch (RuntimeException) {
            // expected
        }
        $this->assertFalse(tenancy()->initialized, 'finally ends tenancy when it started in central context.');
    }

    public function test_revoke_calls_the_impersonation_hook_when_it_is_bound(): void
    {
        $tenant = $this->createTenant();
        $hook = new class implements EndsTenantImpersonationSessions
        {
            /** @var list<string> */
            public array $ended = [];

            public function endAllForTenant(Tenant $tenant): int
            {
                $this->ended[] = (string) $tenant->getKey();

                return 1;
            }
        };
        $this->app->instance(EndsTenantImpersonationSessions::class, $hook);

        app(RevokeTenantTokensAction::class)->execute($tenant);

        $this->assertSame([(string) $tenant->getKey()], $hook->ended);
    }

    private function action(): TransitionTenantStatusAction
    {
        return app(TransitionTenantStatusAction::class);
    }

    private function tenantTokenCount(Tenant $tenant): int
    {
        return $this->inTenant($tenant, fn (): int => PersonalAccessToken::query()->count());
    }

    private function assertUnchanged(Tenant $tenant, TenantStatus $status): void
    {
        $this->assertSame($status->value, Tenant::query()->findOrFail($tenant->getKey())->status);
        $this->assertSame(0, TenantLifecycleEvent::query()->where('tenant_id', $tenant->getKey())->count());
    }

    private function assertLifecycleRefusal(int $status, string $errorCode, Closure $call): void
    {
        try {
            $call();
            $this->fail("Expected a {$errorCode} refusal.");
        } catch (TenantLifecycleException $e) {
            $this->assertSame($status, $e->getStatusCode());
            $this->assertSame($errorCode, $e->errorCode());
            $this->assertNotSame('', $e->getMessage());
            $this->assertStringNotContainsString('subscription.errors.', $e->getMessage(), 'The message must be translated.');
        }
    }
}

/**
 * Token model whose delete fails, to prove RevokeTenantTokensAction restores the context.
 */
final class ExplodingPersonalAccessToken extends PersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    public function newEloquentBuilder($query): never
    {
        throw new RuntimeException('token store unavailable');
    }
}
