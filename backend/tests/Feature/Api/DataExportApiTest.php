<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\DataExportStatus;
use App\Jobs\BuildTenantDataExportJob;
use App\Models\Customer;
use App\Models\DataExport;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TenantTestCase;
use ZipArchive;

/**
 * PRD-17: "Export my data" (Settings). Admin-only, queued, ZIP of CSVs on the tenant's private
 * disk, downloaded through a short-lived relative signed link. Covers 200/202, 401, 403
 * (cashier, non-admin with settings.manage, unsigned / tampered link), 422 (already running),
 * 410 (expired), tenant isolation, and that no password / token ever lands in the archive.
 */
final class DataExportApiTest extends TenantTestCase
{
    private const ITEM_NAME = 'بن برازيلي تصدير';

    private const CUSTOMER_NAME = 'كافيه التصدير';

    private function seedShopData(Tenant $tenant, string $itemName = self::ITEM_NAME, string $customerName = self::CUSTOMER_NAME): void
    {
        $this->inTenant($tenant, function () use ($itemName, $customerName): void {
            Item::create([
                'name' => $itemName,
                'code' => 'EXP-'.random_int(1000, 9999),
                'category' => 'coffee_beans',
                'cost_price' => '300.000',
                'selling_price' => '=1+1',
                'current_stock' => '50.000',
                'min_stock_level' => '1.000',
                'is_active' => true,
            ]);
            Customer::create([
                'name' => $customerName,
                'phone' => '01000007999',
                'current_balance' => '125.500',
                'is_active' => true,
            ]);
        });
    }

    /** @return TestResponse<Response> */
    private function requestExport(Tenant $tenant, ?User $user = null): TestResponse
    {
        return $this->postJson('/api/v1/settings/data-export', [], $this->tenantHeaders($tenant, $user));
    }

    /**
     * @return array<string, string> file name => content
     */
    private function unzip(string $binary): array
    {
        $path = tempnam(sys_get_temp_dir(), 'dex');
        file_put_contents($path, $binary);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $files[$name] = (string) $zip->getFromIndex($i);
        }
        $zip->close();
        @unlink($path);

        return $files;
    }

    public function test_admin_exports_the_shop_data_as_a_zip_of_csvs_without_secrets(): void
    {
        $tenant = $this->createTenant();
        $this->seedShopData($tenant);
        $adminId = (int) $this->tenantAdmin($tenant)->id;
        $hash = $this->inTenant($tenant, function () use ($adminId): string {
            $hash = Hash::make('super-secret-pass');
            User::query()->whereKey($adminId)->update(['password' => $hash]);

            return $hash;
        });

        $response = $this->requestExport($tenant)
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', DataExportStatus::Ready->value)
            ->assertJsonPath('data.is_downloadable', true)
            ->assertJsonMissingPath('data.file_path');

        $url = (string) $response->json('data.download_url');
        $this->assertStringStartsWith('/api/v1/settings/data-export/', $url);
        $this->assertStringContainsString('signature=', $url);

        $this->getJson('/api/v1/settings/data-export', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.is_downloadable', true);

        $download = $this->get($url, $this->tenantHeaders($tenant))->assertOk();
        $this->assertStringContainsString('application/zip', (string) $download->headers->get('Content-Type'));

        $files = $this->unzip($download->streamedContent());

        foreach (['items.csv', 'customers.csv', 'invoices.csv', 'invoice_items.csv', 'purchases.csv', 'returns.csv', 'payments.csv', 'expenses.csv', 'stock_movements.csv', 'users.csv', 'manifest.csv'] as $expected) {
            $this->assertArrayHasKey($expected, $files, $expected.' missing from the export');
        }
        $this->assertArrayNotHasKey('settings.csv', $files);
        $this->assertArrayNotHasKey('personal_access_tokens.csv', $files);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $files['items.csv']);
        $this->assertStringContainsString(self::ITEM_NAME, $files['items.csv']);
        // CSV injection guard: a formula-looking value is neutralised.
        $this->assertStringContainsString("\"'=1+1\"", $files['items.csv']);
        $this->assertStringContainsString(self::CUSTOMER_NAME, $files['customers.csv']);
        // Money is exported as stored (DECIMAL string; sqlite drops trailing zeros).
        $this->assertMatchesRegularExpression('/"125\.5(00)?"/', $files['customers.csv']);

        $usersHeader = strtok(substr($files['users.csv'], 3), "\r\n");
        $this->assertIsString($usersHeader);
        foreach (['password', 'api_token', 'remember_token', 'pos_pin'] as $secret) {
            $this->assertStringNotContainsString('"'.$secret.'"', $usersHeader);
        }

        $all = implode("\n", $files);
        $this->assertStringNotContainsString($hash, $all);
        $this->assertStringNotContainsString('$2y$', $all);

        // The archive is on the tenant's private disk, never in public/.
        $path = $this->inTenant($tenant, fn (): ?string => DataExport::query()->latest('id')->value('file_path'));
        $this->assertNotNull($path);
        $this->assertStringStartsWith('data-exports/', (string) $path);
        $this->assertTrue($this->inTenant($tenant, fn (): bool => Storage::disk('local')->exists((string) $path)));
    }

    public function test_request_is_queued_on_the_tenant(): void
    {
        Bus::fake([BuildTenantDataExportJob::class]);
        $tenant = $this->createTenant();

        $this->requestExport($tenant)
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.download_url', null);

        Bus::assertDispatched(BuildTenantDataExportJob::class);
    }

    public function test_show_returns_null_before_any_export(): void
    {
        $tenant = $this->createTenant();

        $this->getJson('/api/v1/settings/data-export', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_a_second_request_while_one_is_running_is_422(): void
    {
        Bus::fake([BuildTenantDataExportJob::class]);
        $tenant = $this->createTenant();

        $this->requestExport($tenant)->assertStatus(202);
        $this->requestExport($tenant)->assertStatus(422)->assertJsonValidationErrors('export');
    }

    public function test_a_stale_running_export_does_not_block_a_new_one(): void
    {
        Bus::fake([BuildTenantDataExportJob::class]);
        $tenant = $this->createTenant();
        $this->requestExport($tenant)->assertStatus(202);

        $this->inTenant($tenant, fn () => DataExport::query()->update(['updated_at' => now()->subHours(3)]));

        $this->requestExport($tenant)->assertStatus(202);
        $this->assertSame(1, $this->inTenant($tenant, fn (): int => DataExport::query()->where('status', 'failed')->count()));
    }

    public function test_guest_gets_401(): void
    {
        $tenant = $this->createTenant();

        $this->postJson('/api/v1/settings/data-export', [], $this->tenantGuestHeaders($tenant))->assertStatus(401);
        $this->getJson('/api/v1/settings/data-export', $this->tenantGuestHeaders($tenant))->assertStatus(401);
    }

    public function test_cashier_gets_403(): void
    {
        $tenant = $this->createTenant();
        $cashier = $this->createTenantUser($tenant, 'cashier');

        $this->requestExport($tenant, $cashier)->assertStatus(403);
        $this->getJson('/api/v1/settings/data-export', $this->tenantHeaders($tenant, $cashier))->assertStatus(403);
    }

    public function test_non_admin_with_settings_manage_gets_403(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createTenantUser($tenant, 'accountant', ['settings.manage']);

        $this->requestExport($tenant, $manager)->assertStatus(403);
    }

    public function test_cashier_cannot_use_an_admin_signed_link(): void
    {
        $tenant = $this->createTenant();
        $url = (string) $this->requestExport($tenant)->assertStatus(202)->json('data.download_url');
        $cashier = $this->createTenantUser($tenant, 'cashier');

        $this->get($url, $this->tenantHeaders($tenant, $cashier))->assertStatus(403);
    }

    public function test_download_without_a_valid_signature_is_refused(): void
    {
        $tenant = $this->createTenant();
        $url = (string) $this->requestExport($tenant)->assertStatus(202)->json('data.download_url');
        $id = $this->inTenant($tenant, fn (): int => (int) DataExport::query()->latest('id')->value('id'));

        $this->get('/api/v1/settings/data-export/'.$id.'/download', $this->tenantHeaders($tenant))->assertStatus(403);
        $this->get(str_replace('signature=', 'signature=0', $url), $this->tenantHeaders($tenant))->assertStatus(403);
    }

    public function test_expired_export_is_410_and_has_no_link(): void
    {
        $tenant = $this->createTenant();
        $url = (string) $this->requestExport($tenant)->assertStatus(202)->json('data.download_url');

        $this->inTenant($tenant, fn () => DataExport::query()->update(['expires_at' => now()->subMinute()]));

        $this->get($url, $this->tenantHeaders($tenant))->assertStatus(410);
        $this->getJson('/api/v1/settings/data-export', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.is_downloadable', false)
            ->assertJsonPath('data.download_url', null);
    }

    public function test_a_new_export_removes_the_previous_archive(): void
    {
        $tenant = $this->createTenant();
        $this->requestExport($tenant)->assertStatus(202);
        $firstPath = (string) $this->inTenant($tenant, fn (): ?string => DataExport::query()->oldest('id')->value('file_path'));

        $this->requestExport($tenant)->assertStatus(202);

        $this->assertFalse($this->inTenant($tenant, fn (): bool => Storage::disk('local')->exists($firstPath)));
        $this->assertSame('expired', $this->inTenant($tenant, fn (): string => DataExport::query()->oldest('id')->firstOrFail()->status->value));
    }

    public function test_tenant_isolation(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $this->seedShopData($tenantA, 'صنف المحل أ', 'عميل المحل أ');
        $this->seedShopData($tenantB, 'صنف المحل ب', 'عميل المحل ب');

        $urlA = (string) $this->requestExport($tenantA)->assertStatus(202)->json('data.download_url');

        // B sees no export, and A's signed link resolves nothing inside B.
        $this->getJson('/api/v1/settings/data-export', $this->tenantHeaders($tenantB))
            ->assertOk()
            ->assertJsonPath('data', null);
        $this->get($urlA, $this->tenantHeaders($tenantB))->assertStatus(404);

        // A's archive only holds A's data.
        $files = $this->unzip($this->get($urlA, $this->tenantHeaders($tenantA))->assertOk()->streamedContent());
        $this->assertStringContainsString('صنف المحل أ', $files['items.csv']);
        $this->assertStringNotContainsString('صنف المحل ب', $files['items.csv']);
        $this->assertStringNotContainsString('عميل المحل ب', $files['customers.csv']);
    }
}
