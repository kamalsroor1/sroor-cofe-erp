<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralAuditEvent;
use App\Enums\CentralPermission;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use Database\Seeders\CentralPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * IDEN-1.6: `central:create-super-admin {email} {--name=}`: password via secret() twice,
 * at least 12 characters, never printed; email stored lowercase; central-guard
 * `super_admin` role; audited `central_user_created`.
 */
final class CreateCentralSuperAdminCommandTest extends TenantTestCase
{
    private const COMMAND = 'central:create-super-admin';

    // Fake operator password; the `fixture` prefix marks it as fake for gitleaks (.gitleaks.toml).
    private const PASSWORD = 'fixtureNewSuperAdminPassword';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CentralPermissionsSeeder::class);
    }

    private function passwordQuestion(): string
    {
        return (string) __('console.create_super_admin.ask_password', ['min' => 12]);
    }

    private function confirmationQuestion(): string
    {
        return (string) __('console.create_super_admin.ask_password_confirmation');
    }

    public function test_it_creates_an_active_central_super_admin(): void
    {
        $this->artisan(self::COMMAND, ['email' => '  Owner@Platform.TEST ', '--name' => 'مالك المنصة'])
            ->expectsQuestion($this->passwordQuestion(), self::PASSWORD)
            ->expectsQuestion($this->confirmationQuestion(), self::PASSWORD)
            ->expectsOutputToContain('owner@platform.test')
            ->expectsOutputToContain(__('console.create_super_admin.two_factor_hint'))
            ->doesntExpectOutputToContain(self::PASSWORD)
            ->assertSuccessful();

        $user = CentralUser::query()->sole();
        $this->assertSame('owner@platform.test', $user->email);
        $this->assertSame('مالك المنصة', $user->name);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertTrue($user->hasRole(CentralPermission::ROLE_SUPER_ADMIN, CentralPermission::GUARD));
        $this->assertNull($user->two_factor_confirmed_at);

        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::CentralUserCreated->value)->sole();
        $this->assertSame((string) $user->getKey(), (string) $log->subject_id);
        $this->assertStringNotContainsString(self::PASSWORD, (string) json_encode($log->properties));
    }

    public function test_the_name_is_asked_when_omitted(): void
    {
        $this->artisan(self::COMMAND, ['email' => 'asked@platform.test'])
            ->expectsQuestion(__('console.create_super_admin.ask_name'), 'Asked Name')
            ->expectsQuestion($this->passwordQuestion(), self::PASSWORD)
            ->expectsQuestion($this->confirmationQuestion(), self::PASSWORD)
            ->assertSuccessful();

        $this->assertSame('Asked Name', CentralUser::query()->sole()->name);
    }

    public function test_a_password_shorter_than_12_characters_is_refused(): void
    {
        $this->artisan(self::COMMAND, ['email' => 'short@platform.test', '--name' => 'x'])
            ->expectsQuestion($this->passwordQuestion(), 'fixture1234')
            ->expectsOutputToContain(__('console.create_super_admin.password_too_short', ['min' => 12]))
            ->assertFailed();

        $this->assertSame(0, CentralUser::query()->count());
    }

    public function test_mismatched_passwords_are_refused(): void
    {
        $this->artisan(self::COMMAND, ['email' => 'mismatch@platform.test', '--name' => 'x'])
            ->expectsQuestion($this->passwordQuestion(), self::PASSWORD)
            ->expectsQuestion($this->confirmationQuestion(), self::PASSWORD.'-different')
            ->expectsOutputToContain(__('console.create_super_admin.password_mismatch'))
            ->assertFailed();

        $this->assertSame(0, CentralUser::query()->count());
    }

    public function test_an_invalid_email_is_refused(): void
    {
        $this->artisan(self::COMMAND, ['email' => 'not-an-email', '--name' => 'x'])
            ->expectsOutputToContain(__('console.create_super_admin.invalid_email'))
            ->assertFailed();

        $this->assertSame(0, CentralUser::query()->count());
    }

    public function test_an_existing_email_is_refused_case_insensitively(): void
    {
        CentralUser::factory()->create(['email' => 'taken@platform.test']);

        $this->artisan(self::COMMAND, ['email' => 'TAKEN@platform.test', '--name' => 'x'])
            ->expectsOutputToContain(__('console.create_super_admin.email_taken'))
            ->assertFailed();

        $this->assertSame(1, CentralUser::query()->count());
    }

    public function test_it_fails_without_writing_when_the_central_role_is_missing(): void
    {
        Role::query()->where('name', CentralPermission::ROLE_SUPER_ADMIN)->where('guard_name', CentralPermission::GUARD)->delete();

        $this->artisan(self::COMMAND, ['email' => 'norole@platform.test', '--name' => 'x'])
            ->expectsQuestion($this->passwordQuestion(), self::PASSWORD)
            ->expectsQuestion($this->confirmationQuestion(), self::PASSWORD)
            ->expectsOutputToContain(__('console.create_super_admin.central_role_missing'))
            ->assertFailed();

        $this->assertSame(0, CentralUser::query()->count());
        $this->assertSame(0, CentralAuditLog::query()->count());
    }
}
