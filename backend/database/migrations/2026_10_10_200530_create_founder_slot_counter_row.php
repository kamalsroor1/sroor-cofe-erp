<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ENTI-1.7 hardening (central): create the founder-slot counter row up front.
 *
 * FounderPricingService::claim() serialises every founder-slot assignment on the row
 * ('founder_slot', '') of `billing_sequences` (SELECT ... FOR UPDATE inside the caller's
 * transaction). When that row did not exist yet, the first concurrent claimers each ran
 * INSERT IGNORE: the losers take a SHARED lock on the winner's row and then all try to
 * upgrade it to an exclusive lock, which InnoDB resolves by killing one of them with a
 * deadlock (1213). Creating the row here means every claim only ever takes the one
 * exclusive row lock, so concurrent signups queue on it and slot 51 is never handed out.
 * The service keeps its lazy insert as a fallback only.
 *
 * Idempotent: an existing row (and its consumed count) is never touched.
 */
return new class extends Migration
{
    private const KEY = 'founder_slot';

    private const PERIOD = '';

    public function up(): void
    {
        if (! Schema::hasTable('billing_sequences')) {
            return;
        }

        $now = now();

        DB::table('billing_sequences')->insertOrIgnore([
            'key' => self::KEY,
            'period' => self::PERIOD,
            'last_value' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Removes the row only while no slot has been handed out: deleting a used counter
     * would reset the founder count and let more than the configured slots be sold.
     */
    public function down(): void
    {
        if (! Schema::hasTable('billing_sequences')) {
            return;
        }

        DB::table('billing_sequences')
            ->where('key', self::KEY)
            ->where('period', self::PERIOD)
            ->where('last_value', 0)
            ->delete();
    }
};
