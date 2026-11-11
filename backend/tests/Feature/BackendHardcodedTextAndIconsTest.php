<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Invoices\GetInvoiceDetailsAction;
use App\Actions\Roles\GetRolesMatrixAction;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\Store;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\App;
use Tests\TenantTestCase;

/**
 * FE-12: Backend actions, enums, and ActivityLog must use localization keys (ar/en)
 * and Lucide icon names instead of hardcoded Arabic strings or emoji icons.
 */
final class BackendHardcodedTextAndIconsTest extends TenantTestCase
{
    public function createApplication(): Application
    {
        $base = dirname(__DIR__, 2);
        putenv('APP_BASE_PATH='.$base);
        $_ENV['APP_BASE_PATH'] = $base;
        $_SERVER['APP_BASE_PATH'] = $base;

        return parent::createApplication();
    }

    public function test_whatsapp_invoice_receipt_is_localized_in_arabic_and_english_without_emojis(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            Setting::set('company_name', 'Sroor Coffee');
            Setting::set('currency', 'SAR');

            $mainStore = Store::firstOrFail();
            $customer = Customer::create([
                'name' => 'Ahmad Saleh',
                'phone' => '01012345678',
                'current_balance' => '25.000',
                'price_tier' => 'retail',
                'is_active' => true,
            ]);

            $invoice = Invoice::create([
                'invoice_number' => 'INV-FE12-001',
                'store_id' => $mainStore->id,
                'customer_id' => $customer->id,
                'user_id' => null,
                'invoice_date' => '2026-11-11',
                'subtotal' => '150.000',
                'net_total' => '150.000',
                'paid_amount' => '125.000',
                'remaining_amount' => '25.000',
                'status' => 'confirmed',
                'payment_type' => 'partial',
            ]);

            $action = app(GetInvoiceDetailsAction::class);

            App::setLocale('ar');
            $arResult = $action->execute($invoice->id);
            $arText = $arResult['whatsapp']['message_text'];
            $this->assertStringContainsString('*فاتورة مبيعات - Sroor Coffee*', $arText);
            $this->assertStringContainsString('*رقم الفاتورة:* INV-FE12-001', $arText);
            $this->assertStringContainsString('*الإجمالي الصافي:* 150.00 ر.س', $arText);
            $this->assertStringContainsString('شكراً لتعاملكم مع Sroor Coffee', $arText);
            $this->assertSame(0, preg_match('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $arText));

            App::setLocale('en');
            $mainStore->update(['name' => 'Main Branch']);
            $enResult = $action->execute($invoice->id);
            $enText = $enResult['whatsapp']['message_text'];
            $this->assertStringContainsString('*Sales Invoice - Sroor Coffee*', $enText);
            $this->assertStringContainsString('*Invoice Number:* INV-FE12-001', $enText);
            $this->assertStringContainsString('*Customer:* Ahmad Saleh', $enText);
            $this->assertStringContainsString('*Net Total:* 150.00 SAR', $enText);
            $this->assertStringContainsString('*Paid:* 125.00 SAR', $enText);
            $this->assertStringContainsString('*Remaining:* 25.00 SAR', $enText);
            $this->assertStringContainsString('*Current Account Balance:* 25.00 SAR', $enText);
            $this->assertStringContainsString('Thank you for your business with Sroor Coffee', $enText);
            $this->assertSame(0, preg_match('/[\x{0600}-\x{06FF}]/u', $enText));
            $this->assertSame(0, preg_match('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $enText));
        });
    }

    public function test_invoice_show_api_respects_locale_and_permissions(): void
    {
        $tenant = $this->createTenant();
        $otherTenant = $this->createTenant();

        $invoiceId = $this->inTenant($tenant, function (): int {
            $store = Store::firstOrFail();
            $customer = Customer::create([
                'name' => 'John Doe',
                'phone' => '01099998888',
                'current_balance' => '0.000',
                'price_tier' => 'retail',
                'is_active' => true,
            ]);
            $invoice = Invoice::create([
                'invoice_number' => 'INV-FE12-API',
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'user_id' => null,
                'invoice_date' => '2026-11-11',
                'subtotal' => '80.000',
                'net_total' => '80.000',
                'paid_amount' => '80.000',
                'remaining_amount' => '0.000',
                'status' => 'confirmed',
                'payment_type' => 'cash',
            ]);

            return $invoice->id;
        });

        // 401 unauthenticated
        $this->getJson("/api/v1/invoices/{$invoiceId}", $this->tenantGuestHeaders($tenant))->assertStatus(401);

        // 403 without permission
        $unprivileged = $this->createTenantUser($tenant, null);
        $this->getJson("/api/v1/invoices/{$invoiceId}", $this->tenantHeaders($tenant, $unprivileged))->assertStatus(403);

        // Tenant isolation: another tenant cannot read this invoice
        $this->getJson("/api/v1/invoices/{$invoiceId}", $this->tenantHeaders($otherTenant))->assertStatus(404);

        // 200 with admin in English locale
        $resEn = $this->getJson("/api/v1/invoices/{$invoiceId}", array_merge($this->tenantHeaders($tenant), ['X-Locale' => 'en']))
            ->assertStatus(200);
        $this->assertStringContainsString('*Sales Invoice -', (string) $resEn->json('whatsapp.message_text'));
        $this->assertStringContainsString('John Doe', (string) $resEn->json('whatsapp.message_text'));
    }

    public function test_roles_matrix_and_activity_log_and_enums_use_lucide_icons_and_translations(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            App::setLocale('en');

            $matrix = app(GetRolesMatrixAction::class)->execute();
            $salesModule = $matrix['permission_modules']['sales'];
            $this->assertSame('Sales & POS', $salesModule['title']);
            $this->assertSame('shopping-cart', $salesModule['icon']);

            foreach ($matrix['permission_modules'] as $mod) {
                $this->assertSame(0, preg_match('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $mod['icon']));
                $this->assertSame(0, preg_match('/[\x{0600}-\x{06FF}]/u', $mod['title']));
            }

            $log = new ActivityLog(['module' => 'sales', 'action' => 'invoice_created']);
            $this->assertSame('shopping-cart', $log->module_badge['icon']);
            $this->assertSame('Sales & POS', $log->module_badge['label']);
            $this->assertSame('Created / Issued', $log->action_badge['label']);
            $this->assertSame('Issued sales invoice', $log->action_label);

            foreach (PaymentMethod::cases() as $method) {
                $this->assertSame(0, preg_match('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $method->icon()));
                $this->assertSame(0, preg_match('/[\x{0600}-\x{06FF}]/u', $method->label()));
                $this->assertSame(0, preg_match('/[\x{0600}-\x{06FF}]/u', $method->shortLabel()));
            }

            foreach (UserRole::cases() as $role) {
                $this->assertSame(0, preg_match('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $role->icon()));
                $this->assertSame(0, preg_match('/[\x{0600}-\x{06FF}]/u', $role->label()));
            }
        });
    }

    public function test_inventory_lang_files_have_no_duplicate_keys_and_matching_order(): void
    {
        $arPath = lang_path('ar/inventory.php');
        $enPath = lang_path('en/inventory.php');

        foreach ([$arPath, $enPath] as $path) {
            $contents = (string) file_get_contents($path);
            preg_match_all('/^\s{4}\'([^\']+)\'\s*=>/m', $contents, $matches);
            $keys = $matches[1];
            $duplicates = array_keys(array_filter(array_count_values($keys), fn (int $c): bool => $c > 1));
            $this->assertSame([], $duplicates, "Duplicate top-level keys found in {$path}: ".implode(', ', $duplicates));
        }

        $arKeys = array_keys(require $arPath);
        $enKeys = array_keys(require $enPath);
        $this->assertSame($arKeys, $enKeys, 'Top-level key order in lang/ar/inventory.php and lang/en/inventory.php must match.');
    }
}
