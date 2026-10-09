<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ENTI-1.5 hardening (central): the database itself guarantees that an add-on line
 * belongs to the same tenant as its subscription.
 *
 * `subscription_addons.tenant_id` is denormalised from the parent subscription so the
 * entitlement engine reads a tenant's lines with one indexed query. The model copies it
 * on every save (SubscriptionAddon::booted()), but a raw query could still write a line
 * whose tenant differs from its subscription and grant that tenant add-ons it never
 * bought. This migration replaces the single-column FK with a composite one:
 *
 *   subscription_addons (subscription_id, tenant_id) -> subscriptions (id, tenant_id)
 *
 * which needs a UNIQUE (id, tenant_id) on `subscriptions` (redundant with the primary key
 * for uniqueness, required as the FK target). The FK to `tenants` is kept unchanged.
 * Deleting a subscription still cascades to its lines; changing a subscription's
 * tenant_id while it has lines is now refused by the database.
 *
 * Works on sqlite (Laravel rebuilds the table) and MySQL. down() follows the MySQL rule
 * learned in W1 (error 1553): never drop an index while a foreign key still needs it, so
 * the composite FK goes first, then its index, and only then the single-column FK is
 * re-added on the original `subscription_id` index (re-created first if it went missing).
 */
return new class extends Migration
{
    private const PARENT = 'subscriptions';

    private const CHILD = 'subscription_addons';

    private const PARENT_UNIQUE = 'subscriptions_id_tenant_id_unique';

    private const CHILD_COMPOSITE_INDEX = 'subscription_addons_subscription_id_tenant_id_index';

    private const CHILD_SINGLE_INDEX = 'subscription_addons_subscription_id_index';

    public function up(): void
    {
        if (! Schema::hasTable(self::PARENT) || ! Schema::hasTable(self::CHILD)) {
            return;
        }

        $this->assertNoCrossTenantLines();

        if (! Schema::hasIndex(self::PARENT, self::PARENT_UNIQUE)) {
            Schema::table(self::PARENT, function (Blueprint $table): void {
                $table->unique(['id', 'tenant_id'], self::PARENT_UNIQUE);
            });
        }

        if (! Schema::hasIndex(self::CHILD, self::CHILD_COMPOSITE_INDEX)) {
            Schema::table(self::CHILD, function (Blueprint $table): void {
                $table->index(['subscription_id', 'tenant_id'], self::CHILD_COMPOSITE_INDEX);
            });
        }

        if ($this->hasForeignKey(['subscription_id'])) {
            Schema::table(self::CHILD, function (Blueprint $table): void {
                $table->dropForeign(['subscription_id']);
            });
        }

        if (! $this->hasForeignKey(['subscription_id', 'tenant_id'])) {
            Schema::table(self::CHILD, function (Blueprint $table): void {
                $table->foreign(['subscription_id', 'tenant_id'])
                    ->references(['id', 'tenant_id'])
                    ->on(self::PARENT)
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::PARENT) || ! Schema::hasTable(self::CHILD)) {
            return;
        }

        // 1. The composite FK first: while it exists, MySQL refuses to drop its index (1553).
        if ($this->hasForeignKey(['subscription_id', 'tenant_id'])) {
            Schema::table(self::CHILD, function (Blueprint $table): void {
                $table->dropForeign(['subscription_id', 'tenant_id']);
            });
        }

        // 2. The single-column index the original FK used must exist before that FK comes back,
        //    otherwise MySQL would adopt the composite index and step 3 would hit 1553.
        if (! Schema::hasIndex(self::CHILD, ['subscription_id'])) {
            Schema::table(self::CHILD, function (Blueprint $table): void {
                $table->index('subscription_id', self::CHILD_SINGLE_INDEX);
            });
        }

        // 3. Drop the composite index while no FK depends on it.
        if (Schema::hasIndex(self::CHILD, self::CHILD_COMPOSITE_INDEX)) {
            Schema::table(self::CHILD, function (Blueprint $table): void {
                $table->dropIndex(self::CHILD_COMPOSITE_INDEX);
            });
        }

        // 4. Restore the original FK (same name and behaviour as 2026_10_10_200320).
        if (! $this->hasForeignKey(['subscription_id'])) {
            Schema::table(self::CHILD, function (Blueprint $table): void {
                $table->foreign('subscription_id')->references('id')->on(self::PARENT)->cascadeOnDelete();
            });
        }

        // 5. Nothing references (id, tenant_id) any more.
        if (Schema::hasIndex(self::PARENT, self::PARENT_UNIQUE)) {
            Schema::table(self::PARENT, function (Blueprint $table): void {
                $table->dropUnique(self::PARENT_UNIQUE);
            });
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasForeignKey(array $columns): bool
    {
        foreach (Schema::getForeignKeys(self::CHILD) as $foreignKey) {
            if ($foreignKey['columns'] === $columns && $foreignKey['foreign_table'] === self::PARENT) {
                return true;
            }
        }

        return false;
    }

    /**
     * A line whose tenant differs from its subscription would violate the new FK. MySQL would
     * reject the ALTER anyway; sqlite copies rows during the rebuild without checking, so the
     * check is explicit and identical on both drivers. Nothing is rewritten automatically:
     * such a line is a security finding to be investigated, not data to be "fixed".
     */
    private function assertNoCrossTenantLines(): void
    {
        $mismatched = DB::table(self::CHILD)
            ->join(self::PARENT, self::PARENT.'.id', '=', self::CHILD.'.subscription_id')
            ->whereColumn(self::PARENT.'.tenant_id', '!=', self::CHILD.'.tenant_id')
            ->count();

        if ($mismatched > 0) {
            // Developer-facing (migration console), never shown to a tenant.
            throw new RuntimeException(
                "Cannot add the tenant-scoped foreign key: {$mismatched} subscription_addons row(s) "
                .'have a tenant_id different from their subscription. Investigate them before migrating.'
            );
        }
    }
};
