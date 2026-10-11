<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-5 (CENTRAL DB): ledger of every database backup archive uploaded by
 * `php artisan backup:tenants` (App\Models\TenantBackup).
 *
 * - `tenant_id` NULL = the central database itself.
 * - No foreign key to `tenants`: the history of a tenant's backups must survive the
 *   tenant row (OPS-9 archive / purge relies on it).
 * - `verified_at` is set once the uploaded archive was read back from the disk and its
 *   sha256 matched; the health check (OPS-7) and the purge guard (OPS-9) only trust
 *   verified rows.
 * - The row is deleted when retention removes the archive from the disk, so the ledger
 *   lists what really exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_backups')) {
            return;
        }

        Schema::create('tenant_backups', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->string('disk', 64);
            $table->string('path', 512);
            $table->char('sha256', 64);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->index(['tenant_id', 'verified_at']);
            $table->index(['disk', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_backups');
    }
};
