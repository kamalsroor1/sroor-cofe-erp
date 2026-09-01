<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ActivityLogApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected User $regularUser;
    protected string $regularToken;
    protected Store $mainStore;
    protected Store $branchStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        $this->seed(PermissionsSeeder::class);

        $this->mainStore = Store::create([
            'name'       => 'الفرع الرئيسي',
            'code'       => 'MAIN',
            'type'       => 'retail',
            'is_default' => true,
            'is_active'  => true,
        ]);

        $this->branchStore = Store::create([
            'name'       => 'فرع المعادي',
            'code'       => 'MAADI',
            'type'       => 'branch',
            'is_default' => false,
            'is_active'  => true,
        ]);

        $this->adminUser = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012316954',
            'password'         => Hash::make('password123'),
            'is_active'        => true,
            'default_store_id' => $this->mainStore->id,
        ]);
        $this->adminUser->assignRole('admin');
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $this->regularUser = User::factory()->create([
            'name'             => 'أحمد كاشير',
            'phone'            => '01099998888',
            'password'         => Hash::make('password123'),
            'is_active'        => true,
            'default_store_id' => $this->branchStore->id,
        ]);
        $this->regularUser->assignRole('cashier');
        $this->regularToken = $this->regularUser->createToken('cashier-token')->plainTextToken;

        ActivityLog::query()->delete();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/activity-logs');
        $response->assertStatus(401);
    }

    public function test_user_without_logs_view_permission_is_forbidden(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->regularToken)
            ->getJson('/api/v1/activity-logs');

        $response->assertStatus(403);
    }

    public function test_authorized_admin_can_fetch_logs_with_complete_structure(): void
    {
        ActivityLog::create([
            'user_id'     => $this->adminUser->id,
            'store_id'    => $this->mainStore->id,
            'module'      => 'sales',
            'action'      => 'invoice_created',
            'description' => 'إصدار فاتورة مبيعات رقم #INV-1001',
            'ip_address'  => '127.0.0.1',
            'user_agent'  => 'Mozilla/5.0 Test Suite',
            'created_at'  => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/activity-logs');

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
                    ]
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

    public function test_can_filter_logs_by_search_keyword(): void
    {
        ActivityLog::create([
            'user_id'     => $this->adminUser->id,
            'store_id'    => $this->mainStore->id,
            'module'      => 'sales',
            'action'      => 'invoice_created',
            'description' => 'بيع بن حبوب كولومبي فاخر',
            'ip_address'  => '192.168.1.50',
            'created_at'  => now(),
        ]);

        ActivityLog::create([
            'user_id'     => $this->regularUser->id,
            'store_id'    => $this->branchStore->id,
            'module'      => 'expenses',
            'action'      => 'expense_paid',
            'description' => 'سداد فاتورة كهرباء الفرع',
            'ip_address'  => '10.0.0.1',
            'created_at'  => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/activity-logs?search=كولومبي');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'بيع بن حبوب كولومبي فاخر');
    }

    public function test_can_filter_logs_by_module_and_action(): void
    {
        ActivityLog::create([
            'user_id'     => $this->adminUser->id,
            'store_id'    => $this->mainStore->id,
            'module'      => 'sales',
            'action'      => 'invoice_cancelled',
            'description' => 'إلغاء فاتورة مبيعات #INV-999',
            'created_at'  => now(),
        ]);

        ActivityLog::create([
            'user_id'     => $this->adminUser->id,
            'store_id'    => $this->mainStore->id,
            'module'      => 'inventory',
            'action'      => 'stock_adjusted',
            'description' => 'تسوية رصيد بن اسبريسو',
            'created_at'  => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/activity-logs?module=sales&action=invoice_cancelled');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'invoice_cancelled');
    }

    public function test_can_filter_logs_by_user_and_store(): void
    {
        ActivityLog::create([
            'user_id'     => $this->adminUser->id,
            'store_id'    => $this->mainStore->id,
            'module'      => 'auth',
            'action'      => 'login',
            'description' => 'تسجيل دخول ناجح للمدير',
            'created_at'  => now(),
        ]);

        ActivityLog::create([
            'user_id'     => $this->regularUser->id,
            'store_id'    => $this->branchStore->id,
            'module'      => 'shifts',
            'action'      => 'shift_open',
            'description' => 'فتح وردية كاشير جديدة',
            'created_at'  => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/activity-logs?user_id=' . $this->regularUser->id . '&store_id=' . $this->branchStore->id);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_name', 'أحمد كاشير')
            ->assertJsonPath('data.0.store_name', 'فرع المعادي');
    }

    public function test_can_filter_logs_by_date_range(): void
    {
        $pastLog = ActivityLog::create([
            'user_id'     => $this->adminUser->id,
            'store_id'    => $this->mainStore->id,
            'module'      => 'sales',
            'action'      => 'sale',
            'description' => 'عملية سابقة',
        ]);
        $pastLog->timestamps = false;
        $pastLog->created_at = now()->subDays(5);
        $pastLog->save();

        ActivityLog::create([
            'user_id'     => $this->adminUser->id,
            'store_id'    => $this->mainStore->id,
            'module'      => 'sales',
            'action'      => 'sale',
            'description' => 'عملية اليوم',
            'created_at'  => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/activity-logs?from_date=' . now()->toDateString() . '&to_date=' . now()->toDateString());

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'عملية اليوم');
    }

    public function test_returns_accurate_today_statistics(): void
    {
        ActivityLog::create([
            'user_id'     => $this->adminUser->id,
            'store_id'    => $this->mainStore->id,
            'module'      => 'sales',
            'action'      => 'invoice_created',
            'description' => 'فاتورة عادية',
            'created_at'  => now(),
        ]);

        ActivityLog::create([
            'user_id'     => $this->regularUser->id,
            'store_id'    => $this->branchStore->id,
            'module'      => 'sales',
            'action'      => 'cancelled',
            'description' => 'إلغاء حرج',
            'created_at'  => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/activity-logs');

        $response->assertStatus(200)
            ->assertJsonPath('stats.today_total', 2)
            ->assertJsonPath('stats.today_critical', 1)
            ->assertJsonPath('stats.today_users', 2)
            ->assertJsonPath('stats.today_stores', 2);
    }

    public function test_validation_fails_on_invalid_date_format(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/activity-logs?from_date=invalid-date');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['from_date']);
    }

    public function test_handles_empty_logs_and_pagination_limits(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/activity-logs?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('total_count', 0)
            ->assertJsonPath('pagination.per_page', 10);
    }

    public function test_authorized_admin_can_export_logs_as_csv(): void
    {
        ActivityLog::create([
            'user_id'     => $this->adminUser->id,
            'store_id'    => $this->mainStore->id,
            'module'      => 'sales',
            'action'      => 'export_test',
            'description' => 'اختبار تصدير ملف إكسل و CSV',
            'ip_address'  => '127.0.0.1',
            'created_at'  => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->get('/api/v1/activity-logs/export-csv');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_unauthorized_user_cannot_export_csv(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->regularToken)
            ->getJson('/api/v1/activity-logs/export-csv');

        $response->assertStatus(403);
    }
}
