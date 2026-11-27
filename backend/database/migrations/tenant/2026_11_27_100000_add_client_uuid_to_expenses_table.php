<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MGT-153: Client-generated idempotency key for expense creation (double-submit / retry safety).
 * Nullable so legacy rows stay NULL; a UNIQUE index allows multiple NULLs on MySQL and SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('expenses', 'client_uuid')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('client_uuid', 100)->nullable()->after('expense_number');
            $table->unique('client_uuid', 'expenses_client_uuid_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('expenses', 'client_uuid')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique('expenses_client_uuid_unique');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('client_uuid');
        });
    }
};
