<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\Catalog\FeatureCatalog;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * Runs on sqlite (`php` job) and on MySQL 8 (`mysql` job): sqlite reads an unknown
 * double-quoted identifier as a string literal, so only MySQL catches a select / order by
 * on a column that does not exist (BUG-05).
 */
#[Group('mysql')]
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

    public function test_csv_export_neutralizes_spreadsheet_formulas(): void
    {
        // SEC-1 (L7): user-controlled cells starting with = + - @ (or tab / CR) are prefixed with '.
        foreach (['=HYPERLINK("http://evil.test","x")', '+SUM(1,2)', '-2+3', '@cmd', "\tTAB"] as $i => $description) {
            $this->log([
                'user_id' => $this->adminUser->id,
                'store_id' => $this->mainStoreId,
                'module' => 'sales',
                'action' => 'csv_formula_'.$i,
                'description' => $description,
                'ip_address' => '127.0.0.1',
            ]);
        }

        $csv = $this->get('/api/v1/activity-logs/export-csv', $this->adminHeaders)->assertOk();
        $body = $this->inTenant($this->tenant, fn (): string => (string) $csv->streamedContent());

        $this->assertStringContainsString("'=HYPERLINK", $body);
        $this->assertStringContainsString("'+SUM(1,2)", $body);
        $this->assertStringContainsString("'-2+3", $body);
        $this->assertStringContainsString("'@cmd", $body);
        $this->assertStringContainsString("'\tTAB", $body);
        $this->assertDoesNotMatchRegularExpression('/(^|,)"?=HYPERLINK/m', $body);
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

    public function test_non_admin_user_holding_logs_view_can_fetch_logs(): void
    {
        $auditor = $this->createTenantUser($this->tenant, permissions: ['logs.view']);
        $this->log([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->mainStoreId,
            'module' => 'sales',
            'action' => 'invoice_created',
            'description' => 'فاتورة للمراجعة',
        ]);

        $this->getJson('/api/v1/activity-logs', $this->tenantHeaders($this->tenant, $auditor))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'فاتورة للمراجعة');
    }

    public function test_plan_without_the_audit_log_feature_is_forbidden_and_plan_with_it_is_allowed(): void
    {
        $without = $this->createTenant(['plan_id' => $this->plan(['audit.logs' => false])->id]);
        $with = $this->createTenant(['plan_id' => $this->plan(['audit.logs' => true])->id]);

        foreach (['/api/v1/activity-logs', '/api/v1/activity-logs/export-csv'] as $uri) {
            $this->getJson($uri, $this->tenantHeaders($without))
                ->assertStatus(403)
                ->assertJsonPath('error_code', 'subscription.feature_unavailable')
                ->assertJsonPath('details.feature', 'audit.logs');
        }

        $this->getJson('/api/v1/activity-logs', $this->tenantHeaders($with))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_every_field_of_a_log_row_is_returned_with_its_stored_value(): void
    {
        $this->log([
            'user_id' => $this->regularUser->id,
            'store_id' => $this->branchStoreId,
            'module' => 'sales',
            'action' => 'invoice_created',
            'description' => 'بيع 0.250 كجم بن',
            'properties' => ['quantity' => '0.250', 'total' => '62.500'],
            'ip_address' => '10.1.2.3',
            'user_agent' => 'Sroor POS/1.0',
        ]);

        $response = $this->getJson('/api/v1/activity-logs', $this->adminHeaders)->assertOk();

        $response->assertJsonPath('data.0.module', 'sales')
            ->assertJsonPath('data.0.action', 'invoice_created')
            ->assertJsonPath('data.0.description', 'بيع 0.250 كجم بن')
            ->assertJsonPath('data.0.user_name', 'أحمد كاشير')
            ->assertJsonPath('data.0.user_phone', $this->regularUser->phone)
            ->assertJsonPath('data.0.store_name', 'فرع المعادي')
            ->assertJsonPath('data.0.ip_address', '10.1.2.3')
            ->assertJsonPath('data.0.user_agent', 'Sroor POS/1.0')
            // Key by key: a MySQL JSON column does not keep the object's key order.
            ->assertJsonPath('data.0.properties.quantity', '0.250')
            ->assertJsonPath('data.0.properties.total', '62.500')
            ->assertJsonPath('data.0.payload.quantity', '0.250')
            ->assertJsonPath('data.0.payload.total', '62.500');

        $this->assertCount(2, $response->json('data.0.properties'));
        $this->assertSame($response->json('data.0.properties'), $response->json('data.0.payload'));

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $response->json('data.0.created_at'));
        $this->assertSame(
            ['id', 'module', 'module_label', 'module_color', 'module_icon', 'action', 'description', 'properties', 'user_name',
                'user_phone', 'store_name', 'ip_address', 'user_agent', 'payload', 'created_at', 'time_ago'],
            array_keys($response->json('data.0')),
        );
    }

    public function test_logs_are_paginated_newest_first(): void
    {
        foreach (range(1, 5) as $i) {
            $this->log([
                'user_id' => $this->adminUser->id,
                'store_id' => $this->mainStoreId,
                'module' => 'sales',
                'action' => 'invoice_created',
                'description' => 'سجل رقم '.$i,
            ]);
        }

        $this->getJson('/api/v1/activity-logs?per_page=2', $this->adminHeaders)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.description', 'سجل رقم 5')
            ->assertJsonPath('data.1.description', 'سجل رقم 4')
            ->assertJsonPath('total_count', 5)
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.last_page', 3)
            ->assertJsonPath('pagination.per_page', 2)
            ->assertJsonPath('pagination.total', 5);

        $this->getJson('/api/v1/activity-logs?per_page=2&page=3', $this->adminHeaders)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'سجل رقم 1')
            ->assertJsonPath('pagination.current_page', 3);

        $this->getJson('/api/v1/activity-logs?per_page=201', $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_each_filter_alone_narrows_the_list(): void
    {
        $this->log([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->mainStoreId,
            'module' => 'sales',
            'action' => 'invoice_created',
            'description' => 'فاتورة الفرع الرئيسي',
        ]);
        $this->log([
            'user_id' => $this->regularUser->id,
            'store_id' => $this->branchStoreId,
            'module' => 'expenses',
            'action' => 'expense_paid',
            'description' => 'مصروف فرع المعادي',
        ]);

        $cases = [
            'module=expenses' => 'مصروف فرع المعادي',
            'module=sales' => 'فاتورة الفرع الرئيسي',
            'user_id='.$this->regularUser->id => 'مصروف فرع المعادي',
            'user_id='.$this->adminUser->id => 'فاتورة الفرع الرئيسي',
            // Search also matches the related user's name and the related store's name.
            'search='.urlencode('كاشير') => 'مصروف فرع المعادي',
            'search='.urlencode('الرئيسي') => 'فاتورة الفرع الرئيسي',
        ];

        foreach ($cases as $query => $expected) {
            $this->getJson('/api/v1/activity-logs?'.$query, $this->adminHeaders)
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.description', $expected)
                ->assertJsonPath('total_count', 1);
        }

        $this->getJson('/api/v1/activity-logs?module=all&user_id=all&store_id=all', $this->adminHeaders)
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_store_filter_never_returns_logs_of_another_store(): void
    {
        $this->log([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->mainStoreId,
            'module' => 'sales',
            'action' => 'invoice_created',
            'description' => 'سجل الفرع الرئيسي',
        ]);
        $this->log([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->branchStoreId,
            'module' => 'sales',
            'action' => 'invoice_created',
            'description' => 'سجل فرع المعادي',
        ]);

        $this->getJson('/api/v1/activity-logs?store_id='.$this->branchStoreId, $this->adminHeaders)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'سجل فرع المعادي')
            ->assertJsonPath('data.0.store_name', 'فرع المعادي')
            ->assertJsonPath('total_count', 1)
            ->assertJsonMissing(['description' => 'سجل الفرع الرئيسي']);

        $this->getJson('/api/v1/activity-logs?store_id='.$this->mainStoreId, $this->adminHeaders)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'سجل الفرع الرئيسي')
            ->assertJsonMissing(['description' => 'سجل فرع المعادي']);
    }

    public function test_branch_restricted_user_sees_only_their_branch_in_list_stats_and_store_options(): void
    {
        $this->seedBranchLogs();
        $auditor = $this->branchAuditor();

        $response = $this->getJson('/api/v1/activity-logs', $this->tenantHeaders($this->tenant, $auditor, $this->branchStoreId))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'سجل فرع المعادي')
            ->assertJsonPath('total_count', 1)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('stats.today_total', 1)
            ->assertJsonPath('stats.today_critical', 0)
            ->assertJsonPath('stats.today_stores', 1)
            ->assertJsonMissing(['description' => 'سجل الفرع الرئيسي'])
            ->assertJsonMissing(['description' => 'سجل عام بدون فرع']);

        $this->assertSame([$this->branchStoreId], array_map('intval', array_column($response->json('stores'), 'id')));

        // The SPA's default "all" filter and an explicit filter on their own branch keep the same scope.
        foreach (['store_id=all', 'store_id='.$this->branchStoreId, 'search='.urlencode('سجل')] as $query) {
            $this->getJson('/api/v1/activity-logs?'.$query, $this->tenantHeaders($this->tenant, $auditor, $this->branchStoreId))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.description', 'سجل فرع المعادي');
        }

        // X-Store-Id of their branch does not widen anything either way.
        $this->getJson('/api/v1/activity-logs', $this->tenantHeaders($this->tenant, $auditor, $this->branchStoreId))
            ->assertJsonMissing(['description' => 'سجل الفرع الرئيسي']);
    }

    public function test_branch_restricted_user_export_holds_only_their_branch(): void
    {
        $this->seedBranchLogs();
        $auditor = $this->branchAuditor();

        $csv = $this->get('/api/v1/activity-logs/export-csv', $this->tenantHeaders($this->tenant, $auditor, $this->branchStoreId))->assertOk();
        $body = $this->inTenant($this->tenant, fn (): string => (string) $csv->streamedContent());

        $this->assertStringContainsString('سجل فرع المعادي', $body);
        $this->assertStringNotContainsString('سجل الفرع الرئيسي', $body);
        $this->assertStringNotContainsString('سجل عام بدون فرع', $body);
    }

    public function test_store_filter_of_a_branch_the_user_cannot_access_is_forbidden_on_list_and_export(): void
    {
        $this->seedBranchLogs();
        $headers = $this->tenantHeaders($this->tenant, $this->branchAuditor(), $this->branchStoreId);

        foreach (['/api/v1/activity-logs', '/api/v1/activity-logs/export-csv'] as $uri) {
            $this->getJson($uri.'?store_id='.$this->mainStoreId, $headers)
                ->assertStatus(403)
                ->assertJsonPath('success', false)
                ->assertJsonPath('error_code', 'store_access_denied')
                ->assertJsonMissing(['description' => 'سجل الفرع الرئيسي']);

            // Malformed ids are refused too, never read as "no filter".
            $this->getJson($uri.'?store_id=abc', $headers)
                ->assertStatus(403)
                ->assertJsonPath('error_code', 'store_access_denied');
        }
    }

    public function test_user_with_an_assigned_branch_sees_their_default_and_assigned_branches_only(): void
    {
        $thirdStoreId = $this->inTenant($this->tenant, fn (): int => Store::create([
            'name' => 'فرع الزمالك',
            'code' => 'ZAMALEK',
            'type' => 'branch',
            'is_default' => false,
            'is_active' => true,
        ])->id);
        $this->seedBranchLogs();
        $this->log(['user_id' => $this->adminUser->id, 'store_id' => $thirdStoreId, 'module' => 'sales', 'action' => 'sale', 'description' => 'سجل فرع الزمالك']);

        $auditor = $this->branchAuditor();
        $this->inTenant($this->tenant, fn () => $auditor->stores()->attach($thirdStoreId));

        $response = $this->getJson('/api/v1/activity-logs', $this->tenantHeaders($this->tenant, $auditor, $this->branchStoreId))
            ->assertOk()
            ->assertJsonPath('total_count', 2)
            ->assertJsonMissing(['description' => 'سجل الفرع الرئيسي'])
            ->assertJsonMissing(['description' => 'سجل عام بدون فرع']);

        $descriptions = array_column($response->json('data'), 'description');
        sort($descriptions);
        $this->assertSame(['سجل فرع الزمالك', 'سجل فرع المعادي'], $descriptions);

        $this->getJson('/api/v1/activity-logs?store_id='.$thirdStoreId, $this->tenantHeaders($this->tenant, $auditor, $this->branchStoreId))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'سجل فرع الزمالك');
    }

    public function test_view_all_user_and_admin_see_every_branch_and_shop_wide_logs(): void
    {
        $this->seedBranchLogs();
        $viewAll = $this->createTenantUser($this->tenant, permissions: ['logs.view', 'stores.view_all'], attributes: [
            'default_store_id' => $this->branchStoreId,
        ]);

        foreach ([$this->tenantHeaders($this->tenant, $viewAll, $this->branchStoreId), $this->adminHeaders] as $headers) {
            $response = $this->getJson('/api/v1/activity-logs', $headers)
                ->assertOk()
                ->assertJsonCount(3, 'data')
                ->assertJsonPath('total_count', 3)
                ->assertJsonPath('stats.today_total', 3);

            $descriptions = array_column($response->json('data'), 'description');
            $this->assertContains('سجل عام بدون فرع', $descriptions);
            $this->assertContains('سجل الفرع الرئيسي', $descriptions);
            $this->assertContains('سجل فرع المعادي', $descriptions);
            $this->assertEqualsCanonicalizing(
                [$this->mainStoreId, $this->branchStoreId],
                array_map('intval', array_column($response->json('stores'), 'id')),
            );

            // They may filter on any branch.
            $this->getJson('/api/v1/activity-logs?store_id='.$this->mainStoreId, $headers)
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.description', 'سجل الفرع الرئيسي');

            $csv = $this->get('/api/v1/activity-logs/export-csv', $headers)->assertOk();
            $body = $this->inTenant($this->tenant, fn (): string => (string) $csv->streamedContent());
            $this->assertStringContainsString('سجل عام بدون فرع', $body);
            $this->assertStringContainsString('سجل الفرع الرئيسي', $body);
        }
    }

    /** A logs.view holder limited to the Maadi branch (no admin, no stores.view_all). */
    private function branchAuditor(): User
    {
        return $this->createTenantUser($this->tenant, permissions: ['logs.view'], attributes: [
            'name' => 'مراجع فرع المعادي',
            'default_store_id' => $this->branchStoreId,
        ]);
    }

    /** One log on the main store, one on the Maadi branch and one shop-wide log (no store). */
    private function seedBranchLogs(): void
    {
        $this->log(['user_id' => $this->adminUser->id, 'store_id' => $this->mainStoreId, 'module' => 'sales', 'action' => 'cancelled', 'description' => 'سجل الفرع الرئيسي']);
        $this->log(['user_id' => $this->regularUser->id, 'store_id' => $this->branchStoreId, 'module' => 'sales', 'action' => 'sale', 'description' => 'سجل فرع المعادي']);
        $this->log(['user_id' => $this->adminUser->id, 'store_id' => null, 'module' => 'settings', 'action' => 'updated', 'description' => 'سجل عام بدون فرع']);
    }

    /**
     * A plan with every catalog feature on, except the overrides.
     *
     * @param  array<string, bool>  $features
     */
    private function plan(array $features): Plan
    {
        $slug = 'logs-'.Str::lower(Str::random(10));

        return Plan::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'price_monthly' => '449.000',
            'price_yearly' => '4490.000',
            'features' => array_merge(array_fill_keys(FeatureCatalog::keys(), true), $features),
            'max_users' => null,
            'max_stores' => null,
            'max_warehouses' => null,
            'max_vans' => null,
            'max_items' => null,
            'is_active' => true,
            'sort_order' => 1,
        ]);
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
