<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SIGN-11 (central): self-signup with an emailed verification code lives in the same
 * `signup_requests` table as the assisted (PRD-05) requests, so the console keeps one list.
 *
 *  - signup_flow: `assisted` (old public form, operator approval) or `self_signup`;
 *  - status widened to 30 chars (`awaiting_verification`);
 *  - password_hash: the owner's chosen password, HASHED (never plain);
 *  - signup_token_hash: sha256 of the opaque token returned once to the browser;
 *  - verification_code_hash: keyed HMAC of the 6-digit code (never plain), its expiry,
 *    wrong attempts and last send time; email_verified_at once the code matched;
 *  - locale: language of the emails (ar / en).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('signup_requests')) {
            return;
        }

        Schema::table('signup_requests', function (Blueprint $table): void {
            $table->string('status', 30)->default('pending')->change();
        });

        Schema::table('signup_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('signup_requests', 'signup_flow')) {
                $table->string('signup_flow', 20)->default('assisted')->after('status');
            }
            if (! Schema::hasColumn('signup_requests', 'locale')) {
                $table->string('locale', 5)->nullable()->after('signup_flow');
            }
            if (! Schema::hasColumn('signup_requests', 'password_hash')) {
                $table->string('password_hash', 255)->nullable()->after('locale');
            }
            if (! Schema::hasColumn('signup_requests', 'signup_token_hash')) {
                $table->string('signup_token_hash', 64)->nullable()->unique()->after('password_hash');
            }
            if (! Schema::hasColumn('signup_requests', 'verification_code_hash')) {
                $table->string('verification_code_hash', 64)->nullable()->after('signup_token_hash');
            }
            if (! Schema::hasColumn('signup_requests', 'verification_code_expires_at')) {
                $table->timestamp('verification_code_expires_at')->nullable()->after('verification_code_hash');
            }
            if (! Schema::hasColumn('signup_requests', 'verification_attempts')) {
                $table->unsignedTinyInteger('verification_attempts')->default(0)->after('verification_code_expires_at');
            }
            if (! Schema::hasColumn('signup_requests', 'verification_sent_at')) {
                $table->timestamp('verification_sent_at')->nullable()->after('verification_attempts');
            }
            if (! Schema::hasColumn('signup_requests', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('verification_sent_at');
            }
        });

        Schema::table('signup_requests', function (Blueprint $table): void {
            $table->index(['signup_flow', 'status', 'created_at'], 'signup_requests_flow_status_created_index');
            $table->index('email', 'signup_requests_email_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('signup_requests')) {
            return;
        }

        Schema::table('signup_requests', function (Blueprint $table): void {
            $table->dropIndex('signup_requests_flow_status_created_index');
            $table->dropIndex('signup_requests_email_index');
        });

        Schema::table('signup_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('signup_requests', 'signup_token_hash')) {
                $table->dropUnique(['signup_token_hash']);
            }
        });

        Schema::table('signup_requests', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['signup_flow', 'locale', 'password_hash', 'signup_token_hash', 'verification_code_hash',
                    'verification_code_expires_at', 'verification_attempts', 'verification_sent_at', 'email_verified_at'],
                static fn (string $column): bool => Schema::hasColumn('signup_requests', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        // Self-signup rows have no meaning (nor a 20-char status) without these columns.
        DB::table('signup_requests')->whereRaw('LENGTH(status) > 20')->delete();

        Schema::table('signup_requests', function (Blueprint $table): void {
            $table->string('status', 20)->default('pending')->change();
        });
    }
};
