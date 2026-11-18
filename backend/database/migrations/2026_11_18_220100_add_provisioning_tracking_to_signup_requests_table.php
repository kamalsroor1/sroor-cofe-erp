<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SIGN-12 (central): automatic provisioning of verified self-signups, tracked on the same
 * `signup_requests` row (the console's signup log):
 *
 *  - provisioning_started_at: the tenant was registered (indexed: the daily cap counts it);
 *  - provisioned_at / failed_at + failure_code: outcome of the provisioning job;
 *  - ready_mail_sent_at / failure_mail_sent_at: each email is queued at most once.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $columns = [
        'provisioning_started_at', 'provisioned_at', 'failed_at', 'failure_code', 'ready_mail_sent_at', 'failure_mail_sent_at',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('signup_requests')) {
            return;
        }

        Schema::table('signup_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('signup_requests', 'provisioning_started_at')) {
                $table->timestamp('provisioning_started_at')->nullable()->after('email_verified_at')->index();
            }
            if (! Schema::hasColumn('signup_requests', 'provisioned_at')) {
                $table->timestamp('provisioned_at')->nullable()->after('provisioning_started_at');
            }
            if (! Schema::hasColumn('signup_requests', 'failed_at')) {
                $table->timestamp('failed_at')->nullable()->after('provisioned_at');
            }
            if (! Schema::hasColumn('signup_requests', 'failure_code')) {
                $table->string('failure_code', 40)->nullable()->after('failed_at');
            }
            if (! Schema::hasColumn('signup_requests', 'ready_mail_sent_at')) {
                $table->timestamp('ready_mail_sent_at')->nullable()->after('failure_code');
            }
            if (! Schema::hasColumn('signup_requests', 'failure_mail_sent_at')) {
                $table->timestamp('failure_mail_sent_at')->nullable()->after('ready_mail_sent_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('signup_requests')) {
            return;
        }

        if (Schema::hasColumn('signup_requests', 'provisioning_started_at')) {
            Schema::table('signup_requests', function (Blueprint $table): void {
                $table->dropIndex(['provisioning_started_at']);
            });
        }

        Schema::table('signup_requests', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                $this->columns,
                static fn (string $column): bool => Schema::hasColumn('signup_requests', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
