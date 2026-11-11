<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Invoices\GetInvoiceDetailsAction;
use App\Actions\Tenants\GetTenantDetailsAction;
use App\Actions\Trash\GetTrashRecordsAction;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\ReturnDocument;
use App\Models\Setting;
use App\Models\Store;
use App\Services\Settings\TenantSettings;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\App;
use Tests\TenantTestCase;

/**
 * FE-05: tenant-facing text (receipts, shift messages, prints) shows the tenant's currency
 * symbol, not a hardcoded Egyptian pound.
 */
final class TenantCurrencyLabelTest extends TenantTestCase
{
    public function createApplication(): Application
    {
        $base = dirname(__DIR__, 2);
        putenv('APP_BASE_PATH='.$base);
        $_ENV['APP_BASE_PATH'] = $base;
        $_SERVER['APP_BASE_PATH'] = $base;

        return parent::createApplication();
    }

    public function test_label_follows_tenant_currency_and_locale(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            $settings = app(TenantSettings::class);

            App::setLocale('ar');
            $this->assertSame('ج.م', $settings->currencyLabel());

            Setting::set('currency', 'SAR');
            $this->assertSame('ر.س', $settings->currencyLabel());

            App::setLocale('en');
            $this->assertSame('SAR', $settings->currencyLabel());
        });
    }

    public function test_every_selectable_currency_has_a_symbol_in_both_locales(): void
    {
        foreach (array_keys(TenantSettings::SELECTABLE_CURRENCY_DECIMALS) as $code) {
            foreach (['ar', 'en'] as $locale) {
                $this->assertNotSame(
                    'common.currencies.'.$code,
                    __('common.currencies.'.$code, [], $locale),
                    "{$code} missing in {$locale}",
                );
            }
        }
    }

    public function test_whatsapp_summary_uses_tenant_currency(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            Setting::set('currency', 'SAR');
            App::setLocale('ar');

            $mainStore = Store::firstOrFail();
            $customer = Customer::create([
                'name' => 'عميل تجريبي',
                'phone' => '01012345678',
                'current_balance' => '0.000',
                'price_tier' => 'retail',
                'is_active' => true,
            ]);

            $invoice = Invoice::create([
                'invoice_number' => 'INV-TEST-CURR',
                'store_id' => $mainStore->id,
                'customer_id' => $customer->id,
                'user_id' => null,
                'invoice_date' => now()->toDateString(),
                'subtotal' => '150.000',
                'net_total' => '150.000',
                'paid_amount' => '150.000',
                'remaining_amount' => '0.000',
                'status' => 'confirmed',
                'payment_type' => 'cash',
            ]);

            $action = app(GetInvoiceDetailsAction::class);
            $details = $action->execute($invoice->id);

            $this->assertStringContainsString('ر.س', $details['whatsapp']['message_text']);
            $this->assertStringNotContainsString('ج.م', $details['whatsapp']['message_text']);
        });
    }

    public function test_trash_records_subtitles_use_tenant_currency(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            Setting::set('currency', 'SAR');
            App::setLocale('ar');

            $mainStore = Store::firstOrFail();

            $expense = Expense::create([
                'expense_number' => 'EXP-CURR-001',
                'title' => 'مصروف تجريبي',
                'amount' => '75.000',
                'expense_date' => now()->toDateString(),
                'store_id' => $mainStore->id,
                'category' => 'general',
            ]);
            $expense->delete();

            $returnDoc = ReturnDocument::create([
                'return_number' => 'RET-CURR-01',
                'return_type' => 'sales_return',
                'store_id' => $mainStore->id,
                'total_amount' => '120.000',
                'return_date' => now()->toDateString(),
            ]);
            $returnDoc->delete();

            $action = app(GetTrashRecordsAction::class);

            $expenseRecords = $action->execute('expenses');
            $this->assertNotEmpty($expenseRecords['records']);
            $this->assertStringContainsString('ر.س', $expenseRecords['records'][0]['subtitle']);
            $this->assertStringNotContainsString('ج.م', $expenseRecords['records'][0]['subtitle']);

            $returnRecords = $action->execute('returns');
            $this->assertNotEmpty($returnRecords['records']);
            $this->assertStringContainsString('ر.س', $returnRecords['records'][0]['subtitle']);
            $this->assertStringNotContainsString('ج.م', $returnRecords['records'][0]['subtitle']);
        });
    }

    public function test_super_admin_get_tenant_details_returns_tenant_currency_and_label(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            Setting::set('currency', 'SAR');
        });

        App::setLocale('ar');
        $action = app(GetTenantDetailsAction::class);
        $result = $action->execute($tenant->id);

        $this->assertSame('SAR', $result['stats']['currency']);
        $this->assertSame('ر.س', $result['stats']['currency_label']);
        $this->assertSame('SAR', $result['tenant']['currency']);
    }
}
