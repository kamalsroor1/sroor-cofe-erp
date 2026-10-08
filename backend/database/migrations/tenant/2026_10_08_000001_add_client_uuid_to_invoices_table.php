<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client-generated idempotency key for invoice creation (POS double-submit / retry safety).
 * Nullable so legacy rows stay NULL; a UNIQUE index allows multiple NULLs on MySQL and SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoices', 'client_uuid')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->after('invoice_number');
            $table->unique('client_uuid', 'invoices_client_uuid_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('invoices', 'client_uuid')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_client_uuid_unique');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('client_uuid');
        });
    }
};
