<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IDEN-1.5 (CENTRAL DB): append-only audit trail of platform-operator actions
 * (logins, impersonation, tenant lifecycle, billing activation...).
 *
 * Column names mirror spatie/laravel-activitylog (causer/subject morphs, `properties`)
 * so App\Models\CentralAuditLog can later sit on top of that package without a data
 * migration. Deliberately NO foreign keys: an audit row must outlive the operator,
 * the tenant (string id) or the subject it describes. No `updated_at`: rows are never
 * updated.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('central_audit_logs')) {
            return;
        }

        Schema::create('central_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 64)->index();
            $table->string('description')->nullable();
            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('tenant_id')->nullable()->index();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['causer_type', 'causer_id']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_audit_logs');
    }
};
