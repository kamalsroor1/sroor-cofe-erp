<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BRND-1 (CENTRAL DB): platform-wide branding/settings (name, logos, support
 * contacts, powered-by switch...). One typed key/value row per setting, read only
 * through App\Services\Branding\PlatformBranding (config/branding.php is the fallback).
 *
 * `updated_by` points at the platform operator (central_users) who last changed the
 * row; it is nulled if that operator is ever removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_settings')) {
            return;
        }

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->text('value')->nullable();
            $table->string('type', 16)->default('string');
            $table->foreignId('updated_by')->nullable()->index()
                ->constrained('central_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
