<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BRND-1 (CENTRAL DB) data migration: before BRND-1 the super-admin screen wrote the
 * platform name/subtitle/support contacts into a `settings` table on the central
 * connection (a tenant-only table that may exist centrally only if it was created by
 * hand). Copy those values into `platform_settings` if, and only if, that table exists.
 *
 * - Never overwrites a platform_settings row that already exists (idempotent).
 * - Ignores soft-deleted legacy rows and blank values.
 * - Leaves the legacy `settings` rows in place (non-destructive; the old super-admin
 *   endpoints still read them until BRND-2 replaces them).
 * - down() removes only the rows this migration created (marked by `updated_by` NULL
 *   and the legacy value still matching), so later operator edits survive a rollback.
 */
return new class extends Migration
{
    /** legacy `settings.key` => `platform_settings.key` (first legacy key with a value wins). */
    private const MAP = [
        'platform_name' => 'name',
        'app_name' => 'name',
        'platform_subtitle' => 'subtitle',
        'support_email' => 'support_email',
        'support_phone' => 'support_phone',
    ];

    public function up(): void
    {
        $legacy = $this->legacyValues();

        if ($legacy === [] || ! Schema::hasTable('platform_settings')) {
            return;
        }

        $now = now();

        foreach ($legacy as $key => $value) {
            if (DB::table('platform_settings')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('platform_settings')->insert([
                'key' => $key,
                'value' => $value,
                'type' => 'string',
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        foreach ($this->legacyValues() as $key => $value) {
            DB::table('platform_settings')
                ->where('key', $key)
                ->where('value', $value)
                ->whereNull('updated_by')
                ->delete();
        }
    }

    /**
     * @return array<string, string> platform_settings key => legacy value
     */
    private function legacyValues(): array
    {
        if (! Schema::hasTable('settings')
            || ! Schema::hasColumn('settings', 'key')
            || ! Schema::hasColumn('settings', 'value')) {
            return [];
        }

        $query = DB::table('settings')->whereIn('key', array_keys(self::MAP));
        if (Schema::hasColumn('settings', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        /** @var array<string, mixed> $rows */
        $rows = $query->pluck('value', 'key')->all();

        $values = [];
        foreach (self::MAP as $legacyKey => $key) {
            $value = trim((string) ($rows[$legacyKey] ?? ''));

            if ($value !== '' && ! isset($values[$key])) {
                $values[$key] = $value;
            }
        }

        return $values;
    }
};
