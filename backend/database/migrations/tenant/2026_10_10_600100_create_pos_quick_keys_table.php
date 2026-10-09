<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POSB-6 (tenant DB): per-store POS quick keys (pages 1-5 x positions 0-59).
 * A key points at an item OR a category (`type`); the other FK stays null.
 * `color` holds a name from App\Enums\PosQuickKeyColor (fixed palette, never free hex);
 * null = automatic colour on the client. Stored as a short string, not a DB enum, so the
 * palette can grow without an ALTER on every tenant DB.
 * Not soft-deleted: the layout is configuration, replaced wholesale on every save.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_quick_keys')) {
            return;
        }

        Schema::create('pos_quick_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedTinyInteger('page');
            $table->unsignedTinyInteger('position');
            $table->string('type', 10);
            $table->foreignId('item_id')->nullable()->constrained('items')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->string('label', 30)->nullable();
            $table->string('color', 16)->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'page', 'position'], 'pos_quick_keys_store_page_position_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_quick_keys');
    }
};
