<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

class ActivityLogApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    /** @var array<string, string> */
    protected array $adminHeaders;

    protected User $regularUser;

    /** @var array<string, string> */
    protected array $regularHeaders;

    protected int $mainStoreId;

    protected int $branchStoreId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->mainStoreId = (int) $this->tenantStore($this->tenant)->id;
        $this->branchStoreId = $this->inTenant($this->tenant, fn (): int => Store::create([
            'name' => 'فرع المعادي',
            'code' => 'MAADI',
            'type' => 'branch',
            'is_default' => false,
            'is_active' => true,
        ])->id);

        $this->adminUser = $this->tenantAdmin($this->tenant);
        $this->adminHeaders = $this->tenantHeaders($this->tenant);

        // A cashier: has a role, but not logs.view.
        $this->regularUser = $this->createTenantUser($this->tenant, 'cashier', attributes: [
            'name' => 'أحمد كاشير',
            'default_store_id' => $this->branchStoreId,
        ]);
        $this->regularHeaders = $this->tenantHeaders($this->tenant, $this->regularUser, $this->branchStoreId);

        $this->inTenant($this->tenant, fn () => ActivityLog::query()->delete());
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/activity-logs', $this->tenantGuestHeaders($this->tenant));
        $response->assertStatus(401);
    }

    public function test_user_without_logs_view_permission_is_forbidden(): void
    {
        $response = $this->getJson('/api/v1/activity-logs', $this->regularHeaders);

        $response->assertStatus(403);
    }

    public function test_authorized_admin_can_fetch_logs_with_complete_structure(): void
    {
        $this->log([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->mainStoreId,
            'module' => 'sales',
            'action' => 'invoice_created',
            'description' => 'إصدار فاتورة مبيعات رقم #INV-1001',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Test Suite',
        ]);

        $response = $this->getJson('/api/v1/activity-logs', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'module',
                        'module_label',
                        'module_color',
                        'module_icon',
                        'action',
                        'description',
                        'user_name',
                        'user_phone',
                        'store_name',
                        'ip_address',
                        'created_at',
                        'time_ago',
                    ],
                ],
                'stats' => [
                    'today_total',
                    'today_critical',
                    'today_users',
                    'today_stores',
                ],
                'total_count',
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
                'users',
                'stores',
                'modules_list',
            ]);
    }

    public function test_log_payload_carries_the_stored_properties(): void
    {
        $this->log([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->mainStoreId,
            'module' => 'shifts',
            'action' => 'shift_closed',
            'description' => 'إغلاق وردية',
            'properties' => ['expected_cash' => '2100.000', 'difference' => '-5.000'],
        ]);

        $response = $this->getJson('/api/v1/activity-logs', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.properties.difference', '-5.000')
            ->assertJsonPath('data.0.payload.expected_cash', '2100.000')
            ->assertJsonPath('data.0.payload.difference', '-5.000');
    }

    public function test_can_filter_logs_by_search_keyword(): void
    {
        $this->log([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->mainStoreId,
            'module' => 'sales',
            'action' => 'invoice_created',
            'description' => 'بيع بن حبوب كولومبي فاخر',
            'ip_address' => '192.168.1.50',
        ]);

        $this->log([
            'user_id' => $this->regularUser->id,
            'store_id' => $this->branchStoreId,
            'module' => 'expenses',
            'action' => 'expense_paid',
            'description' => 'سداد فاتورة كهرباء الفرع',
            'ip_address' => '10.0.0.1',
        ]);

        $response = $this->getJson('/api/v1/activity-logs?search=كولومبي', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'بيع بن حبوب كولومبي فاخر');
    }

    public function test_can_filter_logs_by_module_and_action(): void
    {
        $this->log([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->mainStoreId,
            'module' => 'sales',
            'action' => 'invoice_cancelled',
            'description' => 'إلغاء فاتورة مبيعات #INV-999',
        ]);

        $this->log([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->mainStoreId,
            'module' => 'inventory',
            'action' => 'stock_adjusted',
            'description' => 'تسوية رصيد بن اسبريسو',
        ]);

        $response = $this->getJson('/api/v1/activity-logs?module=sales&action=invoice_cancelled', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'invoice_cancelled');
    }

    public function test_can_filter_logs_by_user_and_store(): void
    {
        $this->log([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->mainStoreId,
            'module' => 'auth',
            'action' => 'login',
            'description' => 'تسجيل دخول ناجح للمدير',
        ]);

        $this->log([
            'user_id' => $this->regularUser->id,
            'store_id' => $this->branchStoreId,
            'module' => 'shifts',
            'action' => 'shift_open',
            'description' => 'فتح وردية كاشير جديدة',
        ]);

        $response = $this->getJson('/api/v1/activity-logs?user_id='.$this->regularUser->id.'&store_id='.$this->branchStoreId, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_name', 'أحمد كاشير')
            ->assertJsonPath('data.0.store_name', 'فرع المعادي');
    }

    public function test_can_filter_logs_by_date_range(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $pastLog = ActivityLog::create([
                'user_id' => $this->adminUser->id,
                'store_id' => $this->mainStoreId,
                'module' => 'sales',
                'action' => 'sale',
                'description' => 'عملية سابقة',
            ]);
            $pastLog->timestamps = false;
            $pastLog->created_at = now()->subDays(5);
            $pastLog->save();
        });

        $this->log([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->mainStoreId,
            'module' => 'sales',
            'action' => 'sale',
            'description' => 'عملية اليوم',
        ]);

        $response = $this->getJson('/api/v1/activity-logs?from_date='.now()->toDateString().'&to_date='.now()->toDateString(), $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'عملية اليوم');
    }

    public function test_returns_accurate_today_statistics(): void
    {
        $this->log([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->mainStoreId,
            'module' => 'sales',
            'action' => 'invoice_created',
            'description' => 'فاتورة عادية',
        ]);

        $this->log([
            'user_id' => $this->regularUser->id,
            'store_id' => $this->branchStoreId,
            'module' => 'sales',
            'action' => 'cancelled',
            'description' => 'إلغاء حرج',
        ]);

        $response = $this->getJson('/api/v1/activity-logs', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonPath('stats.today_total', 2)
            ->assertJsonPath('stats.today_critical', 1)
            ->assertJsonPath('stats.today_users', 2)
            ->assertJsonPath('stats.today_stores', 2);
    }

    public function test_validation_fails_on_invalid_date_format(): void
    {
        $response = $this->getJson('/api/v1/activity-logs?from_date=invalid-date', $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['from_date']);
    }

    public function test_handles_empty_logs_and_pagination_limits(): void
    {
        $response = $this->getJson('/api/v1/activity-logs?per_page=10', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('total_count', 0)
            ->assertJsonPath('pagination.per_page', 10);
    }

    public function test_authorized_admin_can_export_logs_as_csv(): void
    {
        $this->log([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->mainStoreId,
            'module' => 'sales',
            'action' => 'export_test',
            'description' => 'اختبار تصدير ملف إكسل و CSV',
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->get('/api/v1/activity-logs/export-csv', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_unauthorized_user_cannot_export_csv(): void
    {
        $response = $this->getJson('/api/v1/activity-logs/export-csv', $this->regularHeaders);

        $response->assertStatus(403);
    }

    public function test_logs_of_another_tenant_never_appear_in_list_stats_or_export(): void
    {
        $other = $this->createTenant();
        $otherStoreId = (int) $this->tenantStore($other)->id;
        $otherAdminId = (int) $this->tenantAdmin($other)->id;
        $this->inTenant($other, fn () => ActivityLog::create([
            'user_id' => $otherAdminId,
            'store_id' => $otherStoreId,
            'module' => 'sales',
            'action' => 'cancelled',
            'description' => 'سجل مستأجر آخر سري',
            'created_at' => now(),
        ]));
        $this->log([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->mainStoreId,
            'module' => 'sales',
            'action' => 'invoice_created',
            'description' => 'سجل المستأجر الحالي',
        ]);

        $this->getJson('/api/v1/activity-logs', $this->adminHeaders)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'سجل المستأجر الحالي')
            ->assertJsonPath('total_count', 1)
            ->assertJsonPath('stats.today_total', 1)
            ->assertJsonPath('stats.today_critical', 0)
            ->assertJsonMissing(['description' => 'سجل مستأجر آخر سري']);

        $csv = $this->get('/api/v1/activity-logs/export-csv', $this->adminHeaders)->assertOk();
        // The CSV body is streamed after the request returns; in production tenancy is still
        // initialised at that point, so replay the stream inside the requesting tenant.
        $body = $this->inTenant($this->tenant, fn (): string => (string) $csv->streamedContent());
        $this->assertStringContainsString('سجل المستأجر الحالي', $body);
        $this->assertStringNotContainsString('سجل مستأجر آخر سري', $body);

        // Filtering by the other tenant's ids matches nothing here.
        $this->getJson('/api/v1/activity-logs?user_id='.$otherAdminId.'&store_id='.$otherStoreId, $this->adminHeaders)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Creates a log row inside the tenant, stamped now.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function log(array $attributes): void
    {
        $this->inTenant($this->tenant, fn () => ActivityLog::create(array_merge(['created_at' => now()], $attributes)));
    }
}
