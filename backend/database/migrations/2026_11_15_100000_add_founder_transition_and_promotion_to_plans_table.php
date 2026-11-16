<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRICE-2026-11 (central): founder price schedule step 2 and time-bound promotions on `plans`.
 *
 * - founder_transition_price_monthly / _yearly: the price a founder pays in months 13-24
 *   (config billing.founder.transition_months). NULL = no transition step: the founder goes
 *   straight to the list price after year 1.
 * - compare_at_price_monthly / _yearly: the "was" price of a promotion. Shown only while
 *   promo_starts_at <= now < promo_ends_at, and only when it is above the real price
 *   (App\Support\Billing\PlanPromotion). NULL = no promotion for that cycle.
 * - promo_starts_at (NULL = already started) / promo_ends_at (required to show anything).
 *
 * Schema only: the approved values are written by 2026_11_15_100100. Subscriptions get no
 * column: the end of the transition step is derived from founder_price_until.
 */
return new class extends Migration
{
    private const MONEY_COLUMNS = [
        'founder_transition_price_monthly',
        'founder_transition_price_yearly',
        'compare_at_price_monthly',
        'compare_at_price_yearly',
    ];

    private const DATE_COLUMNS = ['promo_starts_at', 'promo_ends_at'];

    public function up(): void
    {
        if (! Schema::hasTable('plans')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table): void {
            foreach (self::MONEY_COLUMNS as $column) {
                if (! Schema::hasColumn('plans', $column)) {
                    $table->decimal($column, 12, 3)->nullable();
                }
            }

            foreach (self::DATE_COLUMNS as $column) {
                if (! Schema::hasColumn('plans', $column)) {
                    $table->timestamp($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('plans')) {
            return;
        }

        $existing = array_values(array_filter(
            [...self::MONEY_COLUMNS, ...self::DATE_COLUMNS],
            static fn (string $column): bool => Schema::hasColumn('plans', $column),
        ));

        if ($existing !== []) {
            Schema::table('plans', function (Blueprint $table) use ($existing): void {
                $table->dropColumn($existing);
            });
        }
    }
};
