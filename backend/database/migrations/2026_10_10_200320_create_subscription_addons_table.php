<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ENTI-1.5 (central): add-on lines a tenant actually bought.
 *
 * - `unit_price` = the per-unit price frozen for this line's cycle at purchase time
 *   (resolved through PlanAddon/Addon::unitPriceFor()); DECIMAL(12,3), never recomputed;
 * - `billing_cycle` / `status` = string(20) + model casts (App\Enums\Billing\BillingCycle,
 *   App\Enums\Billing\SubscriptionAddonStatus), same choice as subscriptions (ENTI-1.3);
 *   a new line is `pending_payment` until ActivateSubscriptionAction activates it;
 * - `starts_at` NULL = not started yet, `ends_at` NULL = open-ended / one-time service;
 * - `tenant_id` is denormalised from the subscription so the entitlement engine
 *   (ENTI-2.2) reads a tenant's active lines with one indexed query.
 *
 * FKs: tenant/subscription deletion cascades (same as `subscriptions`); an add-on with
 * purchased lines cannot be deleted from the catalog (deactivate it instead).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscription_addons')) {
            return;
        }

        Schema::create('subscription_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained('addons')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 3);
            $table->string('billing_cycle', 20)->default('monthly');
            $table->string('status', 20)->default('pending_payment');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index('subscription_id');
            $table->index('addon_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_addons');
    }
};
