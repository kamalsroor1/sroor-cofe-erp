<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BRND-2 (CENTRAL DB) data migration: before W2 the super-admin units screen wrote the
 * platform unit catalog as `global_system_units` into a `settings` table on the central
 * connection (a tenant-only table that may exist centrally only if it was created by hand).
 * Copy it into `platform_settings` if, and only if, that legacy table and row exist.
 *
 * - Never overwrites an existing platform_settings row (idempotent).
 * - Ignores a soft-deleted or blank legacy row.
 * - Leaves the legacy row in place (non-destructive).
 * - down() removes only the row this migration created (`updated_by` NULL and the legacy
 *   value still matching), so a later operator edit survives a rollback.
 */
return new class extends Migration
{
    private const KEY = 'global_system_units';

    public function up(): void
    {
        $legacy = $this->legacyValue();

        if ($legacy === null
            || ! Schema::hasTable('platform_settings')
            || DB::table('platform_settings')->where('key', self::KEY)->exists()) {
            return;
        }

        $now = now();

        DB::table('platform_settings')->insert([
            'key' => self::KEY,
            'value' => $legacy,
            'type' => 'string',
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        $legacy = $this->legacyValue();

        if ($legacy === null || ! Schema::hasTable('platform_settings')) {
            return;
        }

        DB::table('platform_settings')
            ->where('key', self::KEY)
            ->where('value', $legacy)
            ->whereNull('updated_by')
            ->delete();
    }

    private function legacyValue(): ?string
    {
        if (! Schema::hasTable('settings')
            || ! Schema::hasColumn('settings', 'key')
            || ! Schema::hasColumn('settings', 'value')) {
            return null;
        }

        $query = DB::table('settings')->where('key', self::KEY);
        if (Schema::hasColumn('settings', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $value = trim((string) $query->value('value'));

        return $value !== '' ? $value : null;
    }
};
