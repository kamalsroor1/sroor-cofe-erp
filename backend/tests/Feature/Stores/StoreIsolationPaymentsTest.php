<?php

declare(strict_types=1);

namespace Tests\Feature\Stores;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * STOR-2 (coordinator item 2): the four cash-payment writes (customer receipt, supplier voucher,
 * customer collect-payment, supplier pay) run under `store.active:concrete`, so the drawer store
 * PaymentService records in payments.store_id is the verified X-Store-Id:
 *  - the payment is booked on the header's store, never on the main store by fallback;
 *  - a header for a store the user cannot access, or `all`, is 403 and no payment / balance moves;
 *  - a deactivated branch is 403 store_inactive.
 */
final class StoreIsolationPaymentsTest extends TenantTestCase
{
    private const PERMISSIONS = ['customers.manage', 'suppliers.manage'];

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function paymentWrites(): array
    {
        return [
            'customer receipt' => ['api.payments.customer_receipt', '/api/v1/payments/customer-receipt', 'customer'],
            'supplier voucher' => ['api.payments.supplier_voucher', '/api/v1/payments/supplier-voucher', 'supplier'],
            'customer collect-payment' => ['api.customers.collect_payment', '/api/v1/customers/{customer}/collect-payment', 'customer'],
            'supplier pay' => ['api.suppliers.pay', '/api/v1/suppliers/{supplier}/pay', 'supplier'],
        ];
    }

    #[DataProvider('paymentWrites')]
    public function test_the_payment_is_booked_on_the_verified_active_store(string $name, string $uri, string $party): void
    {
        $route = Route::getRoutes()->getByName($name);
        $this->assertNotNull($route);
        $this->assertContains('store.active:concrete', $route->gatherMiddleware(), $name);

        $tenant = $this->createTenant();
        $seed = $this->seedParties($tenant);
        $userOfB = $this->createTenantUser($tenant, 'cashier', self::PERMISSIONS);
        $this->inTenant($tenant, fn () => User::query()->findOrFail($userOfB->id)->stores()->attach($seed['b']));

        [$url, $payload] = $this->request($uri, $seed, '12.500');

        // Receipts/vouchers answer 201, collect-payment/pay 200 (their existing contracts).
        $id = (int) $this->postJson($url, $payload, $this->tenantHeaders($tenant, $userOfB, $seed['b']))
            ->assertSuccessful()
            ->json('data.id');

        $payment = $this->inTenant($tenant, fn () => $id > 0
            ? Payment::query()->findOrFail($id)
            : Payment::query()->latest('id')->firstOrFail());
        $this->assertSame($seed['b'], (int) $payment->store_id, $name.' booked on the wrong store');
        $this->assertSame('12.500', (string) $payment->amount);
        $this->assertSame(1, $this->inTenant($tenant, fn (): int => Payment::query()->count()));
        $this->assertNotSame($seed['before_'.$party], $this->balanceOf($tenant, $party, $seed[$party]));
    }

    #[DataProvider('paymentWrites')]
    public function test_an_inaccessible_store_all_and_an_inactive_store_are_refused_and_nothing_moves(string $name, string $uri, string $party): void
    {
        $tenant = $this->createTenant();
        $seed = $this->seedParties($tenant);
        $userOfA = $this->createTenantUser($tenant, 'cashier', self::PERMISSIONS);
        [$url, $payload] = $this->request($uri, $seed, '5.000');

        $this->postJson($url, $payload, $this->tenantHeaders($tenant, $userOfA, $seed['b']))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'store_access_denied');

        $all = $this->tenantHeaders($tenant);
        $all['X-Store-Id'] = 'all';
        $this->postJson($url, $payload, $all)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'concrete_store_required');

        $this->postJson($url, $payload, $this->tenantHeaders($tenant, null, $seed['closed']))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'store_inactive');

        $this->assertSame(0, $this->inTenant($tenant, fn (): int => Payment::query()->count()), $name);
        $this->assertSame($seed['before_'.$party], $this->balanceOf($tenant, $party, $seed[$party]));
    }

    /**
     * @param  array<string, mixed>  $seed
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function request(string $uri, array $seed, string $amount): array
    {
        $payload = [
            'amount' => $amount,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ];

        if (str_contains($uri, '/payments/customer-receipt')) {
            $payload['customer_id'] = $seed['customer'];
        } elseif (str_contains($uri, '/payments/supplier-voucher')) {
            $payload['supplier_id'] = $seed['supplier'];
        }

        $url = str_replace(['{customer}', '{supplier}'], [(string) $seed['customer'], (string) $seed['supplier']], $uri);

        return [$url, $payload];
    }

    /**
     * Store A = harness main store; B an extra branch; a closed branch; a customer who owes
     * 100.000 and a supplier owed 100.000.
     *
     * @return array<string, mixed>
     */
    private function seedParties(Tenant $tenant): array
    {
        $storeA = (int) $this->tenantStore($tenant)->id;

        $seed = $this->inTenant($tenant, function () use ($storeA): array {
            $make = fn (string $code, bool $active): int => (int) Store::query()->create([
                'name' => 'فرع '.$code,
                'code' => $code,
                'type' => 'retail',
                'is_main' => false,
                'is_active' => $active,
            ])->id;

            $customer = Customer::query()->create([
                'name' => 'عميل السداد',
                'phone' => '01000007721',
                'current_balance' => '100.000',
                'is_active' => true,
            ]);
            $supplier = Supplier::query()->create([
                'name' => 'مورد السداد',
                'phone' => '01000007722',
                'current_balance' => '100.000',
                'is_active' => true,
            ]);

            return [
                'a' => $storeA,
                'b' => $make('PAY-B', true),
                'closed' => $make('PAY-OFF', false),
                'customer' => (int) $customer->id,
                'supplier' => (int) $supplier->id,
            ];
        });

        $seed['before_customer'] = $this->balanceOf($tenant, 'customer', $seed['customer']);
        $seed['before_supplier'] = $this->balanceOf($tenant, 'supplier', $seed['supplier']);

        return $seed;
    }

    private function balanceOf(Tenant $tenant, string $party, int $id): string
    {
        return (string) $this->inTenant($tenant, fn () => $party === 'customer'
            ? Customer::query()->whereKey($id)->value('current_balance')
            : Supplier::query()->whereKey($id)->value('current_balance'));
    }
}
