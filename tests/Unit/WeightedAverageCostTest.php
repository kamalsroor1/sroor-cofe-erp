<?php

namespace Tests\Unit;

use App\Support\WeightedAverageCost;
use PHPUnit\Framework\TestCase;

class WeightedAverageCostTest extends TestCase
{
    public function test_add_from_empty_stock_returns_incoming_cost(): void
    {
        $this->assertSame('385.000', WeightedAverageCost::add('0.000', '0.000', '48.000', '385.000'));
    }

    public function test_add_ignores_negative_stock(): void
    {
        $this->assertSame('200.000', WeightedAverageCost::add('-3.000', '999.000', '10.000', '200.000'));
    }

    public function test_add_blends_existing_and_incoming_quantities(): void
    {
        // (10 x 100 + 10 x 200) / 20
        $this->assertSame('150.000', WeightedAverageCost::add('10.000', '100.000', '10.000', '200.000'));
        // Production item 16, purchase #14: (31.5 x 385 + 10 x 382.5) / 41.5 = 384.3976 -> 384.398
        $this->assertSame('384.398', WeightedAverageCost::add('31.500', '385.000', '10.000', '382.500'));
    }

    public function test_add_rounds_half_up_instead_of_truncating(): void
    {
        // 1000 / 3 = 333.3333 -> 333.333 ; 2000 / 3 = 666.6666 -> 666.667 (bcdiv scale 3 would give 666.666)
        $this->assertSame('333.333', WeightedAverageCost::add('0.000', '0.000', '3.000', '333.3333'));
        $this->assertSame('666.667', WeightedAverageCost::add('1.000', '1000.000', '2.000', '500.000'));
    }

    public function test_remove_is_the_exact_inverse_of_add(): void
    {
        $afterPurchase = WeightedAverageCost::add('28.000', '385.000', '15.000', '407.500');
        $this->assertSame('392.849', $afterPurchase);

        $afterCancel = WeightedAverageCost::remove('43.000', $afterPurchase, '15.000', '407.500');
        $this->assertSame('385.000', $afterCancel);
    }

    public function test_remove_restores_round_value_without_truncation_drift(): void
    {
        // Item 20 case: 6 @ 230, buy 5 @ 252.5, cancel -> must be 230.000 (truncation gave 229.999)
        $wac = WeightedAverageCost::add('6.000', '230.000', '5.000', '252.500');
        $this->assertSame('230.000', WeightedAverageCost::remove('11.000', $wac, '5.000', '252.500'));
    }

    public function test_remove_of_all_stock_keeps_last_cost(): void
    {
        $this->assertSame('150.000', WeightedAverageCost::remove('10.000', '150.000', '10.000', '200.000'));
    }

    public function test_remove_never_returns_zero_or_negative_cost(): void
    {
        // A legacy-distorted WAC of 10 cannot absorb removing 5 units at 100
        $this->assertSame('10.000', WeightedAverageCost::remove('10.000', '10.000', '5.000', '100.000'));
    }
}
