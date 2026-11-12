<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Item;
use App\Services\Reports\CarryingCost;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * BUG-02: the carrying unit cost is the weighted average when it is positive, else the last
 * purchase cost (cost_price), else 0. Same rule as PurchaseService::effectiveWeightedAverageCost.
 */
final class CarryingCostTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed, 1: mixed, 2: string}>
     */
    public static function cases(): array
    {
        return [
            'average wins' => ['181.205', '200.000', '181.205'],
            'zero average falls back' => ['0.000', '50.000', '50.000'],
            'null average falls back' => [null, '50.000', '50.000'],
            'empty average falls back' => ['', '50.000', '50.000'],
            'negative average falls back' => ['-1.000', '50.000', '50.000'],
            'no figures at all' => [null, null, '0.000'],
            'unscaled inputs are normalized' => ['12.5', '9', '12.500'],
            'fallback is normalized' => ['0', '7.25', '7.250'],
        ];
    }

    #[DataProvider('cases')]
    public function test_the_carrying_cost_rule(mixed $average, mixed $costPrice, string $expected): void
    {
        $this->assertSame($expected, CarryingCost::of($average, $costPrice));
    }

    public function test_for_item_reads_the_two_stored_figures(): void
    {
        $item = new Item;
        $item->setRawAttributes(['weighted_avg_cost' => '0.000', 'cost_price' => '180.000']);
        $this->assertSame('180.000', CarryingCost::forItem($item));

        $item->setRawAttributes(['weighted_avg_cost' => '181.205', 'cost_price' => '200.000']);
        $this->assertSame('181.205', CarryingCost::forItem($item));
    }

    public function test_the_sql_form_is_one_per_row_case_expression(): void
    {
        $this->assertSame(
            '(CASE WHEN items.weighted_avg_cost > 0 THEN items.weighted_avg_cost ELSE COALESCE(items.cost_price, 0) END)',
            CarryingCost::sql(),
        );
    }

    public function test_the_sql_form_refuses_a_non_identifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CarryingCost::sql('items; DROP TABLE items');
    }
}
