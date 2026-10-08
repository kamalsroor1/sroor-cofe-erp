<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\AddonType;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\BillingPaymentMethod;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\Billing\Concerns\BillingEnum;
use App\Enums\Billing\SubscriptionAddonStatus;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\PaymentMethod;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ENTI-1.1: SaaS billing enums live in App\Enums\Billing, never collide with the
 * POS PaymentMethod enum, persist stable string values, and every case has an
 * ar + en label in lang/{ar,en}/billing.php.
 */
class BillingEnumsTest extends TestCase
{
    private const LOCALES = ['ar', 'en'];

    /**
     * The stored string values are a DB contract (central `subscriptions`,
     * `subscription_addons`, `addons`, `billing_invoices`, `billing_payments`).
     *
     * @return array<string, array{class-string<BillingEnum>, list<string>}>
     */
    public static function enumProvider(): array
    {
        return [
            'SubscriptionStatus' => [SubscriptionStatus::class, ['trialing', 'active', 'past_due', 'pending_payment', 'cancelled', 'expired']],
            'SubscriptionAddonStatus' => [SubscriptionAddonStatus::class, ['active', 'pending_payment', 'cancelled', 'expired']],
            'BillingCycle' => [BillingCycle::class, ['monthly', 'yearly', 'biennial']],
            'AddonType' => [AddonType::class, ['recurring', 'service']],
            'BillingInvoiceStatus' => [BillingInvoiceStatus::class, ['draft', 'pending', 'paid', 'void', 'refunded']],
            'BillingInvoiceType' => [BillingInvoiceType::class, ['plan', 'renewal', 'upgrade', 'addon', 'service']],
            'BillingPaymentStatus' => [BillingPaymentStatus::class, ['pending', 'verified', 'rejected', 'failed', 'refunded']],
            'BillingPaymentMethod' => [BillingPaymentMethod::class, ['instapay', 'vodafone_cash', 'bank_transfer', 'cash', 'paymob_card', 'paymob_wallet', 'fawry_reference']],
            'BillingGateway' => [BillingGateway::class, ['manual', 'paymob', 'fawry']],
        ];
    }

    /**
     * @param  class-string<BillingEnum>  $enum
     * @param  list<string>  $expectedValues
     */
    #[DataProvider('enumProvider')]
    public function test_enum_values_are_a_stable_contract(string $enum, array $expectedValues): void
    {
        $this->assertSame($expectedValues, array_map(fn (BillingEnum $case) => $case->value, $enum::cases()));
        $this->assertSame($expectedValues, $enum::values());
    }

    /**
     * @param  class-string<BillingEnum>  $enum
     * @param  list<string>  $expectedValues
     */
    #[DataProvider('enumProvider')]
    public function test_every_case_has_an_ar_and_en_label(string $enum, array $expectedValues): void
    {
        foreach ($enum::cases() as $case) {
            $key = $case->translationKey();
            $this->assertStringStartsWith('billing.', $key);

            foreach (self::LOCALES as $locale) {
                $this->assertTrue(
                    Lang::has($key, $locale, false),
                    "Missing lang/{$locale}/billing.php key [{$key}]"
                );

                $label = $case->label($locale);
                $this->assertNotSame('', trim($label), "Empty {$locale} label for [{$key}]");
                $this->assertNotSame($key, $label, "Untranslated {$locale} label for [{$key}]");
            }
        }
    }

    /**
     * @param  class-string<BillingEnum>  $enum
     * @param  list<string>  $expectedValues
     */
    #[DataProvider('enumProvider')]
    public function test_lang_group_has_no_orphan_keys(string $enum, array $expectedValues): void
    {
        $group = $enum::translationGroup();

        foreach (self::LOCALES as $locale) {
            $translations = $this->loadBillingLang($locale);
            $this->assertArrayHasKey($group, $translations, "lang/{$locale}/billing.php misses group [{$group}]");
            $this->assertIsArray($translations[$group]);
            $this->assertEqualsCanonicalizing(
                $expectedValues,
                array_keys($translations[$group]),
                "lang/{$locale}/billing.php [{$group}] must map exactly the enum cases"
            );
        }
    }

    /**
     * @param  class-string<BillingEnum>  $enum
     * @param  list<string>  $expectedValues
     */
    #[DataProvider('enumProvider')]
    public function test_options_expose_value_and_translated_label(string $enum, array $expectedValues): void
    {
        app()->setLocale('en');

        $options = $enum::options();

        $this->assertCount(count($expectedValues), $options);
        foreach ($options as $i => $option) {
            $this->assertSame(['value', 'label'], array_keys($option));
            $this->assertSame($expectedValues[$i], $option['value']);
            $this->assertSame($enum::from($option['value'])->label('en'), $option['label']);
        }
    }

    public function test_label_follows_the_active_locale(): void
    {
        app()->setLocale('ar');
        $ar = SubscriptionStatus::PastDue->label();

        app()->setLocale('en');
        $en = SubscriptionStatus::PastDue->label();

        $this->assertSame(__('billing.subscription_status.past_due', [], 'ar'), $ar);
        $this->assertSame(__('billing.subscription_status.past_due', [], 'en'), $en);
        $this->assertNotSame($ar, $en);
    }

    public function test_every_billing_enum_file_is_covered_by_this_test(): void
    {
        $covered = array_values(array_map(fn (array $row) => $row[0], self::enumProvider()));

        $declared = collect(File::files(app_path('Enums/Billing')))
            ->map(fn (\SplFileInfo $file) => 'App\\Enums\\Billing\\'.$file->getBasename('.php'))
            ->all();

        $this->assertEqualsCanonicalizing($declared, $covered);
    }

    public function test_ar_and_en_billing_lang_files_have_identical_non_empty_keys(): void
    {
        $ar = $this->flatten($this->loadBillingLang('ar'));
        $en = $this->flatten($this->loadBillingLang('en'));

        $this->assertSame([], array_values(array_diff(array_keys($ar), array_keys($en))), 'Keys missing in lang/en/billing.php');
        $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($ar))), 'Keys missing in lang/ar/billing.php');

        foreach (['ar' => $ar, 'en' => $en] as $locale => $flat) {
            foreach ($flat as $key => $value) {
                $this->assertIsString($value, "lang/{$locale}/billing.php [{$key}] must be a string");
                $this->assertNotSame('', trim($value), "lang/{$locale}/billing.php [{$key}] is empty");
            }
        }
    }

    public function test_biennial_cycle_exists_but_is_not_sellable_in_phase_one(): void
    {
        // Q-E6 [CTO-2026-10-08]: biennial is an enum value only — not priced, not sold.
        $this->assertFalse(BillingCycle::Biennial->isSellable());
        $this->assertTrue(BillingCycle::Monthly->isSellable());
        $this->assertTrue(BillingCycle::Yearly->isSellable());
        $this->assertSame([BillingCycle::Monthly, BillingCycle::Yearly], BillingCycle::sellable());
    }

    public function test_payment_methods_map_to_their_gateway(): void
    {
        $this->assertSame(BillingGateway::Manual, BillingPaymentMethod::Instapay->gateway());
        $this->assertSame(BillingGateway::Manual, BillingPaymentMethod::VodafoneCash->gateway());
        $this->assertSame(BillingGateway::Manual, BillingPaymentMethod::BankTransfer->gateway());
        $this->assertSame(BillingGateway::Manual, BillingPaymentMethod::Cash->gateway());
        $this->assertSame(BillingGateway::Paymob, BillingPaymentMethod::PaymobCard->gateway());
        $this->assertSame(BillingGateway::Paymob, BillingPaymentMethod::PaymobWallet->gateway());
        $this->assertSame(BillingGateway::Fawry, BillingPaymentMethod::FawryReference->gateway());

        $this->assertSame(
            [BillingPaymentMethod::Instapay, BillingPaymentMethod::VodafoneCash, BillingPaymentMethod::BankTransfer, BillingPaymentMethod::Cash],
            BillingPaymentMethod::forGateway(BillingGateway::Manual)
        );
    }

    public function test_billing_payment_method_does_not_replace_the_pos_payment_method(): void
    {
        $this->assertNotSame(PaymentMethod::class, BillingPaymentMethod::class);
        // The POS enum keeps its own cases untouched (no SaaS gateway methods leak in).
        $posValues = array_map(fn (PaymentMethod $method): string => $method->value, PaymentMethod::cases());
        foreach (['paymob_card', 'paymob_wallet', 'fawry_reference', 'vodafone_cash'] as $saasOnly) {
            $this->assertNotContains($saasOnly, $posValues);
        }
    }

    /** @return array<string, mixed> */
    private function loadBillingLang(string $locale): array
    {
        $path = lang_path("{$locale}/billing.php");
        $this->assertFileExists($path);

        $data = require $path;
        $this->assertIsArray($data);

        return $data;
    }

    /**
     * @param  array<string|int, mixed>  $data
     * @return array<string, mixed>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $full = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $out += $this->flatten($value, $full);
            } else {
                $out[$full] = $value;
            }
        }

        return $out;
    }
}
