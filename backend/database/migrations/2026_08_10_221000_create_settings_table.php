<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });

            // Initial default settings
            DB::table('settings')->insert([
                [
                    'key' => 'company_name',
                    'value' => 'منظومة ERP السحابية',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'key' => 'company_subtitle',
                    'value' => 'لوحة الإدارة المركزية والفوترة السحابية',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'key' => 'show_print_subtitle',
                    'value' => '1',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
