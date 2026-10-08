<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ENTI-1.4 (central): which add-on is offered on which plan.
 *
 * - `is_available` = can be bought on this plan;
 * - `included_quantity` = units the plan already includes (e.g. mixes.manage on Enterprise);
 * - `unit_price_override` / `yearly_price_override` = optional flat per-plan price for that
 *   cycle (NULL = catalog price + tiers). Both explicit: the yearly one is never derived.
 *
 * Deleting a plan or an add-on removes its availability rows only (configuration, not money).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plan_addon')) {
            return;
        }

        Schema::create('plan_addon', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained('addons')->cascadeOnDelete();
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('included_quantity')->default(0);
            $table->decimal('unit_price_override', 12, 3)->nullable();
            $table->decimal('yearly_price_override', 12, 3)->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'addon_id']);
            $table->index('addon_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_addon');
    }
};
