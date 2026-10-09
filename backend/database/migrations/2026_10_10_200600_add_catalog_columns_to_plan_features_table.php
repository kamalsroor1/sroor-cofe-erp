<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ENTI-1.8 (central): catalog metadata on the feature registry `plan_features`.
 *
 * - `name_key` = translation key of the feature name (lang/{ar,en}/plans.php), so the UI shows
 *   the name in the user's language instead of the single-language `name` column;
 * - `is_core` = part of every paid plan (Q-E8 + CTO 2026-10-09: 18 core features,
 *   quotations.manage included);
 * - `is_public` = listed in tenant-facing catalogs; hidden keys (pos.offline until the end
 *   of Phase 2, custom.domain until Phase 3) are false and only visible to the super-admin.
 *
 * Defaults keep existing rows as they are (visible, not core) until the one-time data
 * migration 2026_10_10_200610 applies the approved catalog.
 */
return new class extends Migration
{
    private const TABLE = 'plan_features';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            if (! Schema::hasColumn(self::TABLE, 'name_key')) {
                $table->string('name_key', 150)->nullable();
            }
            if (! Schema::hasColumn(self::TABLE, 'is_core')) {
                $table->boolean('is_core')->default(false);
            }
            if (! Schema::hasColumn(self::TABLE, 'is_public')) {
                $table->boolean('is_public')->default(true);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $drop = array_values(array_filter(
            ['name_key', 'is_core', 'is_public'],
            static fn (string $column): bool => Schema::hasColumn(self::TABLE, $column),
        ));

        if ($drop !== []) {
            Schema::table(self::TABLE, function (Blueprint $table) use ($drop): void {
                $table->dropColumn($drop);
            });
        }
    }
};
