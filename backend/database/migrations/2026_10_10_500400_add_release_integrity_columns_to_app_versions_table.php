<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * APP-3 extension (W2 batch 5), CENTRAL database: what ReleaseBinaryInspector found in an
 * uploaded release, and one release per (platform, version_code).
 *
 *   binary_mime           libmagic type of the stored binary
 *   apk_signing_block     the APK carries a v2/v3 APK Signing Block (Android)
 *   authenticode_present  the installer carries an Authenticode certificate table (Windows,
 *                         recorded only; not enforced until a certificate exists, CTO Q11)
 *   unique(platform, version_code)
 *
 * Duplicate (platform, version_code) rows are NOT resolved automatically: deciding which
 * release survives is an operator decision. up() stops with a clear message listing them,
 * before touching the schema. down() drops exactly what up() added.
 */
return new class extends Migration
{
    private const TABLE = 'app_versions';

    private const UNIQUE = 'app_versions_platform_version_code_unique';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (! Schema::hasIndex(self::TABLE, self::UNIQUE)) {
            $this->assertNoDuplicateVersionCodes();
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            if (! Schema::hasColumn(self::TABLE, 'binary_mime')) {
                $table->string('binary_mime', 100)->nullable()->after('apk_checksum');
            }
            if (! Schema::hasColumn(self::TABLE, 'apk_signing_block')) {
                $table->boolean('apk_signing_block')->default(false)->after('binary_mime');
            }
            if (! Schema::hasColumn(self::TABLE, 'authenticode_present')) {
                $table->boolean('authenticode_present')->default(false)->after('apk_signing_block');
            }
        });

        if (! Schema::hasIndex(self::TABLE, self::UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique(['platform', 'version_code'], self::UNIQUE);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (Schema::hasIndex(self::TABLE, self::UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::UNIQUE);
            });
        }

        foreach (['authenticode_present', 'apk_signing_block', 'binary_mime'] as $column) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                Schema::table(self::TABLE, function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }

    private function assertNoDuplicateVersionCodes(): void
    {
        $duplicates = DB::table(self::TABLE)
            ->select('platform', 'version_code', DB::raw('count(*) as releases'))
            ->groupBy('platform', 'version_code')
            ->havingRaw('count(*) > 1')
            ->orderBy('platform')
            ->orderBy('version_code')
            ->limit(20)
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        $list = $duplicates
            ->map(static fn (object $row): string => sprintf('%s/%s (x%s)', $row->platform, $row->version_code, $row->releases))
            ->implode(', ');

        // Developer/operator-facing (migration console), never shown to a tenant.
        throw new RuntimeException(
            'Cannot add unique(platform, version_code) to app_versions: duplicate releases exist: '.$list
            .'. Delete or deactivate-and-delete the extra rows from the super-admin releases page '
            .'(keep the one whose binary/checksum is the shipped one), then run the migration again.'
        );
    }
};
