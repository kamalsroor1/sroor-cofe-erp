<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\CentralAuditEvent;
use App\Exceptions\CentralAuditLogImmutableException;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * IDEN-1.5: append-only central audit log for platform operators.
 *
 *  - rows live in the CENTRAL `central_audit_logs` table, even while a tenant is initialized;
 *  - password / token / secret (and similar) keys are redacted recursively before storage;
 *  - rows can never be updated or deleted through Eloquent (model or builder);
 *  - every event has an ar + en label;
 *  - the migration rolls back and re-runs cleanly.
 */
#[Group('mysql')]
final class CentralAuditLoggerTest extends TenantTestCase
{
    private const MIGRATION = 'database/migrations/2026_10_10_100500_create_central_audit_logs_table.php';

    private function logger(): CentralAuditLogger
    {
        return $this->app->make(CentralAuditLogger::class);
    }

    private function fakeRequest(string $ip = '203.0.113.9', string $userAgent = 'SroorAdmin/1.0'): void
    {
        $this->app->instance('request', Request::create('/api/v1/super-admin/tenants', 'POST', server: [
            'REMOTE_ADDR' => $ip,
            'HTTP_USER_AGENT' => $userAgent,
        ]));
    }

    public function test_it_records_actor_subject_tenant_and_request_context(): void
    {
        $this->fakeRequest();
        $operator = CentralUser::factory()->create();
        $tenant = $this->createTenant();

        $log = $this->logger()->record(
            CentralAuditEvent::TenantSuspended,
            ['reason' => 'عدم السداد', 'previous_status' => 'active'],
            actor: $operator,
            subject: $tenant,
        );

        $row = CentralAuditLog::query()->findOrFail($log->getKey());

        $this->assertSame(CentralAuditEvent::TenantSuspended->value, $row->event);
        $this->assertSame(CentralAuditEvent::TenantSuspended, $row->eventEnum());
        $this->assertSame($operator->getMorphClass(), $row->causer_type);
        $this->assertSame((int) $operator->getKey(), (int) $row->causer_id);
        $this->assertSame($tenant->getMorphClass(), $row->subject_type);
        $this->assertSame((string) $tenant->getKey(), $row->subject_id);
        $this->assertSame((string) $tenant->getKey(), $row->tenant_id, 'A Tenant subject fills tenant_id automatically.');
        $this->assertSame(['reason' => 'عدم السداد', 'previous_status' => 'active'], $row->properties);
        $this->assertSame('203.0.113.9', $row->ip_address);
        $this->assertSame('SroorAdmin/1.0', $row->user_agent);
        $this->assertNotNull($row->created_at);
        $this->assertTrue($row->causer?->is($operator) ?? false);
    }

    public function test_it_records_events_without_an_actor_and_with_an_explicit_tenant(): void
    {
        $this->fakeRequest('198.51.100.7');

        $log = $this->logger()->record(
            CentralAuditEvent::LoginFailed,
            ['email' => 'unknown@central.test', 'password' => 'guessed-password'],
            tenantId: 'some-tenant',
        );

        $this->assertNull($log->causer_type);
        $this->assertNull($log->causer_id);
        $this->assertNull($log->subject_type);
        $this->assertSame('some-tenant', $log->tenant_id);
        $this->assertSame('198.51.100.7', $log->ip_address);
        $this->assertSame('unknown@central.test', $log->properties['email'] ?? null);
        $this->assertSame(CentralAuditLogger::REDACTED, $log->properties['password'] ?? null);
    }

    public function test_sensitive_keys_are_redacted_recursively_and_case_insensitively(): void
    {
        $log = $this->logger()->record(CentralAuditEvent::CentralUserUpdated, [
            'name' => 'مشغل الدعم',
            'Password' => 'plain-1',
            'password_confirmation' => 'plain-1',
            'current_password' => 'old',
            'api_token' => 'tok-123',
            'plainTextToken' => '1|abcdef',
            'two_factor_secret' => 'JBSWY3DP',
            'two_factor_recovery_codes' => ['a', 'b'],
            'Authorization' => 'Bearer xyz',
            'nested' => [
                'client_secret' => 's3cr3t',
                'tokens' => [['id' => 5, 'token' => 'hashed']],
                'safe' => 'kept',
            ],
            'changes' => ['email' => 'new@central.test'],
        ]);

        $stored = CentralAuditLog::query()->findOrFail($log->getKey())->properties;
        $raw = (string) DB::connection($this->centralConnectionName())
            ->table('central_audit_logs')->where('id', $log->getKey())->value('properties');

        $redacted = CentralAuditLogger::REDACTED;
        $this->assertSame('مشغل الدعم', $stored['name']);
        $this->assertSame($redacted, $stored['Password']);
        $this->assertSame($redacted, $stored['password_confirmation']);
        $this->assertSame($redacted, $stored['current_password']);
        $this->assertSame($redacted, $stored['api_token']);
        $this->assertSame($redacted, $stored['plainTextToken']);
        $this->assertSame($redacted, $stored['two_factor_secret']);
        $this->assertSame($redacted, $stored['two_factor_recovery_codes']);
        $this->assertSame($redacted, $stored['Authorization']);
        $this->assertSame($redacted, $stored['nested']['client_secret']);
        $this->assertSame($redacted, $stored['nested']['tokens'], 'A sensitive key hides its whole subtree.');
        $this->assertSame('kept', $stored['nested']['safe']);
        $this->assertSame(['email' => 'new@central.test'], $stored['changes']);

        foreach (['plain-1', 'tok-123', 'abcdef', 'JBSWY3DP', 'Bearer xyz', 's3cr3t', 'hashed'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw, "{$secret} must never reach the database.");
        }
    }

    public function test_user_agent_is_truncated_to_the_column_size(): void
    {
        $this->fakeRequest(userAgent: str_repeat('x', 2000));

        $log = $this->logger()->record(CentralAuditEvent::LoginSucceeded);

        $this->assertSame(CentralAuditLogger::USER_AGENT_MAX, mb_strlen((string) $log->user_agent));
    }

    public function test_rows_cannot_be_updated_through_the_model(): void
    {
        $log = $this->logger()->record(CentralAuditEvent::LoginSucceeded, ['note' => 'original']);

        $log->properties = ['note' => 'tampered'];

        try {
            $log->save();
            $this->fail('Updating an audit row must throw.');
        } catch (CentralAuditLogImmutableException) {
        }

        $this->assertSame(['note' => 'original'], CentralAuditLog::query()->findOrFail($log->getKey())->properties);
    }

    public function test_rows_cannot_be_deleted_through_the_model(): void
    {
        $log = $this->logger()->record(CentralAuditEvent::LoginSucceeded);

        $this->expectException(CentralAuditLogImmutableException::class);

        try {
            $log->delete();
        } finally {
            $this->assertTrue(CentralAuditLog::query()->whereKey($log->getKey())->exists());
        }
    }

    public function test_rows_cannot_be_mass_updated_or_deleted_through_the_builder(): void
    {
        $log = $this->logger()->record(CentralAuditEvent::LoginSucceeded, ['note' => 'original']);

        $attempts = [
            'update' => fn (): mixed => CentralAuditLog::query()->whereKey($log->getKey())->update(['event' => 'x']),
            'delete' => fn (): mixed => CentralAuditLog::query()->whereKey($log->getKey())->delete(),
            'forceDelete' => fn (): mixed => CentralAuditLog::query()->whereKey($log->getKey())->forceDelete(),
            'increment' => fn (): mixed => CentralAuditLog::query()->whereKey($log->getKey())->increment('causer_id'),
            'destroy' => fn (): mixed => CentralAuditLog::destroy($log->getKey()),
            'truncate' => function (): void {
                CentralAuditLog::query()->truncate();
            },
        ];

        foreach ($attempts as $name => $attempt) {
            try {
                $attempt();
                $this->fail("{$name} on the audit log must throw.");
            } catch (CentralAuditLogImmutableException) {
            }
        }

        $row = CentralAuditLog::query()->findOrFail($log->getKey());
        $this->assertSame(CentralAuditEvent::LoginSucceeded->value, $row->event);
        $this->assertSame(['note' => 'original'], $row->properties);
    }

    public function test_immutable_error_message_is_translated(): void
    {
        $exception = new CentralAuditLogImmutableException;

        $this->assertSame(__('central_audit.immutable'), $exception->getMessage());
        $this->assertNotSame('central_audit.immutable', $exception->getMessage());
    }

    public function test_connection_stays_central_while_a_tenant_is_initialized(): void
    {
        $central = $this->centralConnectionName();
        $operator = CentralUser::factory()->create();
        $tenant = $this->createTenant();

        $seen = $this->inTenant($tenant, function () use ($operator, $tenant): array {
            $log = $this->logger()->record(CentralAuditEvent::ImpersonationStarted, ['reason' => 'دعم فني'], $operator, $tenant);

            return [
                'default' => DB::getDefaultConnection(),
                'model' => $log->getConnectionName(),
                'id' => $log->getKey(),
                'tenant_has_table' => Schema::hasTable('central_audit_logs'),
            ];
        });

        $this->assertNotSame($central, $seen['default'], 'Tenancy must switch the default connection for this test to mean anything.');
        $this->assertSame($central, $seen['model']);
        $this->assertFalse($seen['tenant_has_table'], 'central_audit_logs must never exist in a tenant DB.');
        $this->assertTrue(
            DB::connection($central)->table('central_audit_logs')->where('id', $seen['id'])->where('tenant_id', $tenant->getKey())->exists()
        );
    }

    public function test_record_inside_a_tenant_transaction_waits_for_its_commit(): void
    {
        $tenant = $this->createTenant();

        $inside = $this->inTenant($tenant, function (): array {
            return DB::transaction(function (): array {
                $log = $this->logger()->record(CentralAuditEvent::TenantUpdated, description: 'tenant-commit');

                return [
                    'connection' => DB::getDefaultConnection(),
                    'exists' => $log->exists,
                    'rows' => $this->auditRows('tenant-commit'),
                ];
            });
        });

        $this->assertNotSame($this->centralConnectionName(), $inside['connection'], 'The transaction must be a tenant one for this test to mean anything.');
        $this->assertFalse($inside['exists'], 'Nothing may be written while the tenant transaction is open.');
        $this->assertSame(0, $inside['rows']);
        $this->assertSame(1, $this->auditRows('tenant-commit'), 'The row is written once the tenant transaction commits.');
    }

    public function test_record_is_dropped_when_the_tenant_transaction_rolls_back(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            try {
                DB::transaction(function (): void {
                    $this->logger()->record(CentralAuditEvent::TenantUpdated, description: 'tenant-rollback');

                    throw new \RuntimeException('tenant operation failed');
                });
            } catch (\RuntimeException) {
            }
        });

        $this->assertSame(0, $this->auditRows('tenant-rollback'), 'A change that never happened must not be audited as done.');
    }

    public function test_record_attempt_survives_a_tenant_rollback_and_is_written_immediately(): void
    {
        $tenant = $this->createTenant();

        $existsInside = $this->inTenant($tenant, function (): bool {
            $exists = null;

            try {
                DB::transaction(function () use (&$exists): void {
                    $exists = $this->logger()->recordAttempt(CentralAuditEvent::ImpersonationDestructiveUsed, description: 'tenant-attempt')->exists;

                    throw new \RuntimeException('tenant operation failed');
                });
            } catch (\RuntimeException) {
            }

            return (bool) $exists;
        });

        $this->assertTrue($existsInside, 'An attempt outside any central transaction is written at once.');
        $this->assertSame(1, $this->auditRows('tenant-attempt'));
    }

    public function test_record_rolls_back_with_the_central_change_it_describes(): void
    {
        $central = DB::connection($this->centralConnectionName());

        try {
            $central->transaction(function (): void {
                $this->logger()->record(CentralAuditEvent::PlanUpdated, description: 'central-rollback');

                throw new \RuntimeException('central change failed');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, $this->auditRows('central-rollback'));

        $central->transaction(fn () => $this->logger()->record(CentralAuditEvent::PlanUpdated, description: 'central-commit'));
        $this->assertSame(1, $this->auditRows('central-commit'));
    }

    public function test_record_attempt_survives_a_central_rollback_exactly_once(): void
    {
        $central = DB::connection($this->centralConnectionName());

        try {
            $central->transaction(function (): void {
                $log = $this->logger()->recordAttempt(CentralAuditEvent::LoginFailed, ['password' => 'guess'], description: 'central-attempt-rollback');
                $this->assertFalse($log->exists, 'Inside a central transaction the attempt waits for it to finish.');

                throw new \RuntimeException('login failed');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(1, $this->auditRows('central-attempt-rollback'), 'A rolled-back caller must not erase the attempt.');
        $stored = CentralAuditLog::query()->where('description', 'central-attempt-rollback')->firstOrFail();
        $this->assertSame(CentralAuditLogger::REDACTED, $stored->properties['password'] ?? null);

        $central->transaction(fn () => $this->logger()->recordAttempt(CentralAuditEvent::LoginFailed, description: 'central-attempt-commit'));
        $this->assertSame(1, $this->auditRows('central-attempt-commit'), 'A committed caller writes the attempt once, not twice.');
    }

    public function test_record_attempt_in_a_rolled_back_savepoint_is_kept_when_the_outer_transaction_commits(): void
    {
        $central = DB::connection($this->centralConnectionName());

        $central->transaction(function () use ($central): void {
            try {
                $central->transaction(function (): void {
                    $this->logger()->recordAttempt(CentralAuditEvent::LoginFailed, description: 'savepoint-attempt');

                    throw new \RuntimeException('inner step failed');
                });
            } catch (\RuntimeException) {
            }
        });

        $this->assertSame(1, $this->auditRows('savepoint-attempt'));

        try {
            $central->transaction(function () use ($central): void {
                $central->transaction(fn () => $this->logger()->recordAttempt(CentralAuditEvent::LoginFailed, description: 'outer-rollback-attempt'));

                throw new \RuntimeException('outer step failed');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(1, $this->auditRows('outer-rollback-attempt'));
    }

    public function test_record_inside_a_central_transaction_nested_in_a_tenant_transaction_waits_for_the_tenant(): void
    {
        $tenant = $this->createTenant();
        $central = DB::connection($this->centralConnectionName());

        $this->inTenant($tenant, function () use ($central): void {
            try {
                DB::transaction(function () use ($central): void {
                    $central->transaction(fn () => $this->logger()->record(CentralAuditEvent::TenantUpdated, description: 'nested-tenant-rollback'));

                    throw new \RuntimeException('tenant step failed after the central commit');
                });
            } catch (\RuntimeException) {
            }
        });

        $this->assertSame(0, $this->auditRows('nested-tenant-rollback'), 'The tenant change it describes never happened.');
    }

    private function auditRows(string $description): int
    {
        return DB::connection($this->centralConnectionName())->table('central_audit_logs')->where('description', $description)->count();
    }

    public function test_every_event_has_an_arabic_and_english_label(): void
    {
        foreach (CentralAuditEvent::cases() as $event) {
            $key = $event->translationKey();

            foreach (['ar', 'en'] as $locale) {
                $this->assertTrue(Lang::hasForLocale($key, $locale), "Missing {$locale} label for {$key}.");
                $this->assertNotSame($key, $event->label($locale));
            }
        }
    }

    public function test_migration_creates_expected_columns_and_rolls_back_cleanly(): void
    {
        $schema = Schema::connection($this->centralConnectionName());

        $this->assertTrue($schema->hasColumns('central_audit_logs', [
            'id', 'event', 'description', 'causer_type', 'causer_id', 'subject_type', 'subject_id',
            'tenant_id', 'properties', 'ip_address', 'user_agent', 'created_at',
        ]));
        $this->assertFalse($schema->hasColumn('central_audit_logs', 'updated_at'), 'Append-only rows have no updated_at.');

        $migration = require base_path(self::MIGRATION);

        $migration->down();
        $this->assertFalse($schema->hasTable('central_audit_logs'));

        $migration->up();
        $this->assertTrue($schema->hasTable('central_audit_logs'));
    }
}
