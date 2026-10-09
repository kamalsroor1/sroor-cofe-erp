<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IDEN-1.12 (CENTRAL DB): when the operator last proved a second factor ON THIS token.
 *
 * Stamped when a full token is issued after the 2FA challenge (or the 2FA confirmation)
 * and again by POST /api/v1/super-admin/auth/step-up. RequireRecentTwoFactor compares it
 * with central.step_up_ttl_minutes. Null = never verified on this token.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('central_personal_access_tokens')
            || Schema::hasColumn('central_personal_access_tokens', 'two_factor_verified_at')) {
            return;
        }

        Schema::table('central_personal_access_tokens', function (Blueprint $table) {
            $table->timestamp('two_factor_verified_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('central_personal_access_tokens')
            || ! Schema::hasColumn('central_personal_access_tokens', 'two_factor_verified_at')) {
            return;
        }

        Schema::table('central_personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn('two_factor_verified_at');
        });
    }
};
