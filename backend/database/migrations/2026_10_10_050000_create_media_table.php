<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRAL media table (PKG-2): rows of App\Models\CentralMedia — payment receipts (ENTI-3.3,
 * disk central_private) and platform assets (BRND-2, disk central_public).
 *
 * Same file name in database/migrations and database/migrations/tenant (like
 * personal_access_tokens): schema of spatie/laravel-medialibrary v11. Guarded with
 * hasTable because the legacy test base runs both folders on one sqlite DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('media')) {
            return;
        }

        Schema::create('media', function (Blueprint $table): void {
            $table->id();

            $table->morphs('model');
            $table->uuid()->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();

            $table->nullableTimestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
