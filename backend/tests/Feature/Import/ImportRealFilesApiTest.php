<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\Import\ArabicTextNormalizer;
use App\Services\Import\NumberNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TenantTestCase;

/**
 * IMP-01: the import against files shaped like the ones shops really send (tests/Fixtures/imports):
 * re-uploads that must add nothing, titles above the header, everyday Arabic spelling variants,
 * UTF-16 / Windows-1256 encodings, Excel-mangled barcodes, and the downloadable error report.
 */
class ImportRealFilesApiTest extends TenantTestCase
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
    }

    private function fixture(string $name): UploadedFile
    {
        // A copy: the upload is moved to the tenant disk, the fixture must stay in the repo.
        $path = tempnam(sys_get_temp_dir(), 'fix').'-'.$name;
        copy(base_path('tests/Fixtures/imports/'.$name), $path);

        return new UploadedFile($path, $name, null, null, true);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function xlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imt').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return new UploadedFile($path, 'import.xlsx', null, null, true);
    }

    private function preview(string $type, UploadedFile $file, ?array $headers = null): TestResponse
    {
        return $this->post("/api/v1/imports/{$type}/preview", ['file' => $file], $headers ?? $this->tenantHeaders($this->tenant));
    }

    private function commit(string $type, string $token, bool $confirmCategories = true, bool $skipInvalid = false): TestResponse
    {
        return $this->postJson("/api/v1/imports/{$type}/commit", [
            'token' => $token,
            'skip_invalid' => $skipInvalid,
            'confirm_new_categories' => $confirmCategories,
        ], $this->tenantHeaders($this->tenant));
    }

    // ---------------------------------------------------------------- normalization units

    public function test_arabic_normalizer_collapses_everyday_spelling_variants(): void
    {
        $this->assertSame(ArabicTextNormalizer::key('قطعة'), ArabicTextNormalizer::key('قطعه'));
        $this->assertSame(ArabicTextNormalizer::key('إسبريسو'), ArabicTextNormalizer::key('اسبريسو'));
        $this->assertSame(ArabicTextNormalizer::key('مقهى'), ArabicTextNormalizer::key('مقهي'));
        $this->assertSame(ArabicTextNormalizer::key('بن  برازيلي'), ArabicTextNormalizer::key(' بن برازيلي '));
        $this->assertSame(ArabicTextNormalizer::key('قهـــوة'), ArabicTextNormalizer::key('قهوة'));
        $this->assertSame(ArabicTextNormalizer::key('Coffee'), ArabicTextNormalizer::key('coffee'));
        $this->assertNotSame(ArabicTextNormalizer::key('بن'), ArabicTextNormalizer::key('لبن'));

        $this->assertSame('كرتونة', ArabicTextNormalizer::closest('كرتونا', ['قطعة', 'علبة', 'كرتونة']));
        $this->assertNull(ArabicTextNormalizer::closest('برميل', ['قطعة', 'علبة', 'كرتونة']));
    }

    public function test_egyptian_mobiles_with_the_country_code_become_the_local_form(): void
    {
        foreach (['+20 100 123 4567', '00201001234567', '201001234567', '1001234567', '٠١٠٠١٢٣٤٥٦٧'] as $typed) {
            $this->assertSame('01001234567', NumberNormalizer::phone($typed), $typed);
        }
        $this->assertSame('+97150123456', NumberNormalizer::phone('+971 50 123 456'));
    }

    // ---------------------------------------------------------------- idempotent re-upload

    public function test_re_uploading_the_same_items_file_without_codes_adds_nothing_the_second_time(): void
    {
        $first = $this->preview('items', $this->fixture('items-without-codes.csv'))->assertOk()
            ->assertJsonPath('data.summary', ['total' => 4, 'valid' => 4, 'invalid' => 0, 'duplicates' => 0]);
        $this->commit('items', (string) $first->json('data.token'))->assertOk()->assertJsonPath('data.created', 4);

        $this->inTenant($this->tenant, function (): void {
            $this->assertSame(4, Item::query()->count());
            // One opening_balance_in movement per item with opening stock (3 of the 4 rows).
            $this->assertSame(3, StockMovement::query()->where('movement_type', 'opening_balance_in')->count());
            $this->assertSame(3, StockMovement::query()->distinct()->count('item_id'));
            // The opening cost becomes the weighted average cost of the new item.
            $this->assertSame('240.000', (string) Item::query()->where('name', 'بن برازيلي محوج')->value('weighted_avg_cost'));
        });

        $second = $this->preview('items', $this->fixture('items-without-codes.csv'))->assertOk()
            ->assertJsonPath('data.summary', ['total' => 4, 'valid' => 0, 'invalid' => 0, 'duplicates' => 4])
            ->assertJsonPath('data.rows.0.errors.0', __('import.errors.duplicate'));
        $this->commit('items', (string) $second->json('data.token'))->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.duplicates', 4);

        $this->inTenant($this->tenant, function (): void {
            $this->assertSame(4, Item::query()->count());
            $this->assertSame(3, StockMovement::query()->count());
        });
    }

    public function test_a_code_less_row_repeated_inside_the_file_is_reported_against_its_first_row(): void
    {
        $file = $this->xlsx([
            ['اسم الصنف', 'الوحدة', 'سعر البيع'],
            ['شاي', 'علبة', '20'],
            ['شاى', 'علبه', '22'],
            ['شاي', 'كجم', '90'], // another unit: another item
        ]);

        $this->preview('items', $file)->assertOk()
            ->assertJsonPath('data.summary', ['total' => 3, 'valid' => 2, 'invalid' => 0, 'duplicates' => 1])
            ->assertJsonPath('data.rows.1.status', 'duplicate')
            ->assertJsonPath('data.rows.1.errors.0', __('import.errors.duplicate_in_file', ['row' => 2]));
    }

    public function test_re_uploading_customers_without_phones_matches_by_normalized_name(): void
    {
        $this->inTenant($this->tenant, fn () => Customer::query()->create([
            'name' => 'كافيه بالتليفون', 'phone' => '01001234567', 'price_tier' => 'retail', 'current_balance' => '0.000', 'is_active' => true,
        ]));

        // «+20 100 123 4567» is the existing 01001234567: a duplicate, not a new customer.
        $first = $this->preview('customers', $this->fixture('customers-without-phones.csv'))->assertOk()
            ->assertJsonPath('data.summary', ['total' => 3, 'valid' => 2, 'invalid' => 0, 'duplicates' => 1]);
        $this->commit('customers', (string) $first->json('data.token'))->assertOk()->assertJsonPath('data.created', 2);

        $again = $this->xlsx([['اسم العميل', 'الموبايل'], ['مقهي النيل', ''], ['احمد على', ''], ['عميل جديد', '']]);
        $second = $this->preview('customers', $again)->assertOk()
            ->assertJsonPath('data.summary', ['total' => 3, 'valid' => 1, 'invalid' => 0, 'duplicates' => 2]);
        $this->commit('customers', (string) $second->json('data.token'))->assertOk()->assertJsonPath('data.created', 1);

        $this->assertSame(4, $this->inTenant($this->tenant, fn (): int => Customer::query()->count()));
    }

    public function test_existing_parties_saved_with_any_phone_format_are_duplicates_on_re_import(): void
    {
        // Parties saved before phones were normalized, each in another format of an Egyptian mobile.
        $this->inTenant($this->tenant, function (): void {
            foreach (['+201001234567', '201101234567', '00201201234567', '010 1555 4444', '٠١٢٢٣٣٣٤٤٤٤'] as $i => $phone) {
                Customer::query()->create(['name' => 'قديم '.$i, 'phone' => $phone, 'price_tier' => 'retail', 'current_balance' => '0.000', 'is_active' => true]);
                Supplier::query()->create(['name' => 'مورد قديم '.$i, 'phone' => $phone, 'current_balance' => '0.000']);
            }
        });

        $rows = [
            ['01001234567', 'نفس +2010'],
            ['+20 110 123 4567', 'نفس 2011'],
            ['0020 120 123 4567', 'نفس 002012'],
            ['01015554444', 'نفس بمسافات'],
            ['01223334444', 'نفس بأرقام عربية'],
            ['01099998888', 'جديد'],
        ];

        $customers = $this->xlsx([['اسم العميل', 'الموبايل', 'الرصيد الافتتاحي'], ...array_map(static fn (array $r): array => [$r[1], $r[0], '100'], $rows)]);
        $preview = $this->preview('customers', $customers)->assertOk()
            ->assertJsonPath('data.summary', ['total' => 6, 'valid' => 1, 'invalid' => 0, 'duplicates' => 5]);
        $this->commit('customers', (string) $preview->json('data.token'))->assertOk()->assertJsonPath('data.created', 1);

        $suppliers = $this->xlsx([['اسم المورد', 'الموبايل', 'الرصيد الافتتاحي'], ...array_map(static fn (array $r): array => [$r[1], $r[0], '100'], $rows)]);
        $preview = $this->preview('suppliers', $suppliers)->assertOk()
            ->assertJsonPath('data.summary', ['total' => 6, 'valid' => 1, 'invalid' => 0, 'duplicates' => 5]);
        $this->commit('suppliers', (string) $preview->json('data.token'))->assertOk()->assertJsonPath('data.created', 1);

        $this->inTenant($this->tenant, function (): void {
            $this->assertSame(6, Customer::query()->count());
            $this->assertSame(6, Supplier::query()->count());
            // No second opening balance landed on any existing party.
            $this->assertSame(5, Customer::query()->where('current_balance', '0.000')->count());
            $this->assertSame(5, Supplier::query()->where('current_balance', '0.000')->count());
        });
    }

    // ---------------------------------------------------------------- header detection

    public function test_titles_above_the_header_are_skipped_and_unknown_columns_are_reported(): void
    {
        $response = $this->preview('items', $this->fixture('items-title-above-header.csv'))->assertOk()
            ->assertJsonPath('data.header_row', 4)
            ->assertJsonPath('data.ignored_columns', ['ملاحظات المحل'])
            ->assertJsonPath('data.summary', ['total' => 2, 'valid' => 2, 'invalid' => 0, 'duplicates' => 0])
            ->assertJsonPath('data.rows.0.row_number', 5)
            ->assertJsonPath('data.rows.0.data.name', 'قهوة تركي')
            ->assertJsonPath('data.rows.0.data.unit', 'كجم')
            ->assertJsonPath('data.rows.0.data.selling_price', '400.000')
            ->assertJsonPath('data.rows.0.data.code', 'T-1');

        // The same layout in an .xlsx.
        $this->preview('items', $this->xlsx([
            ['محل البركة', ''],
            ['اسم الصنف', 'الوحدة', 'سعر البيع'],
            ['قهوة', 'كجم', '400'],
        ]))->assertOk()->assertJsonPath('data.header_row', 2)->assertJsonPath('data.summary.valid', 1);

        $this->commit('items', (string) $response->json('data.token'))->assertOk()->assertJsonPath('data.created', 2);
    }

    // ---------------------------------------------------------------- units and categories

    public function test_units_match_after_normalization_and_an_unknown_unit_suggests_the_closest(): void
    {
        $response = $this->preview('items', $this->xlsx([
            ['اسم الصنف', 'الوحدة', 'سعر البيع'],
            ['بسكويت', 'قطعه', '5'],
            ['كاكاو', 'كرتونا', '50'],
            ['زيت', 'برميل', '900'],
        ]))->assertOk();

        $response->assertJsonPath('data.rows.0.status', 'valid')
            // Stored in the tenant's own spelling.
            ->assertJsonPath('data.rows.0.data.unit', 'قطعة')
            ->assertJsonPath('data.rows.1.status', 'invalid')
            ->assertJsonPath('data.rows.1.errors.0', __('inventory.unit_not_in_tenant_list', ['unit' => 'كرتونا']).' '.__('import.errors.did_you_mean', ['value' => 'كرتونة']))
            ->assertJsonPath('data.rows.2.errors.0', __('inventory.unit_not_in_tenant_list', ['unit' => 'برميل']));
    }

    public function test_new_categories_are_listed_in_the_preview_and_created_only_after_confirmation(): void
    {
        $this->inTenant($this->tenant, fn () => Category::query()->create(['name' => 'مشروبات ساخنة', 'icon' => 'coffee', 'sort_order' => 0, 'is_active' => true]));

        $response = $this->preview('items', $this->fixture('items-without-codes.csv'))->assertOk()
            // «مشروبات ساخنه» is the existing «مشروبات ساخنة»; «بن» and «ألبان» are new.
            ->assertJsonPath('data.new_categories', ['بن', 'ألبان'])
            ->assertJsonPath('data.rows.0.data.category', 'مشروبات ساخنة');
        $token = (string) $response->json('data.token');

        $this->commit('items', $token, confirmCategories: false)->assertStatus(422)
            ->assertJsonValidationErrors('confirm_new_categories');
        $this->inTenant($this->tenant, function (): void {
            $this->assertSame(1, Category::query()->count());
            $this->assertSame(0, Item::query()->count());
        });

        $this->commit('items', $token)->assertOk()->assertJsonPath('data.created', 4);
        $this->inTenant($this->tenant, function (): void {
            $this->assertSame(3, Category::query()->count());
            $hot = (int) Category::query()->where('name', 'مشروبات ساخنة')->value('id');
            $this->assertSame(2, Item::query()->where('category_id', $hot)->count());
        });
    }

    public function test_a_file_without_new_categories_needs_no_confirmation(): void
    {
        $response = $this->preview('items', $this->xlsx([['اسم الصنف', 'الوحدة', 'سعر البيع'], ['ملح', 'كجم', '10']]))->assertOk()
            ->assertJsonPath('data.new_categories', []);

        $this->commit('items', (string) $response->json('data.token'), confirmCategories: false)->assertOk()->assertJsonPath('data.created', 1);
    }

    // ---------------------------------------------------------------- encodings and cell validation

    public function test_utf16_and_windows_1256_files_are_read(): void
    {
        $this->preview('items', $this->fixture('items-utf16.csv'))->assertOk()
            ->assertJsonPath('data.summary.valid', 2)
            ->assertJsonPath('data.rows.0.data.name', 'سكر')
            ->assertJsonPath('data.rows.0.data.selling_price', '35.000');

        $this->preview('items', $this->fixture('items-windows-1256.csv'))->assertOk()
            ->assertJsonPath('data.rows.0.status', 'valid')
            ->assertJsonPath('data.rows.0.data.name', 'زيت عباد')
            ->assertJsonPath('data.rows.0.data.selling_price', '1250.500')
            // Excel turned the barcode into 1.23457E+12: refused, the digits are lost.
            ->assertJsonPath('data.rows.1.status', 'invalid')
            ->assertJsonPath('data.rows.1.errors.0', __('import.errors.scientific_code', ['field' => __('import.columns.items.code'), 'value' => '1.23457E+12']));
    }

    public function test_only_excels_scientific_notation_is_refused_as_a_code(): void
    {
        $codes = ['12E05', '3E4', '12E+05', '1.23457E+12', '1E+12', '1,2E+11'];
        $sheet = [['الكود', 'اسم الصنف', 'الوحدة', 'سعر البيع']];
        foreach ($codes as $i => $code) {
            $sheet[] = [$code, 'صنف '.$i, 'كجم', '10'];
        }

        $response = $this->preview('items', $this->xlsx($sheet))->assertOk();

        // Real codes that merely contain an E stay as typed.
        foreach (['12E05', '3E4', '12E+05'] as $i => $code) {
            $response->assertJsonPath("data.rows.{$i}.status", 'valid')
                ->assertJsonPath("data.rows.{$i}.data.code", $code);
        }
        // Excel's own output (one-digit mantissa, E+, 2+ digit exponent) lost its digits: refused.
        foreach (['1.23457E+12', '1E+12', '1,2E+11'] as $offset => $code) {
            $i = $offset + 3;
            $response->assertJsonPath("data.rows.{$i}.status", 'invalid')
                ->assertJsonPath("data.rows.{$i}.errors.0", __('import.errors.scientific_code', ['field' => __('import.columns.items.code'), 'value' => $code]));
        }
    }

    public function test_a_zero_sale_price_and_a_floor_above_the_sale_price_are_errors(): void
    {
        $this->preview('items', $this->xlsx([
            ['اسم الصنف', 'الوحدة', 'سعر البيع', 'أقل سعر بيع (جملة)'],
            ['مجاني', 'قطعة', '0', ''],
            ['أرضية أعلى', 'قطعة', '50', '60'],
            ['سليم', 'قطعة', '50', '50'],
        ]))->assertOk()
            ->assertJsonPath('data.rows.0.errors', [__('import.errors.must_be_positive', ['field' => __('import.columns.items.selling_price')])])
            ->assertJsonPath('data.rows.1.errors', [__('import.errors.min_above_selling', [
                'min' => __('import.columns.items.min_selling_price'), 'selling' => __('import.columns.items.selling_price'),
            ])])
            ->assertJsonPath('data.rows.2.status', 'valid');
    }

    // ---------------------------------------------------------------- error report download

    public function test_the_error_report_lists_every_skipped_row_with_its_reason(): void
    {
        $this->inTenant($this->tenant, fn () => Item::query()->create([
            'code' => 'EX-1', 'name' => 'Existing', 'unit' => 'كجم', 'cost_price' => '1.000', 'selling_price' => '2.000',
            'weighted_avg_cost' => '1.000', 'min_selling_price' => '0.000', 'min_stock_level' => '0.000', 'current_stock' => '0.000', 'is_active' => true,
        ]));

        $token = (string) $this->preview('items', $this->xlsx([
            ['الكود', 'اسم الصنف', 'الوحدة', 'سعر البيع'],
            ['A-1', 'سليم', 'كجم', '10'],
            ['A-2', '', 'كجم', '10'],
            ['EX-1', 'مكرر', 'كجم', '10'],
            ['A-3', '=cmd()', 'كجم', '0'],
        ]))->assertOk()->json('data.token');

        $response = $this->post('/api/v1/imports/items/errors', ['token' => $token], $this->tenantHeaders($this->tenant))->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = array_values(array_filter(explode("\r\n", substr($csv, 3))));
        $this->assertCount(4, $lines); // header + 3 skipped rows, the valid row is not in the report
        $this->assertStringContainsString(__('import.error_report.row_number'), $lines[0]);
        $this->assertStringStartsWith('"3",', $lines[1]);
        $this->assertStringContainsString(__('import.errors.required', ['field' => __('import.columns.items.name')]), $lines[1]);
        $this->assertStringContainsString(__('import.error_report.statuses.duplicate'), $lines[2]);
        // Formula-looking text is neutralised in the report.
        $this->assertStringContainsString("\"'=cmd()\"", $lines[3]);

        // The report is read-only: the token still commits.
        $this->commit('items', $token, skipInvalid: true)->assertOk()->assertJsonPath('data.created', 1);
    }

    public function test_the_error_report_shows_the_cells_as_typed_not_the_normalized_values(): void
    {
        $token = (string) $this->preview('customers', $this->xlsx([
            ['اسم العميل', 'الموبايل', 'الرصيد الافتتاحي'],
            ['', '+20 100 123 4567', '١٢٫٥'],
            ['عميل', '01001234567', '5'],
            ['عميل تاني', '0020 100 123 4567', '١٬٠٠٠'],
        ]))->assertOk()->json('data.token');

        $csv = $this->post('/api/v1/imports/customers/errors', ['token' => $token], $this->tenantHeaders($this->tenant))->assertOk()->streamedContent();
        $lines = array_values(array_filter(explode("\r\n", substr($csv, 3))));

        $this->assertCount(3, $lines); // header, the invalid row, the in-file duplicate
        // What the user typed, so the line can be found in the sheet: not 01001234567 / 12.500.
        // (a leading + is neutralised against formula injection, as every exported cell is).
        $this->assertStringContainsString('"\'+20 100 123 4567"', $lines[1]);
        $this->assertStringContainsString('"١٢٫٥"', $lines[1]);
        $this->assertStringNotContainsString('01001234567', $lines[1]);
        $this->assertStringNotContainsString('12.500', $lines[1]);
        $this->assertStringContainsString(__('import.error_report.statuses.duplicate'), $lines[2]);
        $this->assertStringContainsString('"0020 100 123 4567"', $lines[2]);
        $this->assertStringContainsString('"١٬٠٠٠"', $lines[2]);
        $this->assertStringNotContainsString('1000.000', $lines[2]);
    }

    public function test_the_error_report_needs_auth_permission_a_valid_token_and_stays_in_its_tenant(): void
    {
        $token = (string) $this->preview('items', $this->xlsx([['اسم الصنف', 'الوحدة', 'سعر البيع'], ['', 'كجم', '1']]))->assertOk()->json('data.token');

        $this->postJson('/api/v1/imports/items/errors', ['token' => $token], $this->tenantGuestHeaders($this->tenant))->assertUnauthorized();

        $customersOnly = $this->createTenantUser($this->tenant, null, ['customers.manage']);
        $this->postJson('/api/v1/imports/items/errors', ['token' => $token], $this->tenantHeaders($this->tenant, $customersOnly))->assertForbidden();

        $this->postJson('/api/v1/imports/items/errors', [], $this->tenantHeaders($this->tenant))->assertStatus(422)->assertJsonValidationErrors('token');

        // Another user of the same tenant, another type, an unknown token: not found.
        $other = $this->createTenantUser($this->tenant, null, ['items.create']);
        $this->postJson('/api/v1/imports/items/errors', ['token' => $token], $this->tenantHeaders($this->tenant, $other))->assertNotFound();
        $this->postJson('/api/v1/imports/categories/errors', ['token' => $token], $this->tenantHeaders($this->tenant))->assertNotFound();
        $this->postJson('/api/v1/imports/items/errors', ['token' => 'nope'], $this->tenantHeaders($this->tenant))->assertNotFound();

        // Tenant isolation: the token means nothing in another shop.
        $otherTenant = $this->createTenant();
        $this->postJson('/api/v1/imports/items/errors', ['token' => $token], $this->tenantHeaders($otherTenant))->assertNotFound();
    }

    // ---------------------------------------------------------------- size

    public function test_a_10000_row_file_previews_and_commits_in_one_all_or_nothing_transaction(): void
    {
        $rows = [['اسم الصنف', 'الوحدة', 'سعر البيع', 'كمية أول المدة', 'تكلفة أول المدة']];
        for ($i = 1; $i <= 10000; $i++) {
            $rows[] = ['صنف '.$i, 'قطعه', '12.5', $i % 2 === 0 ? '4' : '', '8'];
        }
        $path = tempnam(sys_get_temp_dir(), 'big').'.csv';
        $handle = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }
        fclose($handle);

        $response = $this->preview('items', new UploadedFile($path, 'big.csv', null, null, true))->assertOk()
            ->assertJsonPath('data.summary', ['total' => 10000, 'valid' => 10000, 'invalid' => 0, 'duplicates' => 0])
            ->assertJsonPath('data.rows_truncated', true);

        $this->commit('items', (string) $response->json('data.token'))->assertOk()->assertJsonPath('data.created', 10000);

        $this->inTenant($this->tenant, function (): void {
            $this->assertSame(10000, Item::query()->count());
            $this->assertSame(5000, StockMovement::query()->where('movement_type', 'opening_balance_in')->count());
            $this->assertSame(0, Item::query()->where('unit', '!=', 'قطعة')->count());
        });
    }
}
