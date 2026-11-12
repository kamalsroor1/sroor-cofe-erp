<?php

declare(strict_types=1);

namespace Tests\Feature\Export;

use App\Models\Item;
use App\Models\Tenant;
use App\Services\ExportService;
use Tests\TenantTestCase;

/**
 * IMP-02: an item name a spreadsheet would evaluate (`=1+1`, `=HYPERLINK(...)`, `@cmd`) is exported
 * as text (leading quote), while numbers, negative ones included, stay plain numbers.
 */
class ExportServiceCsvInjectionTest extends TenantTestCase
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
    }

    /**
     * @return list<list<string>>
     */
    private function exportedInventory(): array
    {
        $csv = $this->inTenant($this->tenant, function (): string {
            foreach ([['F-1', '=1+1', '10.000'], ['F-2', '=HYPERLINK("http://x","y")', '0.000'], ['F-3', '@SUM(A1)', '0.000'], ['F-4', 'بن عادي', '0.000']] as [$code, $name, $stock]) {
                Item::query()->create([
                    'code' => $code, 'name' => $name, 'unit' => 'كجم', 'cost_price' => '20.000', 'selling_price' => '12.500',
                    'weighted_avg_cost' => '20.000', 'min_selling_price' => '0.000', 'min_stock_level' => '0.000', 'current_stock' => $stock, 'is_active' => true,
                ]);
            }

            ob_start();
            app(ExportService::class)->exportInventory()->sendContent();

            return (string) ob_get_clean();
        });

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = preg_split('/\r?\n/', trim(substr($csv, 3))) ?: [];

        return array_map(static fn (string $line): array => str_getcsv($line, ',', '"', ''), $lines);
    }

    public function test_formula_like_item_names_are_exported_as_text(): void
    {
        $byCode = [];
        foreach (array_slice($this->exportedInventory(), 1) as $row) {
            $byCode[$row[0]] = $row;
        }

        $this->assertSame("'=1+1", $byCode['F-1'][1]);
        $this->assertSame("'=HYPERLINK(\"http://x\",\"y\")", $byCode['F-2'][1]);
        $this->assertSame("'@SUM(A1)", $byCode['F-3'][1]);
        $this->assertSame('بن عادي', $byCode['F-4'][1]);
    }

    public function test_negative_numbers_stay_numbers(): void
    {
        $row = null;
        foreach ($this->exportedInventory() as $line) {
            if ($line[0] === 'F-1') {
                $row = $line;
            }
        }

        $this->assertNotNull($row);
        // 10 x (12.5 - 20) = -75: the expected-profit column is a negative number, not text.
        $this->assertSame('-75.00', $row[9]);
        $this->assertSame('10.000', $row[4]);
    }
}
