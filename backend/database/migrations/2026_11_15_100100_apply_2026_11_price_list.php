<?php

declare(strict_types=1);

use App\Support\PublicCatalogCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PRICE-2026-11 (central DB, data only): the re-priced catalog (owner decision 2026-11-15,
 * EGP excluding 14% VAT, yearly = 10 x monthly) on an installation that already has it.
 *
 *              list m / y        founder yr1 m / y   founder yr2 (transition) m / y
 *   basic      499 / 4,990       349 / 3,490         429 / 4,290
 *   pro        999 / 9,990       699 / 6,990         849 / 8,490
 *   enterprise 1,499 / 14,990    1,099 / 10,990      1,299 / 12,990
 *   free       0 / 0             none                none
 *
 * - Only the catalog slugs (free / basic / pro / enterprise) are written; super-admin custom
 *   plans are never touched. Promotions (compare_at_*, promo_*) are NOT written: the catalog
 *   ships without an active promotion and the owner switches one on from the console.
 * - Existing subscriptions are not repriced: subscriptions.amount is the frozen price of the
 *   paid term; the next invoice reads the new plan price (no legacy shops, owner decision).
 * - Idempotent (a row already at the approved prices is not written again). An empty catalog (fresh install / test
 *   DB) is left to the idempotent PlansAndFeaturesSeeder, which carries the same numbers.
 * - down() writes the previous approved prices back, but only on a row that still holds
 *   exactly the values written here (a later super-admin edit is never overwritten).
 */
return new class extends Migration
{
    /** @var array<string, array<string, string|null>> */
    private const NEW_PRICES = [
        'free' => [
            'price_monthly' => '0.000',
            'price_yearly' => '0.000',
            'founder_price_monthly' => null,
            'founder_price_yearly' => null,
            'founder_transition_price_monthly' => null,
            'founder_transition_price_yearly' => null,
        ],
        'basic' => [
            'price_monthly' => '499.000',
            'price_yearly' => '4990.000',
            'founder_price_monthly' => '349.000',
            'founder_price_yearly' => '3490.000',
            'founder_transition_price_monthly' => '429.000',
            'founder_transition_price_yearly' => '4290.000',
        ],
        'pro' => [
            'price_monthly' => '999.000',
            'price_yearly' => '9990.000',
            'founder_price_monthly' => '699.000',
            'founder_price_yearly' => '6990.000',
            'founder_transition_price_monthly' => '849.000',
            'founder_transition_price_yearly' => '8490.000',
        ],
        'enterprise' => [
            'price_monthly' => '1499.000',
            'price_yearly' => '14990.000',
            'founder_price_monthly' => '1099.000',
            'founder_price_yearly' => '10990.000',
            'founder_transition_price_monthly' => '1299.000',
            'founder_transition_price_yearly' => '12990.000',
        ],
    ];

    /** The approved catalog before this migration (ENTI-1.8 / CTO W1 Q4), for down(). */
    private const PREVIOUS_PRICES = [
        'basic' => ['price_monthly' => '449.000', 'price_yearly' => '4490.000', 'founder_price_monthly' => '299.000', 'founder_price_yearly' => '2990.000'],
        'pro' => ['price_monthly' => '899.000', 'price_yearly' => '8990.000', 'founder_price_monthly' => '599.000', 'founder_price_yearly' => '5990.000'],
        'enterprise' => ['price_monthly' => '1799.000', 'price_yearly' => '17990.000', 'founder_price_monthly' => '999.000', 'founder_price_yearly' => '9990.000'],
    ];

    public function up(): void
    {
        if (! $this->ready() || ! DB::table('plans')->whereIn('slug', array_keys(self::NEW_PRICES))->exists()) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();

            // Intentional: overwrites the 4 catalog rows (even super-admin edits) with the owner-approved prices.
            foreach (self::NEW_PRICES as $slug => $prices) {
                $row = DB::table('plans')->where('slug', $slug)->first();
                // A row that already holds the approved prices is not rewritten (a re-run changes nothing).
                if ($row === null || $this->holdsNewPrices($row, $prices)) {
                    continue;
                }

                DB::table('plans')->where('slug', $slug)->update($prices + ['updated_at' => $now]);
            }
        });

        PublicCatalogCache::forget();
    }

    public function down(): void
    {
        if (! $this->ready()) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();

            foreach (self::PREVIOUS_PRICES as $slug => $previous) {
                $row = DB::table('plans')->where('slug', $slug)->first();
                if ($row === null || ! $this->holdsNewPrices($row, self::NEW_PRICES[$slug])) {
                    continue;
                }

                DB::table('plans')->where('slug', $slug)->update($previous + [
                    'founder_transition_price_monthly' => null,
                    'founder_transition_price_yearly' => null,
                    'updated_at' => $now,
                ]);
            }
        });

        PublicCatalogCache::forget();
    }

    private function ready(): bool
    {
        return Schema::hasTable('plans')
            && Schema::hasColumn('plans', 'founder_transition_price_monthly')
            && Schema::hasColumn('plans', 'founder_transition_price_yearly');
    }

    /**
     * @param  array<string, string|null>  $prices
     */
    private function holdsNewPrices(object $row, array $prices): bool
    {
        foreach ($prices as $column => $expected) {
            $actual = $row->{$column} ?? null;

            if ($expected === null || $actual === null) {
                if ($expected !== $actual) {
                    return false;
                }

                continue;
            }

            if (bccomp((string) $actual, $expected, 3) !== 0) {
                return false;
            }
        }

        return true;
    }
};
