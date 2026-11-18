<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FREE-15 (central): `tenants.last_activity_at`, the clock of the dormant free shop policy
 * (reminder at 60 days, archive at 90). Stamped at login and, throttled, on authenticated tenant
 * API use (App\Services\Tenancy\TenantActivityTracker). NULL = never stamped: readers fall back to
 * `created_at`.
 *
 * Backfill on purpose: every EXISTING row gets now(). The column is new, so existing shops have no
 * recorded activity; leaving them NULL would make a shop created long ago and used daily look
 * dormant on the first run and be reminded (and, 30 days later, archived) by mistake. Starting
 * their clock at deployment can only delay a reminder, never wrongly archive a shop. A single
 * UPDATE on `tenants` (one row per shop); no tenant database is read or touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants') || Schema::hasColumn('tenants', 'last_activity_at')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            $table->timestamp('last_activity_at')->nullable()->index();
        });

        DB::table('tenants')->whereNull('last_activity_at')->update(['last_activity_at' => now()]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasColumn('tenants', 'last_activity_at')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex(['last_activity_at']);
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('last_activity_at');
        });
    }
};
