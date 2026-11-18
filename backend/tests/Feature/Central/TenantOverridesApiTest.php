<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralAuditEvent;
use App\Enums\LimitResource;
use App\Models\CentralAuditLog;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;
use App\Models\TenantLimitOverride;
use App\Services\Entitlements\TenantEntitlementService;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\Concerns\SeedsFeatureRegistry;
use Tests\TenantTestCase;

/**
 * PLAN-12: console API for per-shop limit overrides and feature grant / revoke.
 */
final class TenantOverridesApiTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;
    use SeedsFeatureRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedFeatureRegistry();
    }

    private function plan(): Plan
    {
        $slug = 'ovr-'.Str::lower(Str::random(8));

        return Plan::query()->create([
            'name' => $slug, 'slug' => $slug, 'price_monthly' => '449.000', 'price_yearly' => '4490.000',
            'features' => ['pos.access' => true, 'transfers.manage' => true, 'audit.logs' => false],
            'max_users' => 5, 'max_stores' => 1, 'max_items' => null, 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    private function shop(): Tenant
    {
        return $this->createTenant(['plan_id' => $this->plan()->id]);
    }

    private function base(Tenant $tenant): string
    {
        return '/api/v1/super-admin/tenants/'.$tenant->getTenantKey();
    }

    /** @return array<string, string> */
    private function operatorHeaders(): array
    {
        return $this->steppedUpCentralHeaders($this->centralOperator());
    }

    public function test_index_lists_limits_and_features_with_plan_override_and_effective(): void
    {
        $tenant = $this->shop();
        $operator = $this->centralOperator();
        TenantLimitOverride::query()->create([
            'tenant_id' => $tenant->id, 'resource' => 'users', 'value' => 9, 'reason' => 'سبب', 'created_by' => $operator->id,
        ]);
        TenantFeatureOverride::query()->create([
            'tenant_id' => $tenant->id, 'feature_key' => 'transfers.manage', 'mode' => 'revoke', 'reason' => 'سبب',
        ]);

        $json = $this->getJson($this->base($tenant).'/overrides', $this->centralHeaders($operator))
            ->assertOk()->assertJsonPath('success', true)->json('data');

        $limits = collect($json['limits'])->keyBy('resource');
        $this->assertCount(count(LimitResource::cases()), $json['limits']);
        $this->assertSame(5, $limits['users']['plan_value']);
        $this->assertSame(9, $limits['users']['effective']);
        $this->assertTrue($limits['users']['overridden']);
        $this->assertSame(9, $limits['users']['override']['value']);
        $this->assertSame($operator->name, $limits['users']['override']['created_by']['name']);
        $this->assertNull($limits['stores']['override']);
        $this->assertSame(1, $limits['stores']['effective']);

        $features = collect($json['features'])->keyBy('key');
        $this->assertTrue($features['transfers.manage']['from_plan']);
        $this->assertFalse($features['transfers.manage']['effective']);
        $this->assertSame('revoke', $features['transfers.manage']['override']['mode']);
        $this->assertTrue($features['pos.access']['effective']);
        $this->assertNull($features['pos.access']['override']);
    }

    public function test_put_limit_creates_then_updates_and_is_audited_with_old_and_new(): void
    {
        $tenant = $this->shop();
        $headers = $this->operatorHeaders();

        $this->putJson($this->base($tenant).'/limit-overrides/users', ['value' => 20, 'reason' => 'توسعة مؤقتة'], $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.resource', 'users')
            ->assertJsonPath('data.value', 20)
            ->assertJsonPath('data.reason', 'توسعة مؤقتة');

        $expires = now()->addDays(10)->startOfSecond();
        $this->putJson($this->base($tenant).'/limit-overrides/users', ['value' => null, 'reason' => 'بلا حد', 'expires_at' => $expires->toIso8601String()], $headers)
            ->assertOk()->assertJsonPath('data.value', null)->assertJsonPath('data.is_expired', false);

        $this->assertSame(1, TenantLimitOverride::query()->where('tenant_id', $tenant->id)->count(), 'One row per (tenant, resource).');
        $this->assertNull(app(TenantEntitlementService::class)->limit($tenant, LimitResource::Users));

        $logs = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantLimitOverrideSet->value)->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertNull($logs[0]->properties['old']);
        $this->assertSame(20, $logs[0]->properties['new']['value']);
        $this->assertSame(20, $logs[1]->properties['old']['value']);
        $this->assertNull($logs[1]->properties['new']['value']);
        $this->assertSame('بلا حد', $logs[1]->properties['reason']);
        $this->assertSame((string) $tenant->id, $logs[1]->tenant_id);
    }

    public function test_delete_limit_restores_the_plan_and_is_audited_and_404s_when_absent(): void
    {
        $tenant = $this->shop();
        $headers = $this->operatorHeaders();
        $this->putJson($this->base($tenant).'/limit-overrides/users', ['value' => 20, 'reason' => 'مؤقت'], $headers)->assertOk();
        $this->assertSame(20, app(TenantEntitlementService::class)->limit($tenant, LimitResource::Users));

        $this->deleteJson($this->base($tenant).'/limit-overrides/users', [], $headers)
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('message', __('plan_overrides.removed'));

        $this->assertSame(5, app(TenantEntitlementService::class)->limit($tenant, LimitResource::Users));
        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantLimitOverrideRemoved->value)->sole();
        $this->assertSame(20, $log->properties['old']['value']);

        $this->deleteJson($this->base($tenant).'/limit-overrides/users', [], $headers)
            ->assertNotFound()->assertJsonPath('message', __('plan_overrides.not_found'));
    }

    public function test_put_and_delete_feature_override_grant_revoke_and_audit(): void
    {
        $tenant = $this->shop();
        $headers = $this->operatorHeaders();
        $service = app(TenantEntitlementService::class);

        $this->putJson($this->base($tenant).'/feature-overrides/transfers.manage', ['mode' => 'revoke', 'reason' => 'تعليق ميزة'], $headers)
            ->assertOk()->assertJsonPath('data.feature', 'transfers.manage')->assertJsonPath('data.mode', 'revoke');
        $this->assertFalse($service->isFeatureEnabled($tenant, 'transfers.manage'));

        $this->putJson($this->base($tenant).'/feature-overrides/audit.logs', ['mode' => 'grant', 'reason' => 'هدية'], $headers)->assertOk();
        $this->assertTrue($service->isFeatureEnabled($tenant, 'audit.logs'));

        $this->deleteJson($this->base($tenant).'/feature-overrides/transfers.manage', [], $headers)->assertOk();
        $this->assertTrue($service->isFeatureEnabled($tenant, 'transfers.manage'));

        $this->assertSame(2, CentralAuditLog::query()->where('event', CentralAuditEvent::TenantFeatureOverrideSet->value)->count());
        $removed = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantFeatureOverrideRemoved->value)->sole();
        $this->assertSame('revoke', $removed->properties['old']['mode']);
        $this->deleteJson($this->base($tenant).'/feature-overrides/transfers.manage', [], $headers)->assertNotFound();
    }

    public function test_a_legacy_alias_is_stored_under_the_canonical_key(): void
    {
        $tenant = $this->shop();

        $this->putJson($this->base($tenant).'/feature-overrides/blender.access', ['mode' => 'grant', 'reason' => 'تجربة'], $this->operatorHeaders())
            ->assertOk()->assertJsonPath('data.feature', 'mixes.manage');
        $this->assertDatabaseHas('tenant_feature_overrides', ['tenant_id' => $tenant->id, 'feature_key' => 'mixes.manage'], $this->centralConnectionName());
    }

    /** @return array<string, array{0: string, 1: array<string, mixed>, 2: string}> */
    public static function invalidLimitPayloads(): array
    {
        return [
            'value missing' => ['users', ['reason' => 'سبب'], 'value'],
            'value negative' => ['users', ['value' => -1, 'reason' => 'سبب'], 'value'],
            'value too big' => ['users', ['value' => 1000001, 'reason' => 'سبب'], 'value'],
            'value not integer' => ['users', ['value' => 'abc', 'reason' => 'سبب'], 'value'],
            'reason missing' => ['users', ['value' => 3], 'reason'],
            'reason too short' => ['users', ['value' => 3, 'reason' => 'ab'], 'reason'],
            'reason too long' => ['users', ['value' => 3, 'reason' => 'x'.str_repeat('y', 255)], 'reason'],
            'expiry in the past' => ['users', ['value' => 3, 'reason' => 'سبب', 'expires_at' => '2020-01-01'], 'expires_at'],
            'expiry beyond 10 years' => ['users', ['value' => 3, 'reason' => 'سبب', 'expires_at' => '2099-01-01'], 'expires_at'],
            'unknown resource' => ['gizmos', ['value' => 3, 'reason' => 'سبب'], 'resource'],
        ];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidLimitPayloads')]
    public function test_invalid_limit_payloads_are_422_and_write_nothing(string $resource, array $payload, string $field): void
    {
        $tenant = $this->shop();

        $this->putJson($this->base($tenant).'/limit-overrides/'.$resource, $payload, $this->operatorHeaders())
            ->assertStatus(422)->assertJsonValidationErrors([$field]);

        $this->assertSame(0, TenantLimitOverride::query()->count());
    }

    /** @return array<string, array{0: string, 1: array<string, mixed>, 2: string}> */
    public static function invalidFeaturePayloads(): array
    {
        return [
            'unknown feature' => ['no.such.feature', ['mode' => 'grant', 'reason' => 'سبب'], 'feature'],
            'bad mode' => ['pos.access', ['mode' => 'toggle', 'reason' => 'سبب'], 'mode'],
            'mode missing' => ['pos.access', ['reason' => 'سبب'], 'mode'],
            'reason missing' => ['pos.access', ['mode' => 'grant'], 'reason'],
            'expiry in the past' => ['pos.access', ['mode' => 'grant', 'reason' => 'سبب', 'expires_at' => '2020-01-01'], 'expires_at'],
            'expiry beyond 10 years' => ['pos.access', ['mode' => 'grant', 'reason' => 'سبب', 'expires_at' => '2099-01-01'], 'expires_at'],
        ];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidFeaturePayloads')]
    public function test_invalid_feature_payloads_are_422_and_write_nothing(string $feature, array $payload, string $field): void
    {
        $tenant = $this->shop();

        $this->putJson($this->base($tenant).'/feature-overrides/'.$feature, $payload, $this->operatorHeaders())
            ->assertStatus(422)->assertJsonValidationErrors([$field]);

        $this->assertSame(0, TenantFeatureOverride::query()->count());
    }

    public function test_delete_with_an_unknown_resource_is_422_and_an_unknown_feature_is_404(): void
    {
        $tenant = $this->shop();
        $headers = $this->operatorHeaders();

        $this->deleteJson($this->base($tenant).'/limit-overrides/gizmos', [], $headers)->assertStatus(422);
        $this->deleteJson($this->base($tenant).'/feature-overrides/no.such.feature', [], $headers)->assertNotFound();
    }

    public function test_an_override_for_a_feature_removed_from_the_catalog_can_still_be_deleted(): void
    {
        $tenant = $this->shop();
        TenantFeatureOverride::query()->create([
            'tenant_id' => $tenant->id, 'feature_key' => 'retired.feature', 'mode' => 'grant', 'reason' => 'قديمة',
        ]);

        $this->deleteJson($this->base($tenant).'/feature-overrides/retired.feature', [], $this->operatorHeaders())->assertOk();

        $this->assertSame(0, TenantFeatureOverride::query()->where('feature_key', 'retired.feature')->count());
    }

    public function test_unknown_tenant_is_404(): void
    {
        $headers = $this->operatorHeaders();

        $this->getJson('/api/v1/super-admin/tenants/no-such-tenant/overrides', $headers)->assertNotFound();
        $this->putJson('/api/v1/super-admin/tenants/no-such-tenant/limit-overrides/users', ['value' => 1, 'reason' => 'سبب'], $headers)
            ->assertNotFound()->assertJsonPath('message', __('super.tenant_not_found'));
    }

    public function test_guests_and_tenant_tokens_are_401_on_every_endpoint(): void
    {
        $tenant = $this->shop();
        $tenantAuth = ['Authorization' => 'Bearer '.$this->tenantToken($tenant)];
        $base = $this->base($tenant);

        foreach ([[], $tenantAuth] as $headers) {
            $this->getJson($base.'/overrides', $headers)->assertUnauthorized();
            $this->putJson($base.'/limit-overrides/users', ['value' => 1, 'reason' => 'سبب'], $headers)->assertUnauthorized();
            $this->deleteJson($base.'/limit-overrides/users', [], $headers)->assertUnauthorized();
            $this->putJson($base.'/feature-overrides/pos.access', ['mode' => 'grant', 'reason' => 'سبب'], $headers)->assertUnauthorized();
            $this->deleteJson($base.'/feature-overrides/pos.access', [], $headers)->assertUnauthorized();
        }
    }

    public function test_support_can_list_but_not_change(): void
    {
        $tenant = $this->shop();
        $support = $this->centralSupport();
        $headers = $this->steppedUpCentralHeaders($support);
        $base = $this->base($tenant);

        $this->getJson($base.'/overrides', $headers)->assertOk();
        $this->putJson($base.'/limit-overrides/users', ['value' => 1, 'reason' => 'سبب'], $headers)->assertForbidden();
        $this->deleteJson($base.'/limit-overrides/users', [], $headers)->assertForbidden();
        $this->putJson($base.'/feature-overrides/pos.access', ['mode' => 'revoke', 'reason' => 'سبب'], $headers)->assertForbidden();
        $this->deleteJson($base.'/feature-overrides/pos.access', [], $headers)->assertForbidden();

        $this->assertSame(0, TenantLimitOverride::query()->count() + TenantFeatureOverride::query()->count());
    }

    public function test_writes_need_a_recent_second_factor(): void
    {
        $tenant = $this->shop();

        $this->putJson($this->base($tenant).'/limit-overrides/users', ['value' => 1, 'reason' => 'سبب'], $this->centralHeaders($this->centralOperator()))
            ->assertStatus(403);
        $this->assertSame(0, TenantLimitOverride::query()->count());
    }

    public function test_an_override_on_one_shop_never_touches_another(): void
    {
        $a = $this->shop();
        $b = $this->shop();
        $headers = $this->operatorHeaders();

        $this->putJson($this->base($a).'/limit-overrides/users', ['value' => 99, 'reason' => 'محل أ'], $headers)->assertOk();
        $this->putJson($this->base($a).'/feature-overrides/pos.access', ['mode' => 'revoke', 'reason' => 'محل أ'], $headers)->assertOk();

        $service = app(TenantEntitlementService::class);
        $this->assertSame(5, $service->limit($b, LimitResource::Users));
        $this->assertTrue($service->isFeatureEnabled($b, 'pos.access'));

        $json = $this->getJson($this->base($b).'/overrides', $this->centralHeaders($this->centralOperator()))->assertOk()->json('data');
        $this->assertNull(collect($json['limits'])->firstWhere('resource', 'users')['override']);
        $this->assertNull(collect($json['features'])->firstWhere('key', 'pos.access')['override']);
        $this->assertSame(1, TenantLimitOverride::query()->where('tenant_id', $a->id)->count());
        $this->assertSame(0, TenantLimitOverride::query()->where('tenant_id', $b->id)->count());
    }

    public function test_the_legacy_toggle_endpoint_still_works_on_the_new_storage(): void
    {
        $tenant = $this->shop();

        $this->postJson($this->base($tenant).'/override-feature', ['feature_key' => 'audit.logs'], $this->operatorHeaders())->assertOk();
        $this->assertTrue(app(TenantEntitlementService::class)->isFeatureEnabled($tenant, 'audit.logs'));
    }
}
