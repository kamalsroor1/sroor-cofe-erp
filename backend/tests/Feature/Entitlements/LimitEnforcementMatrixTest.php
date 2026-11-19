<?php

declare(strict_types=1);

namespace Tests\Feature\Entitlements;

use App\Actions\Customers\ResolveWalkInCustomerAction;
use App\Enums\LimitResource;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Plan;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Entitlements\MonthlyInvoiceCounter;
use App\Support\PartyLimitCounter;
use App\Support\StoreLimitBucket;
use Database\Seeders\Catalog\FeatureCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * PLAN-AUDIT (2026-11-19): every plan limit (LimitResource) x every path that creates one more
 * counted record. For each path the same three rules hold:
 *  - below the limit the create succeeds, the record that reaches the limit is refused with 403
 *    `subscription.limit_reached` (details.limit / details.max) and nothing is written;
 *  - a limit of 0 means "not included": the very first one is refused;
 *  - NULL means unlimited.
 *
 * Adding a limit or a create path = one row in createPaths() and one arm in create(). storage_mb is
 * the only resource outside the matrix (it is measured in megabytes of uploaded files, not in
 * records): see PlanUsageLimitsApiTest (logo / settings logo / import file).
 */
#[Group('entitlements')]
final class LimitEnforcementMatrixTest extends TenantTestCase
{
    /** Resources the matrix leaves to a dedicated test, with the reason. */
    private const OUTSIDE_THE_MATRIX = [
        'storage_mb' => 'bytes of uploaded files, PlanUsageLimitsApiTest',
    ];

    /** @var array<string, array{item: int, customer: int}> */
    private array $saleFixtures = [];

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function createPaths(): iterable
    {
        yield 'users: POST /users' => ['users', 'users.api'];
        yield 'stores: POST /stores' => ['stores', 'stores.api'];
        yield 'stores: trash restore' => ['stores', 'stores.restore'];
        yield 'warehouses: POST /stores' => ['warehouses', 'warehouses.api'];
        yield 'warehouses: trash restore' => ['warehouses', 'warehouses.restore'];
        yield 'warehouses: change a branch into a warehouse' => ['warehouses', 'warehouses.retype'];
        yield 'vans: POST /stores' => ['vans', 'vans.api'];
        yield 'vans: trash restore' => ['vans', 'vans.restore'];
        yield 'items: POST /items' => ['items', 'items.api'];
        yield 'items: Excel import' => ['items', 'items.import'];
        yield 'items: trash restore' => ['items', 'items.restore'];
        yield 'customers: POST /customers' => ['customers', 'customers.api'];
        yield 'customers: POS quick add' => ['customers', 'customers.pos_quick'];
        yield 'customers: Excel import' => ['customers', 'customers.import'];
        yield 'customers: trash restore' => ['customers', 'customers.restore'];
        yield 'suppliers: POST /suppliers' => ['suppliers', 'suppliers.api'];
        yield 'suppliers: Excel import' => ['suppliers', 'suppliers.import'];
        yield 'suppliers: trash restore' => ['suppliers', 'suppliers.restore'];
        yield 'invoices_month: POST /invoices' => ['invoices_month', 'invoices.api'];
        yield 'invoices_month: POS checkout' => ['invoices_month', 'invoices.pos'];
        yield 'invoices_month: mixes invoice' => ['invoices_month', 'invoices.mixes'];
        yield 'invoices_month: legacy coffee-blender invoice' => ['invoices_month', 'invoices.blender'];
    }

    public function test_every_limit_resource_has_a_create_path_in_the_matrix(): void
    {
        $covered = array_unique(array_map(static fn (array $row): string => $row[0], iterator_to_array(self::createPaths())));
        $expected = array_values(array_diff(LimitResource::values(), array_keys(self::OUTSIDE_THE_MATRIX)));

        sort($covered);
        sort($expected);
        $this->assertSame($expected, $covered, 'A LimitResource has no create path in the matrix: add one.');
    }

    #[DataProvider('createPaths')]
    public function test_below_the_limit_succeeds_and_the_one_past_it_is_refused(string $resource, string $path): void
    {
        $limit = LimitResource::from($resource);
        $tenant = $this->tenantWithLimit($limit, null);
        $baseline = $this->usage($tenant, $limit);
        $this->setPlanLimit($tenant, $limit, $baseline + 1);
        $headers = $this->tenantHeaders($tenant);

        $this->assertTrue($this->create($tenant, $headers, $path, 1)->isSuccessful(), "{$path}: the create that reaches the limit must succeed.");
        $this->assertSame($baseline + 1, $this->usage($tenant, $limit));

        $this->create($tenant, $headers, $path, 2)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'subscription.limit_reached')
            ->assertJsonPath('details.limit', $resource)
            ->assertJsonPath('details.max', $baseline + 1);

        $this->assertSame($baseline + 1, $this->usage($tenant, $limit), "{$path}: a refused create must write nothing.");
    }

    #[DataProvider('createPaths')]
    public function test_a_zero_limit_means_not_included(string $resource, string $path): void
    {
        $limit = LimitResource::from($resource);
        $tenant = $this->tenantWithLimit($limit, 0);
        $baseline = $this->usage($tenant, $limit);

        $this->create($tenant, $this->tenantHeaders($tenant), $path, 1)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'subscription.limit_reached')
            ->assertJsonPath('details.limit', $resource)
            ->assertJsonPath('details.max', 0);

        $this->assertSame($baseline, $this->usage($tenant, $limit));
    }

    #[DataProvider('createPaths')]
    public function test_a_null_limit_is_unlimited(string $resource, string $path): void
    {
        $limit = LimitResource::from($resource);
        $tenant = $this->tenantWithLimit($limit, null);
        $headers = $this->tenantHeaders($tenant);
        $baseline = $this->usage($tenant, $limit);

        foreach ([1, 2, 3] as $sequence) {
            $this->assertTrue($this->create($tenant, $headers, $path, $sequence)->isSuccessful(), "{$path} #{$sequence} on an unlimited plan.");
        }

        $this->assertSame($baseline + 3, $this->usage($tenant, $limit));
    }

    public function test_renaming_the_walk_in_customer_cannot_free_a_customers_slot(): void
    {
        $tenant = $this->tenantWithLimit(LimitResource::Customers, 1);
        $headers = $this->tenantHeaders($tenant);
        $this->postJson('/api/v1/customers', ['name' => 'Only customer'], $headers)->assertCreated();

        // A sale without a customer creates the walk-in row (no slot taken).
        $sale = $this->salePayload($tenant);
        $this->inTenant($tenant, fn () => Customer::query()->whereKey($sale['customer_id'])->forceDelete());
        unset($sale['customer_id']);
        $this->postJson('/api/v1/pos/checkout', $sale, $headers)->assertCreated();
        $walkIn = $this->inTenant($tenant, fn (): Customer => Customer::query()->where('phone', ResolveWalkInCustomerAction::PLACEHOLDER_PHONE)->sole());
        $this->assertSame(1, $this->usage($tenant, LimitResource::Customers));

        // The exploit: rename it so it counts as a real customer, then let the next sale make a new walk-in.
        $this->putJson("/api/v1/customers/{$walkIn->id}", ['name' => 'Real name', 'phone' => '01000009999'], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
        $this->postJson('/api/v1/pos/checkout', $sale, $headers)->assertCreated();

        $this->assertSame(2, $this->inTenant($tenant, fn (): int => Customer::query()->count()));
        $this->assertSame(1, $this->usage($tenant, LimitResource::Customers));

        // Editing the walk-in's other fields stays allowed.
        $this->putJson("/api/v1/customers/{$walkIn->id}", [
            'name' => $walkIn->name, 'phone' => $walkIn->phone, 'notes' => 'cash counter',
        ], $headers)->assertOk();
    }

    // ----------------------------------------------------------------- helpers

    private function tenantWithLimit(LimitResource $resource, ?int $max): Tenant
    {
        $slug = 'matrix-'.Str::lower(Str::random(10));
        $plan = Plan::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'price_monthly' => '499.000',
            'price_yearly' => '4990.000',
            'features' => array_fill_keys(FeatureCatalog::keys(), true),
            'max_users' => null,
            'max_stores' => null,
            'max_warehouses' => null,
            'max_vans' => null,
            'max_items' => null,
            'max_invoices_per_month' => null,
            'max_storage_mb' => null,
            'max_customers' => null,
            'max_suppliers' => null,
            $resource->planColumn() => $max,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return $this->createTenant(['plan_id' => $plan->id, 'status' => 'active']);
    }

    /** Changes the plan row the way the console does (the plan observer drops the cached entitlements). */
    private function setPlanLimit(Tenant $tenant, LimitResource $resource, int $max): void
    {
        Plan::query()->findOrFail($tenant->plan_id)->update([$resource->planColumn() => $max]);
    }

    private function usage(Tenant $tenant, LimitResource $resource): int
    {
        return $this->inTenant($tenant, fn (): int => match ($resource) {
            LimitResource::Users => User::query()->count(),
            LimitResource::Stores, LimitResource::Warehouses, LimitResource::Vans => StoreLimitBucket::count($resource),
            LimitResource::Items => Item::query()->count(),
            LimitResource::Customers => PartyLimitCounter::customers(),
            LimitResource::Suppliers => PartyLimitCounter::suppliers(),
            LimitResource::InvoicesMonth => app(MonthlyInvoiceCounter::class)->count(),
            LimitResource::StorageMb => $this->fail('storage_mb is outside the matrix.'),
        });
    }

    /**
     * Creates ONE more counted record through $path. $sequence keeps names / phones unique.
     *
     * @param  array<string, string>  $headers
     */
    private function create(Tenant $tenant, array $headers, string $path, int $sequence): TestResponse
    {
        $tag = $sequence.'-'.Str::lower(Str::random(6));

        return match ($path) {
            'users.api' => $this->postJson('/api/v1/users', [
                'name' => 'Cashier '.$tag,
                'phone' => sprintf('0102%07d', random_int(0, 9_999_999)),
                'password' => 'secret123',
                'role' => 'cashier',
                'store_ids' => [(int) $this->tenantStore($tenant)->id],
            ], $headers),
            'stores.api' => $this->postJson('/api/v1/stores', ['name' => 'Branch '.$tag, 'type' => 'retail_shop'], $headers),
            'warehouses.api' => $this->postJson('/api/v1/stores', ['name' => 'WH '.$tag, 'type' => 'main_warehouse'], $headers),
            'vans.api' => $this->postJson('/api/v1/stores', ['name' => 'Van '.$tag, 'type' => 'wholesale_van'], $headers),
            'stores.restore' => $this->restore($tenant, $headers, 'stores', fn (): Store => $this->rawStore('retail_shop', $tag)),
            'warehouses.restore' => $this->restore($tenant, $headers, 'stores', fn (): Store => $this->rawStore('main_warehouse', $tag)),
            'vans.restore' => $this->restore($tenant, $headers, 'stores', fn (): Store => $this->rawStore('wholesale_van', $tag)),
            'warehouses.retype' => $this->putJson(
                '/api/v1/stores/'.$this->inTenant($tenant, fn (): int => (int) $this->rawStore('retail_shop', $tag)->id),
                ['name' => 'Retyped '.$tag, 'type' => 'main_warehouse'],
                $headers,
            ),
            'items.api' => $this->postJson('/api/v1/items', ['name' => 'Item '.$tag, 'unit' => 'كجم', 'cost_price' => '10.000', 'selling_price' => '15.000'], $headers),
            'items.import' => $this->import($headers, 'items', ['name' => 'Imported item '.$tag, 'unit' => 'كجم', 'cost_price' => '10', 'selling_price' => '15']),
            'items.restore' => $this->restore($tenant, $headers, 'items', fn (): Item => Item::query()->create([
                'name' => 'Trashed item '.$tag, 'code' => 'TR-'.Str::upper(Str::random(8)), 'unit' => 'كجم',
                'cost_price' => '10.000', 'selling_price' => '15.000', 'is_active' => true,
            ])),
            'customers.api' => $this->postJson('/api/v1/customers', ['name' => 'Customer '.$tag], $headers),
            'customers.pos_quick' => $this->postJson('/api/v1/pos/quick-customer', ['name' => 'Quick '.$tag, 'phone' => sprintf('0103%07d', random_int(0, 9_999_999))], $headers),
            'customers.import' => $this->import($headers, 'customers', ['name' => 'Imported customer '.$tag]),
            'customers.restore' => $this->restore($tenant, $headers, 'customers', fn (): Customer => Customer::query()->create([
                'name' => 'Trashed customer '.$tag, 'current_balance' => '0.000', 'is_active' => true,
            ])),
            'suppliers.api' => $this->postJson('/api/v1/suppliers', ['name' => 'Supplier '.$tag], $headers),
            'suppliers.import' => $this->import($headers, 'suppliers', ['name' => 'Imported supplier '.$tag]),
            'suppliers.restore' => $this->restore($tenant, $headers, 'suppliers', fn (): Supplier => Supplier::query()->create([
                'name' => 'Trashed supplier '.$tag, 'current_balance' => '0.000', 'is_active' => true,
            ])),
            'invoices.api' => $this->postJson('/api/v1/invoices', $this->salePayload($tenant), $headers),
            'invoices.pos' => $this->postJson('/api/v1/pos/checkout', $this->salePayload($tenant), $headers),
            'invoices.mixes' => $this->postJson('/api/v1/mixes/invoice', $this->blendPayload($tenant), $headers),
            'invoices.blender' => $this->postJson('/api/v1/coffee-blender/invoice', $this->blendPayload($tenant), $headers),
            default => $this->fail("Unknown create path [{$path}]."),
        };
    }

    /**
     * @param  array<string, string>  $headers
     * @param  callable(): Model  $make
     */
    private function restore(Tenant $tenant, array $headers, string $type, callable $make): TestResponse
    {
        $id = $this->inTenant($tenant, function () use ($make): int {
            $model = $make();
            $model->delete();

            return (int) $model->getKey();
        });

        return $this->postJson("/api/v1/trash/{$type}/{$id}/restore", [], $headers);
    }

    private function rawStore(string $type, string $tag): Store
    {
        return Store::query()->create([
            'name' => 'Raw '.$type.' '.$tag,
            'code' => 'RAW-'.Str::upper(Str::random(8)),
            'type' => $type,
            'is_active' => true,
        ]);
    }

    /**
     * Preview + commit of a one-row import file.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, string>  $row  column key => value
     */
    private function import(array $headers, string $type, array $row): TestResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'mtx').'.csv';
        $handle = fopen($path, 'w');
        $this->assertNotFalse($handle);
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_map(static fn (string $key): string => (string) __("import.columns.{$type}.{$key}"), array_keys($row)), ',', '"', '');
        fputcsv($handle, array_values($row), ',', '"', '');
        fclose($handle);

        $token = (string) $this->post("/api/v1/imports/{$type}/preview", ['file' => new UploadedFile($path, $type.'.csv', null, null, true)], $headers)
            ->assertOk()
            ->json('data.token');

        return $this->postJson("/api/v1/imports/{$type}/commit", ['token' => $token, 'skip_invalid' => false], $headers);
    }

    /**
     * @return array{item: int, customer: int}
     */
    private function saleFixture(Tenant $tenant): array
    {
        return $this->saleFixtures[(string) $tenant->getTenantKey()] ??= $this->inTenant($tenant, function () use ($tenant): array {
            $item = Item::query()->create([
                'name' => 'بن الحدود', 'code' => 'MTX-'.Str::random(6), 'unit' => 'كجم', 'is_weighted' => true,
                'cost_price' => '10.000', 'selling_price' => '20.000', 'current_stock' => '100.000', 'is_active' => true,
            ]);
            StoreStock::query()->create(['store_id' => $this->tenantStore($tenant)->getKey(), 'item_id' => $item->id, 'quantity' => '100.000']);
            $customer = Customer::query()->create(['name' => 'عميل المصفوفة', 'phone' => '01000009'.random_int(100, 999), 'current_balance' => '0.000', 'is_active' => true]);

            return ['item' => (int) $item->id, 'customer' => (int) $customer->id];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function salePayload(Tenant $tenant): array
    {
        $fixture = $this->saleFixture($tenant);

        return [
            'customer_id' => $fixture['customer'],
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [['item_id' => $fixture['item'], 'quantity' => '1.000', 'unit_price' => '20.000']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blendPayload(Tenant $tenant): array
    {
        $fixture = $this->saleFixture($tenant);

        return [
            'blend_name' => 'خلطة الحدود',
            'customer_id' => $fixture['customer'],
            'components' => [['item_id' => $fixture['item'], 'grams' => 250, 'unit_price' => '20.000']],
        ];
    }
}
