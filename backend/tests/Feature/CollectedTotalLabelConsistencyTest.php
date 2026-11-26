<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Card #218 (demo trial 2026-11-26): the sales report labelled summary.total_paid "collected in
 * cash in the treasury" although it sums every payment method (cash, InstaPay, wallets, card,
 * transfer), while the invoices page called the same figure "collected in cash & digital".
 * One concept, one label, in both locales.
 */
final class CollectedTotalLabelConsistencyTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function locales(): array
    {
        return ['ar' => ['ar'], 'en' => ['en']];
    }

    #[DataProvider('locales')]
    public function test_report_and_invoices_page_use_the_same_collected_label(string $locale): void
    {
        $this->assertSame(
            __('invoices.collected_cash_electronic', [], $locale),
            __('reports.cash_collected_treasury', [], $locale),
        );
    }

    public function test_arabic_label_no_longer_claims_cash_only(): void
    {
        $this->assertStringNotContainsString('بالخزينة', __('reports.cash_collected_treasury', [], 'ar'));
    }
}
