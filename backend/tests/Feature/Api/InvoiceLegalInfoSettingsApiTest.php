<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Setting;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * SETG-7 (tenant-settings-catalog §5 #2): the A4 invoice header used to print fake
 * phone / commercial-register / tax numbers. The real values now come from the tenant
 * settings (`company_phone`, `company_address`, `commercial_register`,
 * `tax_registration_no`) and reach every signed-in user (cashiers print invoices too)
 * through the `system` block of /system/context. Empty values stay empty so the
 * frontend hides the line.
 */
final class InvoiceLegalInfoSettingsApiTest extends TenantTestCase
{
    public function test_admin_can_save_legal_info_and_read_it_back(): void
    {
        $tenant = $this->createTenant();

        $this->postJson('/api/v1/settings', [
            'company_name' => 'محل الفاتورة',
            'commercial_register' => 'CR-12/2026',
            'tax_registration_no' => '100-200-300',
        ], $this->tenantHeaders($tenant))->assertStatus(200);

        $this->getJson('/api/v1/settings', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('settings.commercial_register', 'CR-12/2026')
            ->assertJsonPath('settings.tax_registration_no', '100-200-300');
    }

    public function test_context_exposes_real_legal_info_to_a_cashier(): void
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, function (): void {
            Setting::set('company_phone', '01000000500');
            Setting::set('company_address', 'شارع التجربة');
            Setting::set('commercial_register', 'CR-77');
            Setting::set('tax_registration_no', '987-000-111');
        });
        $cashier = $this->createTenantUser($tenant, 'cashier');

        $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant, $cashier))
            ->assertStatus(200)
            ->assertJsonPath('data.system.company_phone', '01000000500')
            ->assertJsonPath('data.system.company_address', 'شارع التجربة')
            ->assertJsonPath('data.system.commercial_register', 'CR-77')
            ->assertJsonPath('data.system.tax_registration_no', '987-000-111');
    }

    public function test_context_returns_empty_strings_when_nothing_is_set(): void
    {
        $tenant = $this->createTenant();

        $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('data.system.company_phone', '')
            ->assertJsonPath('data.system.company_address', '')
            ->assertJsonPath('data.system.commercial_register', '')
            ->assertJsonPath('data.system.tax_registration_no', '');
    }

    public function test_context_never_exposes_the_bot_token(): void
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, fn () => Setting::set('telegram_bot_token', '123456789:AAContextLeakCheck'));

        $response = $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant))->assertStatus(200);

        $this->assertStringNotContainsString('AAContextLeakCheck', (string) $response->getContent());
    }

    public function test_legal_info_does_not_leak_between_tenants(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();
        $this->inTenant($a, fn () => Setting::set('commercial_register', 'CR-TENANT-A'));

        $this->getJson('/api/v1/system/context', $this->tenantHeaders($b))
            ->assertStatus(200)
            ->assertJsonPath('data.system.commercial_register', '');
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function invalidLegalInfo(): array
    {
        return [
            'commercial register too long' => ['commercial_register', str_repeat('1', 51)],
            'commercial register not a string' => ['commercial_register', ['x']],
            'tax number too long' => ['tax_registration_no', str_repeat('1', 51)],
            'tax number not a string' => ['tax_registration_no', ['x']],
        ];
    }

    #[DataProvider('invalidLegalInfo')]
    public function test_invalid_legal_info_is_rejected(string $field, mixed $value): void
    {
        $tenant = $this->createTenant();

        $this->postJson('/api/v1/settings', ['company_name' => 'x', $field => $value], $this->tenantHeaders($tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field]);
    }
}
