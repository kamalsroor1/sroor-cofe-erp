<?php

declare(strict_types=1);

use App\Support\Signup\SignupIdentity;
use App\Support\Signup\SignupNetwork;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Security review of SIGN-11..13 (central):
 *
 *  - tenants + signup_requests: `canonical_email` and `phone_normalized` (SignupIdentity), filled
 *    on every model save and backfilled here, indexed: the one-free-shop guard is a plain equality
 *    on them (gmail dots / +tags and phone spellings fold into one identity);
 *  - signup_requests.ip_network: client network of the signup (SignupNetwork), indexed: the
 *    per-network daily cap of automatic provisionings counts it;
 *  - signup_requests.terminal_at: when the row reached a final state (provisioned / approved /
 *    rejected / failed): the browser token dies 1 hour later, secrets are scrubbed after 30 days.
 *
 * The backfill uses the SAME canonicalisers as the runtime (on purpose: the stored value must
 * equal what the guard computes for a new signup).
 */
return new class extends Migration
{
    private const CHUNK = 500;

    public function up(): void
    {
        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table): void {
                if (! Schema::hasColumn('tenants', 'canonical_email')) {
                    $table->string('canonical_email', 255)->nullable()->index();
                }
                if (! Schema::hasColumn('tenants', 'phone_normalized')) {
                    $table->string('phone_normalized', 32)->nullable()->index();
                }
            });

            $this->backfill('tenants', false);
        }

        if (Schema::hasTable('signup_requests')) {
            Schema::table('signup_requests', function (Blueprint $table): void {
                if (! Schema::hasColumn('signup_requests', 'canonical_email')) {
                    $table->string('canonical_email', 255)->nullable()->index();
                }
                if (! Schema::hasColumn('signup_requests', 'phone_normalized')) {
                    $table->string('phone_normalized', 32)->nullable()->index();
                }
                if (! Schema::hasColumn('signup_requests', 'ip_network')) {
                    $table->string('ip_network', 64)->nullable()->index();
                }
                if (! Schema::hasColumn('signup_requests', 'terminal_at')) {
                    $table->timestamp('terminal_at')->nullable()->index();
                }
            });

            $this->backfill('signup_requests', true);
        }
    }

    public function down(): void
    {
        $this->dropColumns('signup_requests', ['canonical_email', 'phone_normalized', 'ip_network', 'terminal_at']);
        $this->dropColumns('tenants', ['canonical_email', 'phone_normalized']);
    }

    private function backfill(string $table, bool $isSignup): void
    {
        $columns = $isSignup
            ? ['id', 'email', 'phone', 'ip', 'status', 'provisioned_at', 'failed_at', 'reviewed_at', 'updated_at']
            : ['id', 'email', 'phone'];

        DB::table($table)->select($columns)->orderBy('id')->chunk(self::CHUNK, function ($rows) use ($table, $isSignup): void {
            foreach ($rows as $row) {
                $values = [
                    'canonical_email' => SignupIdentity::canonicalEmail($row->email),
                    'phone_normalized' => SignupIdentity::normalizedPhone($row->phone),
                ];

                if ($isSignup) {
                    $values['ip_network'] = SignupNetwork::of($row->ip);
                    // Literal statuses on purpose: a migration must not change when the enum does.
                    if (in_array($row->status, ['provisioned', 'approved', 'rejected', 'failed'], true)) {
                        $values['terminal_at'] = $row->provisioned_at ?? $row->failed_at ?? $row->reviewed_at ?? $row->updated_at;
                    }
                }

                DB::table($table)->where('id', $row->id)->update($values);
            }
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropColumns(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->dropIndex([$column]);
                $blueprint->dropColumn($column);
            });
        }
    }
};
