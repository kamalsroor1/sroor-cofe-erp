<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Services\Billing\ProrationCalculator;
use PHPUnit\Framework\TestCase;

final class ProrationCalculatorTest extends TestCase
{
    public function test_the_reported_case_is_217_367(): void
    {
        $this->assertSame('217.367', (new ProrationCalculator)->difference('449.000', '899.000', 1293766, 2678400));
    }

    public function test_first_day_is_the_full_difference(): void
    {
        $this->assertSame('450.000', (new ProrationCalculator)->difference('449.000', '899.000', 2678400, 2678400));
    }

    public function test_last_second_is_almost_nothing(): void
    {
        $this->assertSame('0.000', (new ProrationCalculator)->difference('449.000', '899.000', 1, 2678400));
    }

    public function test_no_time_left_or_no_gap_is_zero(): void
    {
        $calc = new ProrationCalculator;

        $this->assertSame('0.000', $calc->difference('449.000', '899.000', 0, 2678400));
        $this->assertSame('0.000', $calc->difference('899.000', '449.000', 1000, 2678400));
        $this->assertSame('0.000', $calc->difference('449.000', '899.000', 1000, 0));
    }
}
