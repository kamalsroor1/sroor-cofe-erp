<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-SHIFT (tenant DB): the cash shift a sales return belongs to, stamped when the return is
 * created (App\Services\Shifts\ShiftAttribution), like the payments / expenses / invoices of
 * DATE-01. Its cash refund voucher (PAY-RET) is a payment and is already stamped.
 *
 * Same shape as 2026_11_18_100000 (online-safe on a large shop): a plain nullable column with no
 * default (in-place add), one secondary index (cash_shift_id, created_at) built online, no foreign
 * key, no backfill (existing rows stay NULL and keep the old time window). Guarded, so running
 * up() or down() twice is a no-op.
 */
return new class extends Migration
{
    private const TABLE = 'returns';

    private const COLUMN = 'cash_shift_id';

    private const INDEX = 'returns_cash_shift_created_idx';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unsignedBigInteger(self::COLUMN)->nullable();
            });
        }

        if (Schema::hasColumn(self::TABLE, 'created_at') && ! $this->hasIndex()) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->index([self::COLUMN, 'created_at'], self::INDEX);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if ($this->hasIndex()) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropIndex(self::INDEX);
            });
        }

        if (Schema::hasColumn(self::TABLE, self::COLUMN)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropColumn(self::COLUMN);
            });
        }
    }

    private function hasIndex(): bool
    {
        foreach (Schema::getIndexes(self::TABLE) as $existing) {
            if (strcasecmp((string) $existing['name'], self::INDEX) === 0) {
                return true;
            }
        }

        return false;
    }
};
