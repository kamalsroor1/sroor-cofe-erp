<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\DTOs\CreateTenantDTO;
use App\Jobs\ProvisionTenantJob;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\TenantProvisionerService;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * W2 batch 4 review (S-sec3): `tenancy_db_password` is stored ENCRYPTED in the central
 * `tenants.data` column and read decrypted (Tenant::tenancyDbPassword()), so stancl's
 * DatabaseConfig builds the tenant connection with the real password. Rows written before
 * (plaintext) keep working.
 */
final class TenantDatabasePasswordEncryptionTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const OPERATOR_DB_PHRASE = 'operator-db-secret-123';

    public function test_operator_credentials_given_at_creation_are_encrypted_at_rest(): void
    {
        $slug = 'qaenc'.Str::lower(Str::random(10));
        config([
            'queue.connections.qa_parked' => ['driver' => 'null'],
            'tenancy.provisioning.queue_connection' => 'qa_parked',
        ]);

        $tenant = app(TenantProvisionerService::class)->provision(CreateTenantDTO::fromArray([
            'name' => 'Encrypted '.$slug,
            'slug' => $slug,
            'email' => $slug.'@enc.test',
            'phone' => '01000009911',
            'plan_id' => $this->plan()->id,
            'password' => 'first-admin-pass-1',
            'trial_days' => 14,
            'tenancy_db_username' => 'op_user',
            'tenancy_db_password' => self::OPERATOR_DB_PHRASE,
        ]));
        (new UniqueLock(app(Repository::class)))->release(new ProvisionTenantJob($slug));

        $this->assertEncryptedAtRest($slug);
        $this->assertReadsDecrypted($slug);
        $this->assertSame('op_user', $tenant->database()->getUsername());
    }

    public function test_db_config_update_encrypts_the_password_and_the_connection_uses_the_plain_value(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();

        $this->postJson("/api/v1/super-admin/tenants/{$id}/update-db-config", [
            'tenancy_db_password' => self::OPERATOR_DB_PHRASE,
        ], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))->assertOk();

        $this->assertEncryptedAtRest($id);
        $this->assertReadsDecrypted($id);
    }

    public function test_a_legacy_plaintext_password_still_works(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();

        $data = json_decode((string) DB::table('tenants')->where('id', $id)->value('data'), true) ?: [];
        $data['tenancy_db_password'] = self::OPERATOR_DB_PHRASE; // written before S-sec3
        DB::table('tenants')->where('id', $id)->update(['data' => json_encode($data)]);

        $this->assertReadsDecrypted($id);

        // A save that does not touch the password keeps the legacy value as it is.
        $fresh = Tenant::query()->findOrFail($id);
        $fresh->setAttribute('qa_marker', 'x');
        $fresh->save();
        $this->assertReadsDecrypted($id);
    }

    public function test_null_and_empty_passwords_are_left_alone(): void
    {
        $this->assertNull(Tenant::sealDatabasePassword(null));
        $this->assertSame('', Tenant::sealDatabasePassword(''));
        $this->assertSame(self::OPERATOR_DB_PHRASE, Crypt::decryptString((string) Tenant::sealDatabasePassword(self::OPERATOR_DB_PHRASE)));
    }

    // ------------------------------------------------------------------ helpers

    private function assertEncryptedAtRest(string $id): void
    {
        $raw = (string) DB::table('tenants')->where('id', $id)->value('data');
        $this->assertStringNotContainsString(self::OPERATOR_DB_PHRASE, $raw, 'Never stored in plaintext.');

        $stored = json_decode($raw, true)['tenancy_db_password'] ?? null;
        $this->assertIsString($stored);
        $this->assertSame(self::OPERATOR_DB_PHRASE, Crypt::decryptString($stored));
    }

    private function assertReadsDecrypted(string $id): void
    {
        $tenant = Tenant::query()->findOrFail($id);

        $this->assertSame(self::OPERATOR_DB_PHRASE, $tenant->tenancy_db_password);
        $this->assertSame(self::OPERATOR_DB_PHRASE, $tenant->getInternal('db_password'));
        $this->assertSame(self::OPERATOR_DB_PHRASE, $tenant->database()->getPassword());
        // What stancl's DatabaseTenancyBootstrapper connects with.
        $this->assertSame(self::OPERATOR_DB_PHRASE, $tenant->database()->connection()['password'] ?? null);
    }

    private function plan(): Plan
    {
        return Plan::query()->create([
            'name' => 'Encryption plan',
            'slug' => 'enc-'.Str::lower(Str::random(6)),
            'price_monthly' => '100.000',
            'price_yearly' => '1000.000',
            'max_users' => 5,
            'max_stores' => 1,
            'max_items' => 100,
            'max_invoices_per_month' => 1000,
            'is_active' => true,
            'sort_order' => 1,
            'features' => [],
        ]);
    }
}
