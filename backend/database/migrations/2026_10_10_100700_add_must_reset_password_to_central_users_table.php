<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W2-B3 security (CENTRAL DB): an operator created by `central:migrate-super-admins` keeps
 * the legacy password hash verbatim, so it must set a new password before its first
 * sign-in. LoginCentralUserAction refuses such an account (central_auth.password_reset_required)
 * until ResetCentralPasswordAction clears the flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('central_users') || Schema::hasColumn('central_users', 'must_reset_password')) {
            return;
        }

        Schema::table('central_users', function (Blueprint $table) {
            $table->boolean('must_reset_password')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('central_users') || ! Schema::hasColumn('central_users', 'must_reset_password')) {
            return;
        }

        Schema::table('central_users', function (Blueprint $table) {
            $table->dropColumn('must_reset_password');
        });
    }
};
