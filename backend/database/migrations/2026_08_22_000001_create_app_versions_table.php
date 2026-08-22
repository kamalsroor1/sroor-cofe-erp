<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 30)->default('android')->index();
            $table->string('version_name', 50); // e.g. '1.1.0'
            $table->unsignedInteger('version_code')->index(); // e.g. 2
            $table->unsignedInteger('min_version_code')->default(1); // versions below this require force update
            $table->boolean('is_force_update')->default(false);
            $table->text('release_notes_ar');
            $table->text('release_notes_en')->nullable();
            $table->string('apk_path')->nullable();
            $table->string('apk_filename')->nullable();
            $table->unsignedBigInteger('apk_size_bytes')->default(0);
            $table->string('apk_checksum', 64)->nullable(); // SHA-256
            $table->unsignedInteger('download_count')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
