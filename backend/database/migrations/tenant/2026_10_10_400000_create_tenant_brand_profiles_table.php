<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT table (BRND-5): the shop's brand profile, a single row per tenant database
 * that owns the logo_light / logo_dark media (tenant `media` table, tenant-suffixed disk).
 * Text branding (name, subtitle, receipt lines, colors) stays in the `settings` k/v table.
 *
 * `singleton_key` is unique so two concurrent first uploads cannot create two profiles.
 * Guarded with hasTable because the legacy test base runs both migration folders on one DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_brand_profiles')) {
            return;
        }

        Schema::create('tenant_brand_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('singleton_key', 16)->default('default')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_brand_profiles');
    }
};
