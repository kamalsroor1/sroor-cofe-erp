<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Plan;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\CustomerBalanceService;
use App\Services\DocumentNumberService;
use App\Services\StockService;
use App\Services\SupplierBalanceService;
use App\Support\TenantCache;
use App\Support\TenantClock;
use Database\Seeders\Catalog\FeatureCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * PRD-06: Excel/CSV import wizard (template / preview / commit).
 */
class ImportApiTest extends TenantTestCase
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
    }

    // ---------------------------------------------------------------- fixtures

    /**
     * @param  list<list<string>>  $rows
     */
    private function xlsx(array $rows, string $name = 'import.xlsx'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imt').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return new UploadedFile($path, $name, null, null, true);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function csv(array $rows, bool $bom = true, string $name = 'import.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imt').'.csv';
        $handle = fopen($path, 'w');
        if ($bom) {
            fwrite($handle, "\xEF\xBB\xBF");
        }
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }
        fclose($handle);

        return new UploadedFile($path, $name, null, null, true);
    }

    /**
     * @return list<string>
     */
    private function headers(string $type, string $locale = 'ar'): array
    {
        $columns = [
            'items' => ['code', 'name', 'category', 'unit', 'cost_price', 'selling_price', 'min_selling_price', 'min_stock_level', 'is_weighted', 'opening_quantity', 'opening_cost'],
            'customers' => ['name', 'phone', 'address', 'tax_number', 'price_tier', 'opening_balance', 'notes'],
            'suppliers' => ['name', 'company_name', 'phone', 'address', 'opening_balance', 'notes'],
            'categories' => ['name', 'icon'],
        ][$type];

        return array_map(static fn (string $c): string => (string) __("import.columns.{$type}.{$c}", [], $locale), $columns);
    }

    /**
     * @param  list<string>  $row
     * @return list<string>
     */
    private function itemRow(array $row): array
    {
        return array_pad($row, 11, '');
    }

    private function preview(string $type, UploadedFile $file, ?array $headers = null): TestResponse
    {
        return $this->post("/api/v1/imports/{$type}/preview", ['file' => $file], $headers ?? $this->tenantHeaders($this->tenant));
    }

    private function commit(string $type, string $token, bool $skip = false, ?array $headers = null): TestResponse
    {
        return $this->postJson("/api/v1/imports/{$type}/commit", ['token' => $token, 'skip_invalid' => $skip], $headers ?? $this->tenantHeaders($this->tenant));
    }

    private function previewToken(string $type, UploadedFile $file, ?array $headers = null): string
    {
        return (string) $this->preview($type, $file, $headers)->assertOk()->json('data.token');
    }

    /**
     * @return list<list<string>>
     */
    private function itemsSheet(): array
    {
        return [
            $this->headers('items'),
            $this->itemRow(['A-1', 'بن برازيلي', 'بن', 'كجم', '250', '320', '300', '5', 'نعم', '20', '240']),
            $this->itemRow(['A-2', 'شاي', 'مشروبات', 'كجم', '٨٠٫٥', '1,200.5', '', '', '', '', '']),
        ];
    }

    // ---------------------------------------------------------------- template

    /**
     * @return array<string, array{0: string}>
     */
    public static function types(): array
    {
        return ['items' => ['items'], 'customers' => ['customers'], 'suppliers' => ['suppliers'], 'categories' => ['categories']];
    }

    #[DataProvider('types')]
    public function test_template_downloads_an_xlsx_with_header_and_example_row(string $type): void
    {
        $response = $this->get("/api/v1/imports/{$type}/template", $this->tenantHeaders($this->tenant));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', (string) $response->headers->get('Content-Type'));

        $reader = new Reader;
        $reader->open($response->baseResponse->getFile()->getPathname());
        $lines = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $lines[] = $row->toArray();
            }
            break;
        }
        $reader->close();

        $this->assertCount(2, $lines);
        $this->assertSame($this->headers($type, app()->getLocale()), array_map('strval', $lines[0]));
    }

    public function test_unknown_type_is_404_and_guests_get_401(): void
    {
        $this->get('/api/v1/imports/widgets/template', $this->tenantHeaders($this->tenant))->assertNotFound();
        $this->get('/api/v1/imports/items/template', $this->tenantGuestHeaders($this->tenant))->assertUnauthorized();
        $this->post('/api/v1/imports/items/preview', [], $this->tenantGuestHeaders($this->tenant))->assertUnauthorized();
        $this->postJson('/api/v1/imports/items/commit', [], $this->tenantGuestHeaders($this->tenant))->assertUnauthorized();
    }

    public function test_permission_is_required_per_type(): void
    {
        $nobody = $this->createTenantUser($this->tenant);
        $customersOnly = $this->createTenantUser($this->tenant, null, ['customers.manage']);

        foreach (['items', 'customers', 'suppliers', 'categories'] as $type) {
            $this->get("/api/v1/imports/{$type}/template", $this->tenantHeaders($this->tenant, $nobody))->assertForbidden();
        }

        $this->get('/api/v1/imports/customers/template', $this->tenantHeaders($this->tenant, $customersOnly))->assertOk();
        $this->get('/api/v1/imports/items/template', $this->tenantHeaders($this->tenant, $customersOnly))->assertForbidden();
        $this->preview('items', $this->xlsx($this->itemsSheet()), $this->tenantHeaders($this->tenant, $customersOnly))->assertForbidden();
        $this->commit('suppliers', 'x', false, $this->tenantHeaders($this->tenant, $customersOnly))->assertForbidden();
    }

    // ---------------------------------------------------------------- preview

    public function test_preview_reports_valid_invalid_and_duplicate_rows_without_writing(): void
    {
        $this->inTenant($this->tenant, fn () => Item::query()->create([
            'code' => 'EXIST-1', 'name' => 'Existing', 'unit' => 'كجم', 'cost_price' => '1.000', 'selling_price' => '2.000',
            'weighted_avg_cost' => '1.000', 'min_selling_price' => '0.000', 'min_stock_level' => '0.000', 'current_stock' => '0.000', 'is_active' => true,
        ]));

        $sheet = [
            $this->headers('items'),
            $this->itemRow(['A-1', 'بن', 'بن', 'كجم', '250', '320']),                  // valid
            $this->itemRow(['A-2', '', 'بن', 'كجم', '250', '320']),                    // no name
            $this->itemRow(['A-3', 'صنف', 'بن', 'وحدة-غريبة', '250', '320']),          // bad unit
            $this->itemRow(['A-4', 'صنف', 'بن', 'كجم', '-5', '320']),                  // negative cost
            $this->itemRow(['EXIST-1', 'مكرر', 'بن', 'كجم', '1', '2']),                // duplicate in DB
            $this->itemRow(['A-1', 'مكرر في الملف', 'بن', 'كجم', '1', '2']),           // duplicate in file
            $this->itemRow(['A-5', 'صنف', 'بن', 'كجم', 'abc', '320']),                 // not a number
        ];

        $response = $this->preview('items', $this->xlsx($sheet))->assertOk();

        $response->assertJsonPath('data.type', 'items')
            ->assertJsonPath('data.summary', ['total' => 7, 'valid' => 1, 'invalid' => 4, 'duplicates' => 2])
            ->assertJsonPath('data.rows.0.status', 'valid')
            ->assertJsonPath('data.rows.0.row_number', 2)
            ->assertJsonPath('data.rows.0.data.selling_price', '320.000')
            ->assertJsonPath('data.rows.1.status', 'invalid')
            ->assertJsonPath('data.rows.4.status', 'duplicate')
            ->assertJsonPath('data.rows.5.status', 'duplicate');
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotEmpty($response->json('data.rows.1.errors.0'));

        $this->assertSame(1, $this->inTenant($this->tenant, fn (): int => Item::query()->count()));
        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => Category::query()->count()));
    }

    public function test_preview_reads_a_utf8_csv_with_english_headers_and_arabic_digits(): void
    {
        $sheet = [
            $this->headers('items', 'en'),
            $this->itemRow(['C-1', 'Beans', 'Coffee', 'كجم', '١٢٫٥', '١,٥٠٠', '', '', 'yes', '٣', '']),
        ];

        foreach ([true, false] as $bom) {
            $response = $this->preview('items', $this->csv($sheet, $bom))->assertOk();
            $response->assertJsonPath('data.summary.valid', 1)
                ->assertJsonPath('data.rows.0.data.cost_price', '12.500')
                ->assertJsonPath('data.rows.0.data.selling_price', '1500.000')
                ->assertJsonPath('data.rows.0.data.opening_quantity', '3.000')
                ->assertJsonPath('data.rows.0.data.is_weighted', true);
        }
    }

    public function test_preview_maps_by_column_order_when_headers_are_unknown(): void
    {
        $sheet = [
            ['a', 'b', 'c', 'd', 'e', 'f'],
            ['K-1', 'صنف', 'بن', 'كجم', '10', '20'],
        ];

        $this->preview('items', $this->csv($sheet))->assertOk()
            ->assertJsonPath('data.summary.valid', 1)
            ->assertJsonPath('data.rows.0.data.code', 'K-1');
    }

    public function test_preview_rejects_wrong_type_oversize_and_missing_required_columns(): void
    {
        $this->preview('items', UploadedFile::fake()->create('items.pdf', 10, 'application/pdf'))
            ->assertStatus(422)->assertJsonValidationErrors('file');

        $this->preview('items', UploadedFile::fake()->create('items.csv', 6000, 'text/csv'))
            ->assertStatus(422)->assertJsonValidationErrors('file');

        $this->post('/api/v1/imports/items/preview', [], $this->tenantHeaders($this->tenant))
            ->assertStatus(422)->assertJsonValidationErrors('file');

        $this->preview('items', $this->csv([['الكود', 'التصنيف'], ['A', 'B']]))
            ->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_preview_rejects_more_than_5000_rows(): void
    {
        $rows = [$this->headers('categories')];
        for ($i = 1; $i <= 5001; $i++) {
            $rows[] = ['Cat '.$i, ''];
        }

        $this->preview('categories', $this->csv($rows))->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_preview_returns_all_invalid_rows_even_beyond_the_cap(): void
    {
        $rows = [$this->headers('categories')];
        for ($i = 1; $i <= 600; $i++) {
            $rows[] = ['Cat '.$i, ''];
        }
        $rows[] = ['', '🍰'];
        $rows[] = ['Cat last', ''];

        $response = $this->preview('categories', $this->csv($rows))->assertOk();

        $response->assertJsonPath('data.summary.total', 602)
            ->assertJsonPath('data.summary.invalid', 1)
            ->assertJsonPath('data.rows_truncated', true);
        $this->assertCount(501, $response->json('data.rows'));
        $this->assertSame('invalid', $response->json('data.rows.500.status'));
        $this->assertSame(602, $response->json('data.rows.500.row_number'));
    }

    // ---------------------------------------------------------------- commit: items

    public function test_commit_items_posts_opening_stock_through_the_stock_engine(): void
    {
        $token = $this->previewToken('items', $this->xlsx($this->itemsSheet()));

        $this->commit('items', $token)->assertOk()
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.skipped', 0);

        $storeId = (int) $this->tenantStore($this->tenant)->id;

        $this->inTenant($this->tenant, function () use ($storeId): void {
            $item = Item::query()->where('code', 'A-1')->firstOrFail();
            $this->assertSame('20.000', (string) $item->current_stock);
            $this->assertSame('240.000', (string) $item->weighted_avg_cost);
            $this->assertSame('250.000', (string) $item->cost_price);
            $this->assertSame('300.000', (string) $item->min_selling_price);
            $this->assertTrue((bool) $item->is_weighted);
            $this->assertSame('بن', Category::query()->findOrFail($item->category_id)->name);

            $stock = StoreStock::query()->where('store_id', $storeId)->where('item_id', $item->id)->firstOrFail();
            $this->assertSame('20.000', (string) $stock->quantity);

            $movement = StockMovement::query()->where('item_id', $item->id)->sole();
            $this->assertSame('opening_balance_in', $movement->movement_type);
            $this->assertSame($storeId, (int) $movement->store_id);
            $this->assertSame('20.000', (string) $movement->quantity);
            $this->assertSame('0.000', (string) $movement->stock_before);
            $this->assertSame('20.000', (string) $movement->stock_after);
            $this->assertSame('240.000', (string) $movement->unit_cost);

            // Second row: no opening stock, no movement, no price floor invented from the cost.
            $other = Item::query()->where('code', 'A-2')->firstOrFail();
            $this->assertSame('0.000', (string) $other->current_stock);
            $this->assertSame('0.000', (string) $other->min_selling_price);
            $this->assertSame('1200.500', (string) $other->selling_price);
            $this->assertSame('80.500', (string) $other->cost_price);
            $this->assertSame(0, StockMovement::query()->where('item_id', $other->id)->count());
            $this->assertSame(2, Category::query()->count());
        });

        // The token is single-use.
        $this->commit('items', $token)->assertNotFound();
    }

    public function test_commit_is_refused_while_the_same_token_is_being_committed(): void
    {
        $token = $this->previewToken('categories', $this->xlsx([$this->headers('categories'), ['س', '']]));

        $lock = Cache::lock(TenantCache::keyFor((string) $this->tenant->getTenantKey(), 'import-commit:'.$token), 60);
        $this->assertTrue($lock->get());

        $this->commit('categories', $token)->assertStatus(409)
            ->assertJsonPath('message', __('import.errors.already_running'));
        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => Category::query()->count()));

        $lock->release();
        $this->commit('categories', $token)->assertOk()->assertJsonPath('data.created', 1);
        $this->commit('categories', $token)->assertNotFound();
        $this->assertSame(1, $this->inTenant($this->tenant, fn (): int => Category::query()->count()));
    }

    public function test_generated_item_codes_continue_after_existing_ones_and_avoid_codes_in_the_file(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $item = Item::query()->create(['name' => 'قديم', 'code' => 'ITM-0007', 'unit' => 'كجم', 'cost_price' => '1.000', 'selling_price' => '2.000', 'weighted_avg_cost' => '1.000', 'min_stock_level' => '0.000', 'current_stock' => '0.000', 'is_active' => true]);
            $item->delete();
        });

        $sheet = [
            $this->headers('items'),
            $this->itemRow(['', 'بدون كود 1', '', 'كجم', '', '10']),
            $this->itemRow(['ITM-0009', 'بكود صريح', '', 'كجم', '', '10']),
            $this->itemRow(['', 'بدون كود 2', '', 'كجم', '', '10']),
        ];
        $this->commit('items', $this->previewToken('items', $this->xlsx($sheet)))->assertOk()->assertJsonPath('data.created', 3);

        $codes = $this->inTenant($this->tenant, fn (): array => Item::query()->orderBy('id')->pluck('name', 'code')->all());
        $this->assertSame('بدون كود 1', $codes['ITM-0008']);
        $this->assertSame('بكود صريح', $codes['ITM-0009']);
        $this->assertSame('بدون كود 2', $codes['ITM-0010']);
    }

    public function test_a_unique_violation_during_commit_is_a_translated_422_and_rolls_back(): void
    {
        $token = $this->previewToken('items', $this->xlsx([$this->headers('items'), $this->itemRow(['RACE-1', 'سباق', '', 'كجم', '', '10'])]));

        // Simulate the race: a concurrent request takes the code right before our insert.
        $taken = false;
        Item::creating(function (Item $item) use (&$taken): void {
            if (! $taken && $item->code === 'RACE-1') {
                $taken = true;
                DB::table('items')->insert(['name' => 'سبق', 'code' => 'RACE-1', 'unit' => 'كجم', 'cost_price' => '1.000', 'selling_price' => '2.000', 'weighted_avg_cost' => '1.000', 'min_stock_level' => '0.000', 'current_stock' => '0.000', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        $this->commit('items', $token)->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
        // The racing insert shares the import transaction, so everything is rolled back together.
        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => Item::query()->count()));
    }

    public function test_commit_generates_codes_and_skips_duplicates_without_updating(): void
    {
        $this->inTenant($this->tenant, fn () => Item::query()->create([
            'code' => 'KEEP-1', 'name' => 'Old name', 'unit' => 'كجم', 'cost_price' => '1.000', 'selling_price' => '2.000',
            'weighted_avg_cost' => '1.000', 'min_selling_price' => '0.000', 'min_stock_level' => '0.000', 'current_stock' => '0.000', 'is_active' => true,
        ]));

        $sheet = [
            $this->headers('items'),
            $this->itemRow(['KEEP-1', 'New name', '', 'كجم', '9', '9']),
            $this->itemRow(['', 'No code item', '', 'كجم', '5', '8']),
        ];

        $this->commit('items', $this->previewToken('items', $this->xlsx($sheet)))->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.duplicates', 1);

        $this->inTenant($this->tenant, function (): void {
            $this->assertSame('Old name', Item::query()->where('code', 'KEEP-1')->firstOrFail()->name);
            $this->assertSame(1, Item::query()->where('name', 'No code item')->count());
            $this->assertNotEmpty(Item::query()->where('name', 'No code item')->value('code'));
        });
    }

    public function test_commit_with_invalid_rows_needs_skip_invalid(): void
    {
        $sheet = [
            $this->headers('items'),
            $this->itemRow(['B-1', 'سليم', '', 'كجم', '5', '8']),
            $this->itemRow(['B-2', '', '', 'كجم', '5', '8']),
        ];
        $token = $this->previewToken('items', $this->xlsx($sheet));

        $this->commit('items', $token, false)->assertStatus(422)->assertJsonValidationErrors('skip_invalid');
        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => Item::query()->count()));

        $this->commit('items', $token, true)->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.errors.0.row_number', 3);
        $this->assertSame(1, $this->inTenant($this->tenant, fn (): int => Item::query()->count()));
    }

    public function test_commit_is_all_or_nothing(): void
    {
        $failing = new class(app(TenantClock::class), app(DocumentNumberService::class)) extends StockService
        {
            private int $calls = 0;

            public function addStock(Item $item, string $quantity, string $unitCost, Model $source, string $documentNumber, string $movementType = 'purchase_in', ?string $notes = null, ?int $storeId = null, ?int $actorId = null): StockMovement
            {
                if (++$this->calls === 2) {
                    throw new \RuntimeException('forced failure');
                }

                return parent::addStock($item, $quantity, $unitCost, $source, $documentNumber, $movementType, $notes, $storeId, $actorId);
            }
        };

        $sheet = [
            $this->headers('items'),
            $this->itemRow(['F-1', 'أول', 'تصنيف جديد', 'كجم', '5', '8', '', '', '', '10']),
            $this->itemRow(['F-2', 'تاني', 'تصنيف جديد 2', 'كجم', '5', '8', '', '', '', '10']),
        ];
        $token = $this->previewToken('items', $this->xlsx($sheet));

        $this->instance(StockService::class, $failing);
        $this->commit('items', $token)->assertStatus(500);

        $this->inTenant($this->tenant, function (): void {
            $this->assertSame(0, Item::query()->count());
            $this->assertSame(0, StockMovement::query()->count());
            $this->assertSame(0, Category::query()->count());
            $this->assertSame('0.000', (string) (StoreStock::query()->sum('quantity') ?: '0.000'));
        });
    }

    public function test_commit_is_refused_when_the_plan_item_limit_would_be_exceeded(): void
    {
        $slug = 'imp-'.Str::lower(Str::random(8));
        $plan = Plan::query()->create([
            'name' => $slug, 'slug' => $slug, 'price_monthly' => '449.000', 'price_yearly' => '4490.000',
            'features' => array_fill_keys(FeatureCatalog::keys(), true),
            'max_users' => null, 'max_stores' => null, 'max_warehouses' => null, 'max_vans' => null, 'max_items' => 1,
            'is_active' => true, 'sort_order' => 1,
        ]);
        $tenant = $this->createTenant(['plan_id' => $plan->id]);

        $headers = $this->tenantHeaders($tenant);
        $token = (string) $this->preview('items', $this->xlsx($this->itemsSheet()), $headers)->assertOk()->json('data.token');

        $this->commit('items', $token, false, $headers)->assertStatus(403)
            ->assertJsonPath('error_code', 'subscription.limit_reached')
            ->assertJsonPath('details.limit', 'items');

        $this->assertSame(0, $this->inTenant($tenant, fn (): int => Item::query()->count()));
        $this->assertSame(0, $this->inTenant($tenant, fn (): int => Category::query()->count()));
    }

    // ---------------------------------------------------------------- commit: parties and categories

    public function test_commit_customers_posts_an_opening_balance_statement_entry(): void
    {
        $sheet = [
            $this->headers('customers'),
            ['أحمد', '01000000111', 'القاهرة', '', 'جملة', '١٥٠٠', ''],
            ['سارة', '1000000222', '', '', '', '-200', ''],
            ['بدون رصيد', '', '', '', '', '', ''],
        ];

        $this->commit('customers', $this->previewToken('customers', $this->xlsx($sheet)))->assertOk()
            ->assertJsonPath('data.created', 3);

        $this->inTenant($this->tenant, function (): void {
            $ahmed = Customer::query()->where('name', 'أحمد')->firstOrFail();
            $this->assertSame('wholesale', $ahmed->price_tier);
            $this->assertSame('1500.000', (string) $ahmed->current_balance);

            $ledger = app(CustomerBalanceService::class)->getCustomerLedger($ahmed);
            $this->assertCount(1, $ledger['entries']);
            $this->assertSame(__('import.opening_balance_entry'), $ledger['entries'][0]['type']);
            $this->assertSame('1500.000', (string) $ledger['entries'][0]['debit']);
            $this->assertSame('1500.000', (string) $ledger['entries'][0]['balance_after']);

            // The balance survives a recalculation (it is a real statement entry, not a raw column).
            $this->assertSame('1500.000', app(CustomerBalanceService::class)->updateBalance((int) $ahmed->id));

            $sara = Customer::query()->where('name', 'سارة')->firstOrFail();
            $this->assertSame('01000000222', $sara->phone);
            $this->assertSame('-200.000', (string) $sara->current_balance);
            $this->assertSame('200.000', (string) app(CustomerBalanceService::class)->getCustomerLedger($sara)['entries'][0]['credit']);

            $this->assertSame([], app(CustomerBalanceService::class)->getCustomerLedger(Customer::query()->where('name', 'بدون رصيد')->firstOrFail())['entries']);
        });
    }

    public function test_commit_suppliers_posts_an_opening_balance_and_skips_duplicate_phones(): void
    {
        $this->inTenant($this->tenant, fn () => Supplier::query()->create(['name' => 'قديم', 'phone' => '01000000333', 'current_balance' => '0.000', 'is_active' => true]));

        $sheet = [
            $this->headers('suppliers'),
            ['محمود', 'شركة النيل', '01000000444', '', '5000', ''],
            ['مكرر', '', '01000000333', '', '10', ''],
            ['عليه لنا', '', '01000000555', '', '-75.5', ''],
        ];

        $this->commit('suppliers', $this->previewToken('suppliers', $this->xlsx($sheet)))->assertOk()
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.duplicates', 1);

        $this->inTenant($this->tenant, function (): void {
            $mahmoud = Supplier::query()->where('name', 'محمود')->firstOrFail();
            $this->assertSame('5000.000', (string) $mahmoud->current_balance);
            $ledger = app(SupplierBalanceService::class)->getSupplierLedger($mahmoud)['ledger'];
            $this->assertCount(1, $ledger);
            $this->assertEquals(5000.0, $ledger[0]['credit']);
            $this->assertSame('5000.000', app(SupplierBalanceService::class)->updateBalance((int) $mahmoud->id));

            $this->assertSame('-75.500', (string) Supplier::query()->where('name', 'عليه لنا')->firstOrFail()->current_balance);
            $this->assertSame(0, Supplier::query()->where('name', 'مكرر')->count());
        });
    }

    public function test_commit_categories_skips_existing_names(): void
    {
        $this->inTenant($this->tenant, fn () => Category::query()->create(['name' => 'مشروبات']));

        $sheet = [$this->headers('categories'), ['مشروبات', ''], ['حلويات', '🍰'], ['حلويات', '']];

        $this->commit('categories', $this->previewToken('categories', $this->xlsx($sheet)))->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.skipped', 2);

        $this->assertSame(2, $this->inTenant($this->tenant, fn (): int => Category::query()->count()));
    }

    // ---------------------------------------------------------------- tokens and store scoping

    public function test_a_token_of_another_user_or_type_is_not_found(): void
    {
        $token = $this->previewToken('categories', $this->xlsx([$this->headers('categories'), ['س', '']]));

        $other = $this->createTenantUser($this->tenant, null, ['items.create']);
        $this->commit('categories', $token, false, $this->tenantHeaders($this->tenant, $other))->assertNotFound();
        $this->commit('items', $token)->assertNotFound();
        $this->commit('categories', 'does-not-exist')->assertNotFound();

        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => Category::query()->count()));
        $this->commit('categories', $token)->assertOk();
    }

    public function test_a_store_the_user_cannot_access_is_forbidden(): void
    {
        $user = $this->createTenantUser($this->tenant, null, ['items.create']);
        $foreign = $this->inTenant($this->tenant, fn (): Store => Store::query()->create(['name' => 'فرع تاني', 'code' => 'BR-2', 'type' => 'retail_shop', 'is_active' => true]));

        $headers = $this->tenantHeaders($this->tenant, $user, $foreign);

        $this->preview('items', $this->xlsx($this->itemsSheet()), $headers)->assertForbidden();
        $this->commit('items', 'whatever', false, $headers)->assertForbidden();
        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => Item::query()->count()));
    }

    public function test_tenant_isolation_a_token_is_not_valid_in_another_tenant(): void
    {
        $token = $this->previewToken('categories', $this->xlsx([$this->headers('categories'), ['س', '']]));

        $otherTenant = $this->createTenant();
        $this->commit('categories', $token, false, $this->tenantHeaders($otherTenant))->assertNotFound();
        $this->assertSame(0, $this->inTenant($otherTenant, fn (): int => Category::query()->count()));
    }
}
