<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\ReturnDocument;
use App\Models\Setting;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\InvoiceService;
use App\Services\Settings\TenantSettings;
use App\Support\TenantClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TenantTestCase;

/**
 * SET-02: sales-return window (`returns_max_days`). Day counting uses the tenant clock and
 * business_day_cutoff; override = `returns.override_window` or a manager PIN of a holder.
 */
final class ReturnWindowApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $storeId;

    private int $customerId;

    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;

        $this->inTenant($this->tenant, function (): void {
            $this->customerId = (int) Customer::create(['name' => 'عميل', 'phone' => '01000008001', 'price_tier' => 'retail', 'is_active' => true])->id;
            $item = Item::create([
                'name' => 'صنف نافذة', 'code' => 'WIN-1', 'category' => 'coffee_beans', 'unit' => 'كجم',
                'cost_price' => '10.000', 'weighted_avg_cost' => '10.000', 'selling_price' => '20.000',
                'price_retail' => '20.000', 'price_wholesale' => '20.000',
                'current_stock' => '100.000', 'min_stock_level' => '1.000', 'is_active' => true,
            ]);
            StoreStock::create(['store_id' => $this->storeId, 'item_id' => $item->id, 'quantity' => '100.000']);
            $this->itemId = (int) $item->id;
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_without_a_window_an_invoice_of_any_age_can_be_returned(): void
    {
        [$invoiceId, $lineId] = $this->invoiceDated('2025-01-01');
        $this->at('2026-11-12 12:00');

        $this->postReturn($invoiceId, $lineId, $this->cashier())->assertCreated();
    }

    public function test_return_on_the_last_day_passes_and_one_day_later_is_refused_with_the_translated_message(): void
    {
        $this->setWindow('3');
        [$invoiceId, $lineId] = $this->invoiceDated('2026-11-01');
        $cashier = $this->cashier();

        $this->at('2026-11-04 12:00');
        $this->postReturn($invoiceId, $lineId, $cashier, '1.000')->assertCreated();

        $this->at('2026-11-05 12:00');
        $this->postReturn($invoiceId, $lineId, $cashier, '1.000', locale: 'ar')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'returns.window_expired')
            ->assertJsonPath('message', trans('returns.window_expired', ['days' => 3], 'ar'));

        $this->inTenant($this->tenant, fn () => $this->assertSame(1, ReturnDocument::query()->count()));
    }

    public function test_a_back_dated_return_date_does_not_reopen_the_window(): void
    {
        $this->setWindow('3');
        [$invoiceId, $lineId] = $this->invoiceDated('2026-11-01');
        $this->at('2026-11-10 12:00');

        // DATE-02: back-dating needs documents.backdate; with it the date is accepted yet the window still counts from the invoice.
        $backdater = $this->createTenantUser($this->tenant, null, ['returns.manage', 'documents.backdate']);

        $this->postReturn($invoiceId, $lineId, $backdater, returnDate: '2026-11-05')
            ->assertStatus(422)->assertJsonPath('error_code', 'returns.window_expired');
    }

    public function test_the_day_ends_at_midnight_on_the_tenant_clock(): void
    {
        $this->setWindow('3');
        [$invoiceId, $lineId] = $this->invoiceDated('2026-11-01');
        $cashier = $this->cashier();

        // 23:59 Cairo on day 3 is inside; 00:30 Cairo on day 4 is out, although it is still the
        // 4th in UTC (22:30): the count follows the tenant clock, not the server's.
        $this->at('2026-11-04 23:59');
        $this->postReturn($invoiceId, $lineId, $cashier, '1.000')->assertCreated();

        $this->at('2026-11-05 00:30');
        $this->postReturn($invoiceId, $lineId, $cashier, '1.000')->assertStatus(422)->assertJsonPath('error_code', 'returns.window_expired');
    }

    public function test_the_business_day_cutoff_extends_the_last_day(): void
    {
        $this->setWindow('3');
        $this->inTenant($this->tenant, fn () => $this->putSetting(TenantSettings::KEY_BUSINESS_DAY_CUTOFF, '03:00'));
        [$invoiceId, $lineId] = $this->invoiceDated('2026-11-01');
        $cashier = $this->cashier();

        // 02:30 on the 5th still belongs to business day the 4th.
        $this->at('2026-11-05 02:30');
        $this->postReturn($invoiceId, $lineId, $cashier, '1.000')->assertCreated();

        $this->at('2026-11-05 03:00');
        $this->postReturn($invoiceId, $lineId, $cashier, '1.000')->assertStatus(422)->assertJsonPath('error_code', 'returns.window_expired');
    }

    public function test_the_override_permission_passes_and_is_logged(): void
    {
        $this->setWindow('3');
        [$invoiceId, $lineId] = $this->invoiceDated('2026-11-01');
        $this->at('2026-11-20 12:00');
        $supervisor = $this->createTenantUser($this->tenant, null, ['returns.manage', 'returns.override_window']);

        $id = (int) $this->postReturn($invoiceId, $lineId, $supervisor)->assertCreated()->json('data.id');

        $this->inTenant($this->tenant, function () use ($id, $supervisor): void {
            $log = ActivityLog::query()->where('action', 'return_window_overridden')->sole();
            $this->assertSame($id, (int) $log->subject_id);
            $this->assertSame((int) $supervisor->id, (int) $log->user_id);
            $this->assertSame((int) $supervisor->id, $log->properties['approved_by']);
            $this->assertSame(19, $log->properties['age_days']);
            $this->assertFalse($log->properties['via_manager_pin']);
        });
    }

    /**
     * Security review of SEC-21 (9): the override record was written AFTER the return's
     * transaction. If the log failed, the return existed (stock and balance moved), the client got
     * a 500 and no `return_window_overridden` record existed. Now the record is part of the
     * return's unit of work: a failing log rolls the return back.
     */
    public function test_a_failing_override_log_rolls_the_return_back(): void
    {
        $this->setWindow('3');
        [$invoiceId, $lineId] = $this->invoiceDated('2026-11-01');
        $this->at('2026-11-20 12:00');
        $supervisor = $this->createTenantUser($this->tenant, null, ['returns.manage', 'returns.override_window']);
        $stockBefore = $this->inTenant($this->tenant, fn (): string => (string) StoreStock::query()->where('store_id', $this->storeId)->where('item_id', $this->itemId)->value('quantity'));

        $this->app->instance(ActivityLogService::class, new class extends ActivityLogService
        {
            public function log(string $module, string $action, string $description, ?Model $subject = null, ?array $properties = null, ?int $userId = null, ?int $storeId = null): ActivityLog
            {
                if ($action === 'return_window_overridden') {
                    throw new \RuntimeException('activity log is down');
                }

                return parent::log($module, $action, $description, $subject, $properties, $userId, $storeId);
            }
        });

        $response = $this->postReturn($invoiceId, $lineId, $supervisor);
        $this->assertGreaterThanOrEqual(500, $response->status());

        $this->inTenant($this->tenant, function () use ($stockBefore): void {
            $this->assertSame(0, ReturnDocument::query()->count());
            $this->assertSame($stockBefore, (string) StoreStock::query()->where('store_id', $this->storeId)->where('item_id', $this->itemId)->value('quantity'));
        });
    }

    public function test_the_tenant_admin_holds_the_override_by_default(): void
    {
        $this->setWindow('3');
        [$invoiceId, $lineId] = $this->invoiceDated('2026-11-01');
        $this->at('2026-11-20 12:00');

        $this->postReturn($invoiceId, $lineId, null)->assertCreated();
    }

    public function test_a_manager_pin_of_a_holder_approves_and_a_wrong_or_unauthorised_pin_does_not(): void
    {
        $this->setWindow('3');
        [$invoiceId, $lineId] = $this->invoiceDated('2026-11-01');
        $this->at('2026-11-20 12:00');
        $cashier = $this->cashier();
        $admin = $this->tenantAdmin($this->tenant);
        $this->inTenant($this->tenant, fn () => User::query()->whereKey($admin->id)->update(['pos_pin' => Hash::make('482916')]));
        // A real PIN, but its owner lacks returns.override_window.
        $plain = $this->createTenantUser($this->tenant, null, ['returns.manage']);
        $this->inTenant($this->tenant, fn () => User::query()->whereKey($plain->id)->update(['pos_pin' => Hash::make('135790')]));

        $this->postReturn($invoiceId, $lineId, $cashier, override: ['user_id' => $admin->id, 'pin' => '000000'])
            ->assertStatus(422)->assertJsonValidationErrors(['override.pin']);
        $this->postReturn($invoiceId, $lineId, $cashier, override: ['user_id' => $plain->id, 'pin' => '135790'])
            ->assertStatus(422)->assertJsonValidationErrors(['override.pin']);
        $this->postReturn($invoiceId, $lineId, $cashier, override: ['user_id' => $admin->id, 'pin' => 'abc'])
            ->assertStatus(422)->assertJsonValidationErrors(['override.pin']);

        $this->postReturn($invoiceId, $lineId, $cashier, override: ['user_id' => $admin->id, 'pin' => '482916'])->assertCreated();

        $this->inTenant($this->tenant, function () use ($admin, $cashier): void {
            $log = ActivityLog::query()->where('action', 'return_window_overridden')->sole();
            $this->assertSame((int) $cashier->id, (int) $log->user_id);
            $this->assertSame((int) $admin->id, $log->properties['approved_by']);
            $this->assertTrue($log->properties['via_manager_pin']);
            $this->assertSame(1, ReturnDocument::query()->count());
        });
    }

    public function test_purchase_returns_and_returns_without_invoice_are_not_affected(): void
    {
        $this->setWindow('1');
        $this->at('2026-11-20 12:00');

        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'return_date' => '2026-11-20',
            'items' => [['item_id' => $this->itemId, 'quantity' => '1.000', 'unit_price' => '20.000']],
        ], $this->tenantHeaders($this->tenant))->assertCreated();
    }

    public function test_guest_is_rejected_and_a_user_without_returns_permission_is_forbidden(): void
    {
        $this->setWindow('3');
        [$invoiceId, $lineId] = $this->invoiceDated('2026-11-01');
        $this->at('2026-11-20 12:00');
        $payload = $this->payload($invoiceId, $lineId, '1.000', '2026-11-20');

        $this->postJson('/api/v1/returns', $payload, $this->tenantGuestHeaders($this->tenant))->assertStatus(401);
        $nobody = $this->createTenantUser($this->tenant, null, ['items.view']);
        $this->postJson('/api/v1/returns', $payload, $this->tenantHeaders($this->tenant, $nobody))->assertStatus(403);
    }

    public function test_the_window_of_one_tenant_does_not_apply_to_another(): void
    {
        $this->setWindow('1');
        $other = $this->createTenant();

        $this->inTenant($other, fn () => $this->assertNull(app(TenantSettings::class)->returnsMaxDays()));
        $this->inTenant($this->tenant, fn () => $this->assertSame(1, app(TenantSettings::class)->returnsMaxDays()));
    }

    // ------------------------------------------------------------------ helpers

    private function cashier(): User
    {
        return $this->createTenantUser($this->tenant, null, ['returns.manage']);
    }

    private function setWindow(string $days): void
    {
        $this->inTenant($this->tenant, fn () => $this->putSetting(TenantSettings::KEY_RETURNS_MAX_DAYS, $days));
    }

    private function putSetting(string $key, string $value): void
    {
        Setting::set($key, $value);
        Setting::clearCache();
    }

    /** Freeze "now" at a wall time on the tenant clock (Africa/Cairo). */
    private function at(string $cairoTime): void
    {
        $now = CarbonImmutable::parse($cairoTime, 'Africa/Cairo');
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function invoiceDated(string $date): array
    {
        return $this->inTenant($this->tenant, function () use ($date): array {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());
            $invoice = app(InvoiceService::class)->confirmInvoice([
                'customer_id' => $this->customerId, 'store_id' => $this->storeId,
                'payment_type' => 'credit', 'payment_method' => 'cash', 'paid_amount' => '0.000',
                'items' => [['item_id' => $this->itemId, 'quantity' => '10.000', 'unit_price' => '20.000']],
            ]);
            Invoice::query()->whereKey($invoice->id)->update(['invoice_date' => $date]);

            return [(int) $invoice->id, (int) InvoiceItem::query()->where('invoice_id', $invoice->id)->value('id')];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $invoiceId, int $lineId, string $qty, string $returnDate): array
    {
        return [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $invoiceId,
            'return_date' => $returnDate, 'items' => [['invoice_item_id' => $lineId, 'quantity' => $qty]],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $override
     */
    private function postReturn(int $invoiceId, int $lineId, ?User $user, string $qty = '1.000', ?string $returnDate = null, ?array $override = null, ?string $locale = null): TestResponse
    {
        $payload = $this->payload($invoiceId, $lineId, $qty, $returnDate ?? $this->inTenant($this->tenant, fn () => app(TenantClock::class)->businessDate()));
        if ($override !== null) {
            $payload['override'] = $override;
        }

        $headers = $this->tenantHeaders($this->tenant, $user);
        if ($locale !== null) {
            $headers['X-Locale'] = $locale;
            $headers['Accept-Language'] = $locale;
        }

        return $this->postJson('/api/v1/returns', $payload, $headers);
    }
}
