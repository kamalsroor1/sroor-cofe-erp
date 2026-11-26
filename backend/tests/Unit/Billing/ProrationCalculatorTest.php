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

    /** #232 spec B: (new - current) x remaining days / actual period days, half-up at scale 3. */
    public function test_the_spec_examples_on_a_days_basis(): void
    {
        $calc = new ProrationCalculator;

        $this->assertSame('166.667', $calc->difference('499.000', '999.000', 10, 30));
        $this->assertSame('1369.863', $calc->difference('4990.000', '9990.000', 100, 365));
        $this->assertSame('500.000', $calc->difference('499.000', '999.000', 31, 30), 'never more than the full gap');
    }
}
