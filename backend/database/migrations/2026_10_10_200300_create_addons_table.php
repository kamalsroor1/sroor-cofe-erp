<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ENTI-1.4 (central): the add-on catalog.
 *
 * - `type` = App\Enums\Billing\AddonType (`recurring` billed every cycle, `service` one-time);
 *   string(20) + model cast instead of a DB ENUM (same choice as subscriptions, ENTI-1.3);
 * - `unit_price` = monthly unit price (recurring) or the one-time price (service);
 * - `yearly_price` = explicit stored yearly unit price, NULL = not sold yearly (never derived);
 * - `price_tiers` = explicit stored volume prices, e.g.
 *   [{"min_qty":3,"unit_price":"212.000","yearly_price":"2120.000"}, …]; the whole quantity
 *   is billed at the tier price (Q-E3). No percentages, no rounding rule;
 * - `feature_key` = the plan feature it unlocks (mixes.manage …), `bundled_limits` = limits it
 *   raises per unit ({"stores":1,"users":1}) — read by the entitlement engine (ENTI-2.x).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('addons')) {
            return;
        }

        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name_key', 150);
            $table->string('type', 20)->default('recurring')->index();
            $table->string('feature_key', 100)->nullable()->index();
            $table->json('bundled_limits')->nullable();
            $table->decimal('unit_price', 12, 3)->default(0);
            $table->decimal('yearly_price', 12, 3)->nullable();
            $table->json('price_tiers')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'is_public', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};
