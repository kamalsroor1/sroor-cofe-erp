<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ENTI-1.3 (central): billing-grade `subscriptions`.
 *
 * - `status` ENUM -> string(20) so it can hold pending_payment / expired
 *   (allowed values = App\Enums\Billing\SubscriptionStatus, enforced by the model cast);
 * - `billing_cycle` ENUM -> string(20) so it can hold biennial
 *   (App\Enums\Billing\BillingCycle; Q-E6: value only, not sold in Phase 1);
 * - `amount` DECIMAL(10,2) -> DECIMAL(12,3) (golden rule 1);
 * - new: currency (EGP), price_locked, is_founder, founder_price_until;
 * - indexes for tenant/status lookups and status/cycle revenue reports.
 *
 * Lifecycle dates (grace_ends_at, trial_extended_at) deliberately live on `tenants`
 * (IDEN-3.2): `tenants.status` decides access, `subscriptions.status` is the billing log.
 */
return new class extends Migration
{
    private const NEW_COLUMNS = ['currency', 'price_locked', 'is_founder', 'founder_price_until'];

    private const LEGACY_STATUSES = ['active', 'past_due', 'cancelled', 'trialing'];

    private const LEGACY_CYCLES = ['monthly', 'yearly'];

    /**
     * down(): new value => closest legacy value, applied BEFORE the column is narrowed
     * back to the old ENUM (otherwise MySQL strict mode / the sqlite CHECK rejects it).
     */
    private const DOWN_STATUS_MAP = [
        'pending_payment' => 'past_due',   // unpaid
        'expired' => 'cancelled',          // no longer running
    ];

    private const DOWN_CYCLE_MAP = [
        'biennial' => 'yearly',
    ];

    private const INDEX_TENANT_STATUS = 'subscriptions_tenant_id_status_index';

    private const INDEX_STATUS_CYCLE = 'subscriptions_status_billing_cycle_index';

    public function up(): void
    {
        if (! Schema::hasTable('subscriptions')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('status', 20)->default('trialing')->change();
            $table->string('billing_cycle', 20)->default('monthly')->change();
            $table->decimal('amount', 12, 3)->change();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'currency')) {
                $table->string('currency', 3)->default('EGP');
            }
            if (! Schema::hasColumn('subscriptions', 'price_locked')) {
                $table->boolean('price_locked')->default(false);
            }
            if (! Schema::hasColumn('subscriptions', 'is_founder')) {
                $table->boolean('is_founder')->default(false);
            }
            if (! Schema::hasColumn('subscriptions', 'founder_price_until')) {
                $table->timestamp('founder_price_until')->nullable();
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasIndex('subscriptions', self::INDEX_TENANT_STATUS)) {
                $table->index(['tenant_id', 'status'], self::INDEX_TENANT_STATUS);
            }
            if (! Schema::hasIndex('subscriptions', self::INDEX_STATUS_CYCLE)) {
                $table->index(['status', 'billing_cycle'], self::INDEX_STATUS_CYCLE);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('subscriptions')) {
            return;
        }

        foreach (self::DOWN_STATUS_MAP as $new => $legacy) {
            DB::table('subscriptions')->where('status', $new)->update(['status' => $legacy]);
        }
        foreach (self::DOWN_CYCLE_MAP as $new => $legacy) {
            DB::table('subscriptions')->where('billing_cycle', $new)->update(['billing_cycle' => $legacy]);
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasIndex('subscriptions', self::INDEX_TENANT_STATUS)) {
                $table->dropIndex(self::INDEX_TENANT_STATUS);
            }
            if (Schema::hasIndex('subscriptions', self::INDEX_STATUS_CYCLE)) {
                $table->dropIndex(self::INDEX_STATUS_CYCLE);
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $drop = array_values(array_filter(
                self::NEW_COLUMNS,
                static fn (string $column): bool => Schema::hasColumn('subscriptions', $column),
            ));

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });

        // Narrowing back to DECIMAL(10,2) rounds a third decimal (accepted on rollback).
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('status', self::LEGACY_STATUSES)->default('trialing')->change();
            $table->enum('billing_cycle', self::LEGACY_CYCLES)->default('monthly')->change();
            $table->decimal('amount', 10, 2)->change();
        });
    }
};
