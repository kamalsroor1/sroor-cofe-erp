<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\PlatformSetting;
use App\Services\Branding\PlatformBranding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * #232 spec F / UI spec 2b + 6b: per-method payment details.
 *  - tenant GET /api/v1/billing/subscription `payment_methods`: ONLY fully configured methods, exact
 *    key set, nothing else of the platform settings;
 *  - super-admin PUT/GET /api/v1/super-admin/platform-settings: the new keys, their validation
 *    (IBAN = EG + 27 digits, digits-only account number, Egyptian mobile...), normalisation and the
 *    computed per-method status; 401 / 403;
 *  - the data migration of the old shared account name / note.
 * Every number / IBAN here is an obvious dummy.
 */
#[Group('billing')]
final class PaymentMethodDetailsApiTest extends BillingTestCase
{
    private const SETTINGS = '/api/v1/super-admin/platform-settings';

    private const IBAN = 'EG380019000500000000263180002';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
    }

    /** A label in the locale the API answers in (the app default, Arabic). */
    private function t(string $key): string
    {
        return (string) __($key, [], (string) config('app.locale'));
    }

    /**
     * @param  array<string, string|null>  $values
     */
    private function configure(array $values): void
    {
        $this->endTenancy();
        app(PlatformBranding::class)->update($values);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function methods(): array
    {
        $tenant = $this->createTenant();

        return $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))->assertOk()->json('data.payment_methods');
    }

    /** @return array<string, string> */
    private function bank(): array
    {
        return [
            'billing_bank_name' => 'Dummy Bank',
            'billing_bank_account_holder' => 'Dummy Holder',
            'billing_bank_account_number' => '1234567890',
            'billing_bank_iban' => self::IBAN,
        ];
    }

    // ------------------------------------------------------------------ tenant payload

    public function test_no_configured_method_gives_an_empty_list(): void
    {
        $this->configure(['billing_vodafone_cash_note' => 'A note alone does not configure a method.', 'billing_bank_name' => 'Dummy Bank']);

        $this->assertSame([], $this->methods());
    }

    public function test_one_method_has_exactly_its_contracted_shape(): void
    {
        $this->configure(['billing_vodafone_cash_number' => '01000000123', 'billing_vodafone_cash_note' => 'Write your shop name.', 'support_email' => 'support@example.test', 'billing_tax_rate' => '14']);

        $this->assertSame([[
            'key' => 'vodafone_cash',
            'label' => $this->t('billing.billing_payment_method.vodafone_cash'),
            'details' => [['key' => 'number', 'label' => $this->t('billing.payment_method_details.vodafone_cash.number'), 'value' => '01000000123', 'copyable' => true]],
            'note' => 'Write your shop name.',
        ]], $this->methods());
    }

    public function test_methods_come_in_order_with_only_filled_details_and_a_null_note_when_empty(): void
    {
        $this->configure($this->bank() + [
            'billing_instapay_address' => 'dummy@instapay',
            'billing_vodafone_cash_number' => '01000000123',
            'billing_bank_branch' => 'Main branch',
            'billing_bank_note' => 'Bank transfers take one working day.',
        ]);

        $methods = $this->methods();

        $this->assertSame(['vodafone_cash', 'instapay', 'bank_transfer'], array_column($methods, 'key'));
        $this->assertNull($methods[0]['note']);
        $this->assertSame([['key' => 'address', 'label' => $this->t('billing.payment_method_details.instapay.address'), 'value' => 'dummy@instapay', 'copyable' => true]], $methods[1]['details']);
        $this->assertSame([
            ['key' => 'bank_name', 'label' => $this->t('billing.payment_method_details.bank_transfer.bank_name'), 'value' => 'Dummy Bank', 'copyable' => true],
            ['key' => 'account_holder', 'label' => $this->t('billing.payment_method_details.bank_transfer.account_holder'), 'value' => 'Dummy Holder', 'copyable' => true],
            ['key' => 'account_number', 'label' => $this->t('billing.payment_method_details.bank_transfer.account_number'), 'value' => '1234567890', 'copyable' => true],
            ['key' => 'iban', 'label' => $this->t('billing.payment_method_details.bank_transfer.iban'), 'value' => self::IBAN, 'copyable' => true],
            ['key' => 'branch', 'label' => $this->t('billing.payment_method_details.bank_transfer.branch'), 'value' => 'Main branch', 'copyable' => false],
        ], $methods[2]['details']);
        $this->assertSame('Bank transfers take one working day.', $methods[2]['note']);

        foreach ($methods as $method) {
            $this->assertSame(['key', 'label', 'details', 'note'], array_keys($method));
            foreach ($method['details'] as $detail) {
                $this->assertSame(['key', 'label', 'value', 'copyable'], array_keys($detail));
            }
        }
    }

    public function test_a_bank_transfer_missing_any_required_detail_is_not_offered(): void
    {
        foreach (array_keys($this->bank()) as $missing) {
            $values = $this->bank();
            $values[$missing] = null;
            $this->configure($values + ['billing_instapay_address' => 'dummy@instapay']);

            $this->assertSame(['instapay'], array_column($this->methods(), 'key'), $missing.' empty must hide the bank transfer');
        }

        // The optional branch is not needed, and is not listed when empty.
        $this->configure($this->bank() + ['billing_bank_branch' => null]);
        $bank = collect($this->methods())->firstWhere('key', 'bank_transfer');
        $this->assertSame(['bank_name', 'account_holder', 'account_number', 'iban'], array_column($bank['details'], 'key'));
    }

    public function test_labels_are_translated_never_raw_keys(): void
    {
        $this->configure($this->bank() + ['billing_instapay_address' => 'dummy@instapay', 'billing_vodafone_cash_number' => '01000000123']);

        foreach ($this->methods() as $method) {
            $this->assertStringNotContainsString('billing.', $method['label']);
            foreach ($method['details'] as $detail) {
                $this->assertStringNotContainsString('payment_method_details', $detail['label']);
            }
        }
    }

    // ------------------------------------------------------------------ super-admin settings

    public function test_put_normalises_and_stores_the_details_and_get_reports_the_status(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $this->putJson(self::SETTINGS, [
            'billing_vodafone_cash_number' => '0100 000-0123',
            'billing_vodafone_cash_note' => 'Write your shop name.',
            'billing_bank_name' => 'Dummy Bank',
            'billing_bank_account_number' => '1234 5678 90',
            'billing_bank_iban' => 'eg38 0019 0005 0000 0000 2631 8000 2',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.billing_vodafone_cash_number', '01000000123')
            ->assertJsonPath('data.billing_bank_account_number', '1234567890')
            ->assertJsonPath('data.billing_bank_iban', self::IBAN)
            ->assertJsonPath('data.payment_methods_status.vodafone_cash', ['status' => 'visible', 'visible' => true, 'missing' => [], 'missing_labels' => []])
            ->assertJsonPath('data.payment_methods_status.instapay', ['status' => 'empty', 'visible' => false, 'missing' => ['billing_instapay_address'], 'missing_labels' => [$this->t('billing.console.settings.instapay_address')]])
            ->assertJsonPath('data.payment_methods_status.bank_transfer', ['status' => 'missing', 'visible' => false, 'missing' => ['billing_bank_account_holder'], 'missing_labels' => [$this->t('billing.console.settings.bank_account_holder')]]);

        $this->assertDatabaseHas('platform_settings', ['key' => 'billing_bank_iban', 'value' => self::IBAN]);

        $this->getJson(self::SETTINGS, $this->centralHeaders($this->centralSupport()))
            ->assertOk()
            ->assertJsonPath('data.billing_bank_name', 'Dummy Bank')
            ->assertJsonPath('data.billing_bank_branch', '')
            ->assertJsonPath('data.payment_methods_status.bank_transfer.status', 'missing');
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string|null}>
     */
    public static function invalidDetails(): array
    {
        return [
            'iban without EG' => [['billing_bank_iban' => 'DE89370400440532013000'], 'billing_bank_iban', 'billing.console.settings.iban_invalid'],
            'iban 26 digits' => [['billing_bank_iban' => 'EG'.str_repeat('1', 26)], 'billing_bank_iban', 'billing.console.settings.iban_invalid'],
            'iban 28 digits' => [['billing_bank_iban' => 'EG'.str_repeat('1', 28)], 'billing_bank_iban', 'billing.console.settings.iban_invalid'],
            'iban with letters' => [['billing_bank_iban' => 'EG38001900050000000026318000A'], 'billing_bank_iban', 'billing.console.settings.iban_invalid'],
            'account with letters' => [['billing_bank_account_number' => '12345ABC'], 'billing_bank_account_number', 'billing.console.settings.bank_account_number_invalid'],
            'account with dashes' => [['billing_bank_account_number' => '1234-5678'], 'billing_bank_account_number', 'billing.console.settings.bank_account_number_invalid'],
            'account over 34 digits' => [['billing_bank_account_number' => str_repeat('1', 35)], 'billing_bank_account_number', 'billing.console.settings.bank_account_number_invalid'],
            'vodafone 10 digits' => [['billing_vodafone_cash_number' => '0100000012'], 'billing_vodafone_cash_number', 'billing.console.settings.vodafone_number_invalid'],
            'vodafone not 01' => [['billing_vodafone_cash_number' => '02000000123'], 'billing_vodafone_cash_number', 'billing.console.settings.vodafone_number_invalid'],
            'instapay free text' => [['billing_instapay_address' => 'pay me'], 'billing_instapay_address', 'billing.console.settings.instapay_address_invalid'],
            'note over 300' => [['billing_bank_note' => str_repeat('x', 301)], 'billing_bank_note', null],
            'bank name markup' => [['billing_bank_name' => '<b>Bank</b>'], 'billing_bank_name', null],
            'bank name over 120' => [['billing_bank_account_holder' => str_repeat('x', 121)], 'billing_bank_account_holder', null],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidDetails')]
    public function test_invalid_details_are_422_and_write_nothing(array $payload, string $field, ?string $message): void
    {
        $response = $this->putJson(self::SETTINGS, $payload, $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field]);

        if ($message !== null) {
            $response->assertJsonPath('errors.'.$field.'.0', $this->t($message));
        }

        $this->assertSame(0, PlatformSetting::query()->where('key', $field)->count());
    }

    public function test_settings_writes_are_401_for_guests_and_403_without_the_permission_or_step_up(): void
    {
        $payload = ['billing_bank_iban' => self::IBAN];

        $this->putJson(self::SETTINGS, $payload, ['Accept' => 'application/json'])->assertUnauthorized();
        $this->putJson(self::SETTINGS, $payload, $this->steppedUpCentralHeaders($this->centralSupport()))->assertForbidden();
        $this->putJson(self::SETTINGS, $payload, $this->centralHeaders($this->centralSuperAdmin()))->assertForbidden();

        $this->assertSame(0, PlatformSetting::query()->where('key', 'billing_bank_iban')->count());
    }

    // ------------------------------------------------------------------ migration of the old keys

    private function migration(): Migration
    {
        $migration = require database_path('migrations/2026_11_26_110100_split_billing_payment_details_per_method.php');
        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    /**
     * @return array<string, string|null>
     */
    private function stored(): array
    {
        /** @var array<string, string|null> $rows */
        $rows = DB::table('platform_settings')->pluck('value', 'key')->all();

        return $rows;
    }

    public function test_the_migration_copies_the_shared_values_without_overwriting_and_is_idempotent(): void
    {
        $this->configure([
            'billing_account_name' => '  Dummy Holder ',
            'billing_payment_note' => 'Write your shop name.',
            'billing_instapay_note' => 'Typed by an operator.',
            'billing_bank_note' => '',
        ]);
        DB::table('platform_settings')->where('key', 'billing_bank_note')->update(['value' => '   ']);

        $this->migration()->up();
        $this->migration()->up();

        $stored = $this->stored();
        $this->assertSame('Dummy Holder', $stored['billing_bank_account_holder']);
        $this->assertSame('Write your shop name.', $stored['billing_vodafone_cash_note']);
        $this->assertSame('Typed by an operator.', $stored['billing_instapay_note']);
        $this->assertSame('Write your shop name.', $stored['billing_bank_note']);
        // The old keys stay readable.
        $this->assertSame('  Dummy Holder ', $stored['billing_account_name']);
        $this->assertSame('Write your shop name.', $stored['billing_payment_note']);
        $this->assertSame(1, DB::table('platform_settings')->where('key', 'billing_vodafone_cash_note')->count());

        // The copied note is served at once (cache bumped).
        $this->assertSame('Write your shop name.', app(PlatformBranding::class)->get()->billingVodafoneCashNote);

        // down() removes only the untouched copies; the operator's note survives.
        $this->migration()->down();
        $after = $this->stored();
        $this->assertArrayNotHasKey('billing_bank_account_holder', $after);
        $this->assertArrayNotHasKey('billing_vodafone_cash_note', $after);
        $this->assertSame('Typed by an operator.', $after['billing_instapay_note']);
        $this->assertSame('Write your shop name.', $after['billing_payment_note']);
    }

    public function test_the_migration_does_nothing_without_old_values(): void
    {
        $this->configure(['billing_account_name' => '', 'billing_payment_note' => null]);

        $this->migration()->up();

        $this->assertSame(0, DB::table('platform_settings')->whereIn('key', [
            'billing_bank_account_holder', 'billing_vodafone_cash_note', 'billing_instapay_note', 'billing_bank_note',
        ])->count());
    }
}
