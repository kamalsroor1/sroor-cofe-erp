<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRD-08: store legal consent metadata (acceptance timestamp and legal document version)
 * alongside the applicant IP on central `signup_requests`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('signup_requests')) {
            return;
        }

        Schema::table('signup_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('signup_requests', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable()->after('user_agent');
            }
            if (! Schema::hasColumn('signup_requests', 'terms_version')) {
                $table->string('terms_version', 40)->nullable()->after('terms_accepted_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('signup_requests')) {
            return;
        }

        Schema::table('signup_requests', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['terms_accepted_at', 'terms_version'],
                static fn (string $column): bool => Schema::hasColumn('signup_requests', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
