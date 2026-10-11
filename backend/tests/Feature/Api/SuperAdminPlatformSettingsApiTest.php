<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * IDEN-1.8 (split from SuperAdminApiTest): platform settings + global units of the control
 * plane (GET: super_admin.settings.view, POST: super_admin.settings.manage), as an
 * App\Models\CentralUser. `support` reads, never writes.
 *
 * A tenant token is 401 here (was 403 before IDEN-1.4): see SuperAdminTenantsApiTest.
 */
final class SuperAdminPlatformSettingsApiTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const SETTINGS_PAYLOAD = [
        'platform_name' => 'منظومة سرور كلاود ERP',
        'platform_subtitle' => 'المنصة السحابية الموحدة للمحامص والمقاهي',
        'support_email' => 'support@sroor-erp.test',
        'support_phone' => self::ADMIN_PHONE,
    ];

    private const UNITS = ['كجم', 'جرام', 'شيكارة', 'علبة', 'طرد', 'دستة', 'باكت'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
    }

    public function test_can_get_and_update_platform_settings(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $this->getJson('/api/v1/super-admin/settings', $headers)
            ->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['platform_name', 'platform_subtitle', 'support_email', 'support_phone']]);

        $this->postJson('/api/v1/super-admin/settings', self::SETTINGS_PAYLOAD, $headers)
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => self::SETTINGS_PAYLOAD]);

        // Persisted: a fresh read (new request, new token) returns the saved values.
        $this->getJson('/api/v1/super-admin/settings', $this->centralHeaders($this->centralSuperAdmin()))
            ->assertStatus(200)
            ->assertJson(['data' => self::SETTINGS_PAYLOAD]);
    }

    public function test_update_platform_settings_validates_input(): void
    {
        $this->postJson('/api/v1/super-admin/settings', [
            'platform_name' => '',
            'support_email' => 'not-an-email',
        ], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['platform_name', 'support_email']);
    }

    public function test_legacy_update_rejects_markup_and_badly_formatted_contacts_like_the_new_endpoint(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $before = $this->getJson('/api/v1/super-admin/settings', $headers)->assertStatus(200)->json('data');

        $this->postJson('/api/v1/super-admin/settings', [
            'platform_name' => '<script>x</script>',
            'platform_subtitle' => 'سطر <b>عريض</b>',
            'support_email' => 'support@',
            'support_phone' => 'call me <now>',
        ], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['platform_name', 'platform_subtitle', 'support_email', 'support_phone']);

        $this->postJson('/api/v1/super-admin/settings', [
            'platform_name' => 'منصة سرور',
            'support_phone' => '+20 '.str_repeat('1', 30),
        ], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['support_phone']);

        $this->assertSame($before, $this->getJson('/api/v1/super-admin/settings', $headers)->json('data'));
    }

    public function test_can_get_and_update_system_units(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $this->getJson('/api/v1/super-admin/units', $headers)
            ->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['units']);

        $this->postJson('/api/v1/super-admin/units', ['units' => self::UNITS], $headers)
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSame(self::UNITS, $this->getJson('/api/v1/super-admin/units', $headers)->assertStatus(200)->json('units'));
    }

    public function test_update_units_validates_input(): void
    {
        $this->postJson('/api/v1/super-admin/units', ['units' => 'كجم'], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['units']);
    }

    public function test_support_reads_settings_and_units_but_cannot_write(): void
    {
        $superAdmin = $this->centralHeaders($this->centralSuperAdmin());
        $before = $this->getJson('/api/v1/super-admin/settings', $superAdmin)->assertStatus(200)->json('data');
        $unitsBefore = $this->getJson('/api/v1/super-admin/units', $superAdmin)->assertStatus(200)->json('units');

        $support = $this->centralHeaders($this->centralSupport());

        $this->getJson('/api/v1/super-admin/settings', $support)->assertStatus(200);
        $this->getJson('/api/v1/super-admin/units', $support)->assertStatus(200);
        $this->postJson('/api/v1/super-admin/settings', self::SETTINGS_PAYLOAD, $support)->assertStatus(403);
        $this->postJson('/api/v1/super-admin/units', ['units' => self::UNITS], $support)->assertStatus(403);

        $this->assertSame($before, $this->getJson('/api/v1/super-admin/settings', $superAdmin)->json('data'));
        $this->assertSame($unitsBefore, $this->getJson('/api/v1/super-admin/units', $superAdmin)->json('units'));
    }

    public function test_guest_tenant_and_legacy_tokens_are_401(): void
    {
        $tenant = $this->createTenant();
        $tenantBearer = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($tenant)];
        $legacyBearer = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->legacyUsersTableSuperAdmin()->createToken('legacy')->plainTextToken];

        $this->getJson('/api/v1/super-admin/settings')->assertStatus(401);
        $this->postJson('/api/v1/super-admin/units', ['units' => self::UNITS])->assertStatus(401);

        foreach ([$tenantBearer, $legacyBearer] as $headers) {
            $this->getJson('/api/v1/super-admin/settings', $headers)->assertStatus(401);
            $this->postJson('/api/v1/super-admin/settings', self::SETTINGS_PAYLOAD, $headers)->assertStatus(401);
            $this->postJson('/api/v1/super-admin/units', ['units' => self::UNITS], $headers)->assertStatus(401);
        }
    }

    public function test_operator_without_role_is_403(): void
    {
        $headers = $this->centralHeaders($this->centralOperator(null));

        $this->getJson('/api/v1/super-admin/settings', $headers)->assertStatus(403);
        $this->getJson('/api/v1/super-admin/units', $headers)->assertStatus(403);
    }
}
