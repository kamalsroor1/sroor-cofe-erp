<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ENTI-1.6 extension (central), CTO decision W1 Q3 [2026-10-09]:
 * `billing_payments.gateway_reference` is unique PER GATEWAY, not globally.
 *
 * A reference is only meaningful inside the gateway that issued it (a Paymob transaction
 * id and a Fawry reference code may collide), so the idempotency key becomes
 * (gateway, gateway_reference): the same reference on another gateway is accepted, the
 * same reference twice on one gateway is still rejected. NULL references stay unlimited
 * (both MySQL and sqlite allow many NULLs in a unique index).
 *
 * No data change: every existing row already satisfies the stricter global unique.
 * down() restores the global unique and refuses (before touching the schema) when two
 * gateways already share a reference, instead of deleting or rewriting payment records.
 */
return new class extends Migration
{
    private const TABLE = 'billing_payments';

    private const GLOBAL_UNIQUE = 'billing_payments_gateway_reference_unique';

    private const GATEWAY_UNIQUE = 'billing_payments_gateway_gateway_reference_unique';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        // Add the per-gateway key first, so there is never a moment without an idempotency key.
        if (! Schema::hasIndex(self::TABLE, self::GATEWAY_UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique(['gateway', 'gateway_reference'], self::GATEWAY_UNIQUE);
            });
        }

        if (Schema::hasIndex(self::TABLE, self::GLOBAL_UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::GLOBAL_UNIQUE);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (! Schema::hasIndex(self::TABLE, self::GLOBAL_UNIQUE)) {
            $shared = DB::table(self::TABLE)
                ->whereNotNull('gateway_reference')
                ->groupBy('gateway_reference')
                ->havingRaw('count(*) > 1')
                ->limit(1)
                ->pluck('gateway_reference');

            if ($shared->isNotEmpty()) {
                // Developer-facing (migration console), never shown to a tenant.
                throw new RuntimeException(
                    'Cannot restore the global unique on billing_payments.gateway_reference: '
                    .'the same reference is used on more than one gateway. Resolve those payments manually first.'
                );
            }

            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique('gateway_reference', self::GLOBAL_UNIQUE);
            });
        }

        if (Schema::hasIndex(self::TABLE, self::GATEWAY_UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::GATEWAY_UNIQUE);
            });
        }
    }
};
