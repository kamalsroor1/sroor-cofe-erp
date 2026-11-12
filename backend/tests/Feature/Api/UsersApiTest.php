<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

class UsersApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    /** @var array<string, string> */
    protected array $adminHeaders;

    protected User $unauthorizedUser;

    /** @var array<string, string> */
    protected array $unauthorizedHeaders;

    protected int $storeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
        $this->adminUser = $this->tenantAdmin($this->tenant);
        $this->adminHeaders = $this->tenantHeaders($this->tenant);

        $this->unauthorizedUser = $this->createTenantUser($this->tenant, attributes: ['name' => 'مستخدم بدون صلاحيات']);
        $this->unauthorizedHeaders = $this->tenantHeaders($this->tenant, $this->unauthorizedUser);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/users', $this->tenantGuestHeaders($this->tenant));
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_users_management(): void
    {
        $response = $this->getJson('/api/v1/users', $this->unauthorizedHeaders);

        $response->assertStatus(403);
    }

    public function test_can_list_users_with_roles_and_stores(): void
    {
        $response = $this->getJson('/api/v1/users', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'phone', 'email', 'is_active', 'roles', 'primary_role', 'has_pos_pin'],
                ],
                'roles',
                'stores',
                'pagination',
            ]);
    }

    public function test_can_show_user_profile(): void
    {
        $targetUser = $this->createTenantUser($this->tenant, 'cashier', attributes: [
            'name' => 'أحمد كاشير',
            'phone' => '01000007006',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->getJson("/api/v1/users/{$targetUser->id}", $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $targetUser->id,
                    'name' => 'أحمد كاشير',
                    'phone' => '01000007006',
                ],
            ]);
    }

    public function test_can_create_new_user(): void
    {
        $payload = [
            'name' => 'محمود الكاشير',
            'phone' => '01000007002',
            'email' => 'cashier@sroor.com',
            'password' => 'secret123',
            'role' => 'cashier',
            'default_store_id' => $this->storeId,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/v1/users', $payload, $this->adminHeaders);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'محمود الكاشير',
                    'phone' => '01000007002',
                ],
            ]);

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseHas('users', [
            'phone' => '01000007002',
            'name' => 'محمود الكاشير',
        ]));
    }

    public function test_create_user_fails_validation_on_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/users', [
            'name' => 'اسم بدون هاتف وبدون كلمة مرور',
        ], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone', 'password', 'role']);
    }

    public function test_can_update_user(): void
    {
        $targetUser = $this->createTenantUser($this->tenant, 'cashier', attributes: [
            'name' => 'موظف سابق',
            'phone' => '01000007007',
            'password' => Hash::make('oldpass'),
        ]);

        $payload = [
            'name' => 'موظف معدل',
            'phone' => '01000007007',
            'email' => 'updated@sroor.com',
            'role' => 'cashier',
            'default_store_id' => $this->storeId,
            'is_active' => true,
        ];

        $response = $this->putJson("/api/v1/users/{$targetUser->id}", $payload, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'موظف معدل',
                ],
            ]);

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'name' => 'موظف معدل',
        ]));
    }

    public function test_can_toggle_user_active_state(): void
    {
        $targetUser = $this->createTenantUser($this->tenant, 'cashier', attributes: [
            'phone' => '01000007004',
            'is_active' => true,
        ]);

        $response = $this->patchJson("/api/v1/users/{$targetUser->id}/toggle-active", [], $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_active' => false,
            ]);

        $this->assertFalse($this->inTenant($this->tenant, fn (): bool => (bool) User::findOrFail($targetUser->id)->is_active));
    }

    public function test_can_delete_user_and_prevent_self_deletion(): void
    {
        $targetUser = $this->createTenantUser($this->tenant, 'cashier', attributes: [
            'phone' => '01000007009',
        ]);

        // Delete another user
        $response = $this->deleteJson("/api/v1/users/{$targetUser->id}", [], $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->inTenant($this->tenant, fn () => $this->assertSoftDeleted('users', ['id' => $targetUser->id]));

        // Attempt self-deletion
        $selfResponse = $this->deleteJson("/api/v1/users/{$this->adminUser->id}", [], $this->adminHeaders);

        $selfResponse->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_users_of_another_tenant_are_invisible_and_immutable(): void
    {
        $other = $this->createTenant();
        $foreign = $this->createTenantUser($other, 'cashier', attributes: [
            'name' => 'كاشير مستأجر آخر',
            'phone' => '01000007010',
        ]);

        $list = $this->getJson('/api/v1/users', $this->adminHeaders)->assertOk();
        $list->assertJsonMissing(['name' => 'كاشير مستأجر آخر']);
        $this->assertNotContains($foreign->id, array_column((array) $list->json('data'), 'id'));

        $this->getJson("/api/v1/users/{$foreign->id}", $this->adminHeaders)->assertNotFound();
        $this->putJson("/api/v1/users/{$foreign->id}", [
            'name' => 'اختراق',
            'phone' => '01000007010',
            'role' => 'admin',
            'default_store_id' => $this->storeId,
            'is_active' => true,
        ], $this->adminHeaders)->assertNotFound();
        // Not found in this tenant's DB. (Today toggle/destroy catch every Throwable and answer 422, not 404.)
        $toggle = $this->patchJson("/api/v1/users/{$foreign->id}/toggle-active", [], $this->adminHeaders);
        $this->assertContains($toggle->status(), [404, 422]);
        $toggle->assertJsonPath('success', false);
        $delete = $this->deleteJson("/api/v1/users/{$foreign->id}", [], $this->adminHeaders);
        $this->assertContains($delete->status(), [404, 422]);
        $delete->assertJsonPath('success', false);

        $this->inTenant($other, function () use ($foreign): void {
            $user = User::findOrFail($foreign->id);
            $this->assertSame('كاشير مستأجر آخر', $user->name);
            $this->assertTrue((bool) $user->is_active);
            $this->assertTrue($user->hasRole('cashier'));
            $this->assertFalse($user->hasRole('admin'));
        });
    }

    // ------------------------------------------------------------------ SEC-1 L12 (W2 batch 6)

    /** @return array<string, mixed> */
    private function updatePayload(User $target, string $role, array $overrides = []): array
    {
        return array_merge([
            'name' => $target->name,
            'phone' => $target->phone,
            'email' => $target->email,
            'role' => $role,
            'default_store_id' => $target->default_store_id ?? $this->storeId,
            'is_active' => (bool) $target->is_active,
        ], $overrides);
    }

    public function test_a_non_admin_role_manager_cannot_grant_the_admin_role(): void
    {
        $manager = $this->createTenantUser($this->tenant, 'accountant', ['roles.manage']);
        $headers = $this->tenantHeaders($this->tenant, $manager);
        $cashier = $this->createTenantUser($this->tenant, 'cashier');

        $this->putJson("/api/v1/users/{$cashier->id}", $this->updatePayload($cashier, 'admin'), $headers)->assertStatus(403);
        $this->assertFalse($this->inTenant($this->tenant, fn (): bool => User::findOrFail($cashier->id)->hasRole('admin')));

        $this->postJson('/api/v1/users', [
            'name' => 'مدير مزيف',
            'phone' => '01000007301',
            'password' => 'secret123',
            'role' => 'admin',
            'default_store_id' => $this->storeId,
        ], $headers)->assertStatus(403);
        $this->assertFalse($this->inTenant($this->tenant, fn (): bool => User::query()->where('phone', '01000007301')->exists()));

        // Ordinary edits of a non-admin account still work, provided the manager holds every
        // permission of the target role (SEC-04: no granting what you do not hold) and of the
        // user's current role (SEC-20: no stripping a role you could not have granted).
        $this->inTenant($this->tenant, function () use ($manager): void {
            $manager->givePermissionTo(Role::findByName('storekeeper')->permissions->pluck('name')->all());
            $manager->givePermissionTo(Role::findByName('cashier')->permissions->pluck('name')->all());
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
        $this->putJson("/api/v1/users/{$cashier->id}", $this->updatePayload($cashier, 'storekeeper', ['name' => 'أمين مخزن']), $headers)
            ->assertOk();
        $this->assertTrue($this->inTenant($this->tenant, fn (): bool => User::findOrFail($cashier->id)->hasRole('storekeeper')));
    }

    public function test_a_non_admin_role_manager_cannot_touch_an_admin_account(): void
    {
        $manager = $this->createTenantUser($this->tenant, 'accountant', ['roles.manage']);
        $headers = $this->tenantHeaders($this->tenant, $manager);
        $admin = $this->adminUser;
        $passwordHash = $this->inTenant($this->tenant, fn (): string => (string) User::findOrFail($admin->id)->password);

        $this->putJson("/api/v1/users/{$admin->id}", $this->updatePayload($admin, 'admin', ['password' => 'hijacked1']), $headers)->assertStatus(403);
        $this->putJson("/api/v1/users/{$admin->id}", $this->updatePayload($admin, 'admin', ['is_active' => false]), $headers)->assertStatus(403);
        $this->putJson("/api/v1/users/{$admin->id}", $this->updatePayload($admin, 'cashier'), $headers)->assertStatus(403);
        $this->patchJson("/api/v1/users/{$admin->id}/toggle-active", [], $headers)->assertStatus(403);
        $this->deleteJson("/api/v1/users/{$admin->id}", [], $headers)->assertStatus(403);

        $this->inTenant($this->tenant, function () use ($admin, $passwordHash): void {
            $fresh = User::findOrFail($admin->id);
            $this->assertSame($passwordHash, (string) $fresh->password);
            $this->assertTrue((bool) $fresh->is_active);
            $this->assertTrue($fresh->hasRole('admin'));
            $this->assertNull($fresh->deleted_at);
        });
    }

    public function test_a_non_admin_role_manager_cannot_change_their_own_role(): void
    {
        $manager = $this->createTenantUser($this->tenant, 'accountant', ['roles.manage']);

        $this->putJson("/api/v1/users/{$manager->id}", $this->updatePayload($manager, 'storekeeper'), $this->tenantHeaders($this->tenant, $manager))
            ->assertStatus(403);

        $this->assertTrue($this->inTenant($this->tenant, fn (): bool => User::findOrFail($manager->id)->hasRole('accountant')));
    }

    public function test_the_admin_may_still_grant_the_admin_role(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');

        $this->putJson("/api/v1/users/{$cashier->id}", $this->updatePayload($cashier, 'admin'), $this->adminHeaders)->assertOk();

        $this->assertTrue($this->inTenant($this->tenant, fn (): bool => User::findOrFail($cashier->id)->hasRole('admin')));
    }

    public function test_a_non_admin_role_manager_cannot_change_an_admin_name_phone_or_email(): void
    {
        $manager = $this->createTenantUser($this->tenant, 'accountant', ['roles.manage']);
        $headers = $this->tenantHeaders($this->tenant, $manager);
        $admin = $this->adminUser;

        foreach ([
            ['name' => 'اسم مُختطف'],
            ['phone' => '01000007999'],
            ['email' => 'hijacked@example.test'],
            ['email' => null],
        ] as $change) {
            $this->putJson("/api/v1/users/{$admin->id}", $this->updatePayload($admin, 'admin', $change), $headers)
                ->assertStatus(403)
                ->assertJsonPath('message', __('auth.cannot_modify_admin_identity'));
        }

        $this->inTenant($this->tenant, function () use ($admin): void {
            $fresh = User::findOrFail($admin->id);
            $this->assertSame($admin->name, $fresh->name);
            $this->assertSame($admin->phone, $fresh->phone);
            $this->assertSame($admin->email, $fresh->email);
            $this->assertTrue($fresh->hasRole('admin'));
        });

        // Resubmitting the admin's unchanged identity is not an identity change.
        $this->putJson("/api/v1/users/{$admin->id}", $this->updatePayload($admin, 'admin'), $headers)->assertOk();
    }

    public function test_a_non_admin_role_manager_cannot_change_an_admin_default_store(): void
    {
        $manager = $this->createTenantUser($this->tenant, 'accountant', ['roles.manage']);
        $admin = $this->adminUser;
        $otherStoreId = $this->inTenant($this->tenant, fn (): int => (int) Store::query()->create(['name' => 'فرع آخر', 'code' => 'BR-ADM', 'is_active' => true])->id);
        $before = $this->inTenant($this->tenant, fn () => User::findOrFail($admin->id)->default_store_id);

        $this->putJson("/api/v1/users/{$admin->id}", $this->updatePayload($admin, 'admin', ['default_store_id' => $otherStoreId]), $this->tenantHeaders($this->tenant, $manager))
            ->assertStatus(403)
            ->assertJsonPath('message', __('auth.cannot_modify_admin_identity'));

        $this->assertSame($before, $this->inTenant($this->tenant, fn () => User::findOrFail($admin->id)->default_store_id));
    }

    public function test_an_admin_may_still_edit_another_admin_identity(): void
    {
        $otherAdmin = $this->createTenantUser($this->tenant, 'admin');

        $this->putJson("/api/v1/users/{$otherAdmin->id}", $this->updatePayload($otherAdmin, 'admin', [
            'name' => 'مدير الفرع الثاني',
            'phone' => '01000007888',
            'email' => 'branch2-admin@example.test',
        ]), $this->adminHeaders)->assertOk();

        $this->inTenant($this->tenant, function () use ($otherAdmin): void {
            $fresh = User::findOrFail($otherAdmin->id);
            $this->assertSame('مدير الفرع الثاني', $fresh->name);
            $this->assertSame('01000007888', $fresh->phone);
            $this->assertSame('branch2-admin@example.test', $fresh->email);
        });
    }

    public function test_users_index_and_profile_return_has_pos_pin_boolean_and_hide_pin_hash(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier', attributes: [
            'name' => 'كاشير موافقة',
            'phone' => '01000009988',
        ]);

        // Initially no PIN
        $indexResp = $this->getJson('/api/v1/users', $this->adminHeaders)->assertOk();
        $row = collect($indexResp->json('data'))->firstWhere('id', $cashier->id);
        $this->assertNotNull($row);
        $this->assertFalse($row['has_pos_pin']);
        $this->assertArrayNotHasKey('pos_pin', $row);

        // Set PIN via admin endpoint
        $this->putJson("/api/v1/users/{$cashier->id}/pos-pin", ['pin' => '543210'], $this->adminHeaders)
            ->assertOk()
            ->assertJson(['success' => true, 'has_pos_pin' => true]);

        // Index now reports has_pos_pin = true
        $indexResp2 = $this->getJson('/api/v1/users', $this->adminHeaders)->assertOk();
        $row2 = collect($indexResp2->json('data'))->firstWhere('id', $cashier->id);
        $this->assertTrue($row2['has_pos_pin']);
        $this->assertArrayNotHasKey('pos_pin', $row2);

        // Profile endpoint also returns has_pos_pin = true for that user
        $cashierHeaders = $this->tenantHeaders($this->tenant, $cashier);
        $profileResp = $this->getJson('/api/v1/profile', $cashierHeaders)->assertOk();
        $this->assertTrue($profileResp->json('data.has_pos_pin'));
        $this->assertArrayNotHasKey('pos_pin', $profileResp->json('data'));

        // Clear PIN via profile endpoint
        $this->putJson('/api/v1/profile/pos-pin', [
            'pin' => null,
            'current_password' => 'password',
        ], $cashierHeaders)
            ->assertOk()
            ->assertJson(['success' => true, 'has_pos_pin' => false]);

        $profileResp2 = $this->getJson('/api/v1/profile', $cashierHeaders)->assertOk();
        $this->assertFalse($profileResp2->json('data.has_pos_pin'));
    }

    public function test_pos_pin_endpoints_require_authentication(): void
    {
        $guest = $this->tenantGuestHeaders($this->tenant);

        $this->putJson("/api/v1/users/{$this->unauthorizedUser->id}/pos-pin", ['pin' => '123456'], $guest)->assertStatus(401);
        $this->putJson('/api/v1/profile/pos-pin', ['pin' => '123456', 'current_password' => 'password'], $guest)->assertStatus(401);
    }

    public function test_user_pos_pin_endpoint_is_forbidden_without_user_management_rights_and_validates_the_pin(): void
    {
        $target = $this->createTenantUser($this->tenant, 'cashier', attributes: ['name' => 'كاشير PIN']);

        $this->putJson("/api/v1/users/{$target->id}/pos-pin", ['pin' => '123456'], $this->unauthorizedHeaders)->assertStatus(403);

        // SEC-21: a new PIN is exactly 6 digits, so 4- and 5-digit PINs are refused too.
        foreach (['12', '1234', '12345', '1234567', '12ab56', 123456] as $bad) {
            $this->putJson("/api/v1/users/{$target->id}/pos-pin", ['pin' => $bad], $this->adminHeaders)
                ->assertStatus(422)
                ->assertJsonValidationErrors('pin');
        }
        $this->putJson("/api/v1/users/{$target->id}/pos-pin", [], $this->adminHeaders)->assertStatus(422)->assertJsonValidationErrors('pin');

        $this->assertNull($this->inTenant($this->tenant, fn () => User::query()->findOrFail($target->id)->pos_pin));

        $this->putJson("/api/v1/users/{$target->id}/pos-pin", ['pin' => '567890'], $this->adminHeaders)
            ->assertOk()
            ->assertJson(['success' => true, 'has_pos_pin' => true])
            ->assertJsonMissingPath('pos_pin');
        $this->assertTrue(Hash::check('567890', (string) $this->inTenant($this->tenant, fn () => User::query()->findOrFail($target->id)->pos_pin)));
    }

    /**
     * Security review of SEC-21 (5): PUT /profile/pos-pin checked current_password with a bare
     * Hash::check in the FormRequest, limited only by throttle:10,1, outside the account lockout:
     * 10 password guesses a minute, forever, from any left-open session. Now it shares the
     * CurrentPasswordVerifier budget: 5 wrong answers lock, the 6th (right) one is refused, the
     * PIN is unchanged, and the same lock covers the other password checks.
     */
    public function test_profile_pos_pin_password_check_shares_the_account_lockout(): void
    {
        $user = $this->createTenantUser($this->tenant, 'cashier');
        $headers = $this->tenantHeaders($this->tenant, $user);

        foreach (range(1, 5) as $i) {
            $this->putJson('/api/v1/profile/pos-pin', ['pin' => '135791', 'current_password' => 'wrong-'.$i], $headers)
                ->assertStatus(422)
                ->assertJsonPath('errors.current_password.0', __('auth.current_password_incorrect'));
        }

        $this->putJson('/api/v1/profile/pos-pin', ['pin' => '135791', 'current_password' => 'password'], $headers)
            ->assertStatus(422)
            ->assertJsonPath('errors.current_password.0', __('auth.account_locked', ['minutes' => 15]));
        $this->assertNull($this->inTenant($this->tenant, fn () => User::query()->findOrFail($user->id)->pos_pin));

        // One budget: the password change is locked as well.
        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'password', 'password' => 'brandNew#2026', 'password_confirmation' => 'brandNew#2026',
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('current_password');

        // After the lock window the right password works again.
        $this->travel(16)->minutes();
        $this->putJson('/api/v1/profile/pos-pin', ['pin' => '135791', 'current_password' => 'password'], $this->tenantHeaders($this->tenant, $user))->assertOk();
    }

    public function test_profile_pos_pin_endpoint_validates_the_pin_and_the_current_password(): void
    {
        $headers = $this->unauthorizedHeaders;

        $this->putJson('/api/v1/profile/pos-pin', ['pin' => '12', 'current_password' => 'password'], $headers)
            ->assertStatus(422)->assertJsonValidationErrors('pin');
        $this->putJson('/api/v1/profile/pos-pin', ['pin' => '123456'], $headers)
            ->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->putJson('/api/v1/profile/pos-pin', ['pin' => '123456', 'current_password' => 'not-the-password'], $headers)
            ->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->assertNull($this->inTenant($this->tenant, fn () => User::query()->findOrFail($this->unauthorizedUser->id)->pos_pin));

        $this->putJson('/api/v1/profile/pos-pin', ['pin' => '123456', 'current_password' => 'password'], $headers)
            ->assertOk()
            ->assertJson(['success' => true, 'has_pos_pin' => true]);
        $this->assertTrue(Hash::check('123456', (string) $this->inTenant($this->tenant, fn () => User::query()->findOrFail($this->unauthorizedUser->id)->pos_pin)));
    }

    // ------------------------------------------------------------------ SEC-20

    /** @param  list<string>  $permissions */
    private function makeRole(string $name, array $permissions): void
    {
        $this->inTenant($this->tenant, function () use ($name, $permissions): void {
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }
            Role::findOrCreate($name, 'web')->syncPermissions($permissions);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    /** A non-admin delegate whose only permission is roles.manage (plus any extra given). */
    private function makeDelegate(array $extraPermissions = []): User
    {
        $this->makeRole('sec20_user_manager', ['roles.manage']);

        return $this->createTenantUser($this->tenant, 'sec20_user_manager', $extraPermissions);
    }

    /** A user whose role grants billing.manage, which the bare delegate does not hold. */
    private function makeBillingClerk(): User
    {
        $this->makeRole('sec20_billing_clerk', ['billing.manage']);

        return $this->createTenantUser($this->tenant, 'sec20_billing_clerk', attributes: ['password' => Hash::make('password')]);
    }

    /** @return array{password: string, pos_pin: string|null, phone: string|null, email: string|null, tokens: int} */
    private function credentialSnapshot(int $userId): array
    {
        return $this->inTenant($this->tenant, function () use ($userId): array {
            $user = User::query()->findOrFail($userId);

            return [
                'password' => (string) $user->password,
                'pos_pin' => $user->pos_pin,
                'phone' => $user->phone,
                'email' => $user->email,
                'tokens' => $user->tokens()->count(),
            ];
        });
    }

    public function test_a_delegate_cannot_change_credentials_or_pin_of_a_user_with_permissions_they_lack(): void
    {
        $delegate = $this->makeDelegate();
        $headers = $this->tenantHeaders($this->tenant, $delegate);
        $victim = $this->makeBillingClerk();
        $victimHeaders = $this->tenantHeaders($this->tenant, $victim);
        $before = $this->credentialSnapshot($victim->id);
        $message = __('users.cannot_change_credentials_of_more_privileged_user');

        foreach ([
            ['password' => 'hijacked12'],
            ['phone' => '01000007555'],
            ['email' => 'hijacked-clerk@example.test'],
        ] as $change) {
            $this->putJson("/api/v1/users/{$victim->id}", $this->updatePayload($victim, 'sec20_billing_clerk', $change), $headers)
                ->assertStatus(403)
                ->assertJsonPath('success', false)
                ->assertJsonPath('message', $message);
        }

        $this->putJson("/api/v1/users/{$victim->id}/pos-pin", ['pin' => '482613'], $headers)
            ->assertStatus(403)
            ->assertJsonPath('message', $message);

        $this->assertSame($before, $this->credentialSnapshot($victim->id));
        $this->getJson('/api/v1/profile', $victimHeaders)->assertOk();
        $this->assertFalse($this->inTenant($this->tenant, fn (): bool => ActivityLog::query()
            ->where('subject_id', $victim->id)
            ->whereIn('action', ['password_reset', 'pos_pin_set', 'pos_pin_cleared'])
            ->exists()));

        // Editing what is not a credential (the name) of the same user is still allowed.
        $this->putJson("/api/v1/users/{$victim->id}", $this->updatePayload($victim, 'sec20_billing_clerk', ['name' => 'محاسب فواتير']), $headers)
            ->assertOk();
    }

    public function test_a_direct_permission_of_the_target_also_counts(): void
    {
        $delegate = $this->makeDelegate(['pos.access']);
        $this->makeRole('sec20_pos_helper', ['pos.access']);
        $target = $this->createTenantUser($this->tenant, 'sec20_pos_helper', ['pos.price.below_min']);

        $headers = $this->tenantHeaders($this->tenant, $delegate);
        $message = __('users.cannot_change_credentials_of_more_privileged_user');
        $this->putJson("/api/v1/users/{$target->id}", $this->updatePayload($target, 'sec20_pos_helper', ['password' => 'hijacked12']), $headers)
            ->assertStatus(403)
            ->assertJsonPath('message', $message);
        $this->putJson("/api/v1/users/{$target->id}/pos-pin", ['pin' => '482613'], $headers)
            ->assertStatus(403)
            ->assertJsonPath('message', $message);
    }

    public function test_a_delegate_cannot_clear_the_pin_of_a_more_privileged_user(): void
    {
        $delegate = $this->makeDelegate();
        $victim = $this->makeBillingClerk();
        $this->putJson("/api/v1/users/{$victim->id}/pos-pin", ['pin' => '739104'], $this->adminHeaders)->assertOk();

        $this->putJson("/api/v1/users/{$victim->id}/pos-pin", ['pin' => null], $this->tenantHeaders($this->tenant, $delegate))
            ->assertStatus(403)
            ->assertJsonPath('message', __('users.cannot_change_credentials_of_more_privileged_user'));

        $this->assertTrue(Hash::check('739104', (string) $this->credentialSnapshot($victim->id)['pos_pin']));
    }

    private function makeSecondStore(): int
    {
        return $this->inTenant($this->tenant, fn (): int => (int) Store::query()->create(['name' => 'فرع ب', 'code' => 'BR-SEC20', 'is_active' => true])->id);
    }

    private function attachStore(User $user, int $storeId): void
    {
        $this->inTenant($this->tenant, fn () => User::query()->findOrFail($user->id)->stores()->attach($storeId));
    }

    public function test_a_store_confined_delegate_cannot_take_over_a_user_of_another_store(): void
    {
        $storeB = $this->makeSecondStore();
        $this->makeRole('sec20_pos_helper', ['pos.access']);
        $delegate = $this->makeDelegate(['pos.access']);
        $headers = $this->tenantHeaders($this->tenant, $delegate);
        $message = __('users.cannot_change_credentials_of_more_privileged_user');

        // Same permissions, but the target works in store B (by default store, or by assignment).
        $byDefault = $this->createTenantUser($this->tenant, 'sec20_pos_helper', attributes: ['default_store_id' => $storeB]);
        $byAssignment = $this->createTenantUser($this->tenant, 'sec20_pos_helper');
        $this->attachStore($byAssignment, $storeB);

        foreach ([$byDefault, $byAssignment] as $target) {
            $before = $this->credentialSnapshot($target->id);
            $this->putJson("/api/v1/users/{$target->id}", $this->updatePayload($target, 'sec20_pos_helper', ['password' => 'hijacked12']), $headers)
                ->assertStatus(403)
                ->assertJsonPath('message', $message);
            $this->putJson("/api/v1/users/{$target->id}/pos-pin", ['pin' => '482613'], $headers)
                ->assertStatus(403)
                ->assertJsonPath('message', $message);
            $this->assertSame($before, $this->credentialSnapshot($target->id));
        }

        // Once the delegate may work in store B too, the same changes go through.
        $this->attachStore($delegate, $storeB);
        $this->putJson("/api/v1/users/{$byDefault->id}", $this->updatePayload($byDefault, 'sec20_pos_helper', ['password' => 'resetpass1']), $headers)
            ->assertOk();
        $this->putJson("/api/v1/users/{$byAssignment->id}/pos-pin", ['pin' => '482613'], $headers)->assertOk();
    }

    public function test_viewing_all_stores_is_not_enough_to_take_over_a_user_of_another_store(): void
    {
        $storeB = $this->makeSecondStore();
        $this->makeRole('sec20_pos_helper', ['pos.access']);
        $delegate = $this->makeDelegate(['pos.access', 'stores.view_all']);
        $target = $this->createTenantUser($this->tenant, 'sec20_pos_helper', attributes: ['default_store_id' => $storeB]);
        $headers = $this->tenantHeaders($this->tenant, $delegate);
        $message = __('users.cannot_change_credentials_of_more_privileged_user');

        $this->putJson("/api/v1/users/{$target->id}", $this->updatePayload($target, 'sec20_pos_helper', ['password' => 'resetpass1']), $headers)
            ->assertStatus(403)
            ->assertJsonPath('message', $message);
        $this->putJson("/api/v1/users/{$target->id}/pos-pin", ['pin' => '482613'], $headers)
            ->assertStatus(403)
            ->assertJsonPath('message', $message);
    }

    public function test_a_delegate_cannot_assign_a_default_store_they_cannot_access(): void
    {
        $storeB = $this->makeSecondStore();
        $mainStore = $this->storeId;
        $this->makeRole('sec20_pos_helper', ['pos.access']);
        $delegate = $this->makeDelegate(['pos.access']);
        $target = $this->createTenantUser($this->tenant, 'sec20_pos_helper');
        $headers = $this->tenantHeaders($this->tenant, $delegate);
        $message = __('users.cannot_assign_inaccessible_store');

        // On themselves.
        $this->putJson("/api/v1/users/{$delegate->id}", $this->updatePayload($delegate, 'sec20_user_manager', ['default_store_id' => $storeB]), $headers)
            ->assertStatus(403)
            ->assertJsonPath('message', $message);
        // On another user, even one the delegate may otherwise edit.
        $this->putJson("/api/v1/users/{$target->id}", $this->updatePayload($target, 'sec20_pos_helper', ['default_store_id' => $storeB]), $headers)
            ->assertStatus(403)
            ->assertJsonPath('message', $message);
        // On a new account.
        $this->postJson('/api/v1/users', [
            'name' => 'كاشير فرع ب',
            'phone' => '01000007560',
            'password' => 'secret123',
            'role' => 'sec20_pos_helper',
            'default_store_id' => $storeB,
        ], $headers)->assertStatus(403)->assertJsonPath('message', $message);

        $this->inTenant($this->tenant, function () use ($delegate, $target, $mainStore): void {
            $this->assertSame($mainStore, (int) User::query()->findOrFail($delegate->id)->default_store_id);
            $this->assertSame($mainStore, (int) User::query()->findOrFail($target->id)->default_store_id);
            $this->assertFalse(User::query()->where('phone', '01000007560')->exists());
        });

        // Resubmitting the current store is not a change; the admin may move anyone anywhere.
        $this->putJson("/api/v1/users/{$target->id}", $this->updatePayload($target, 'sec20_pos_helper', ['name' => 'كاشير معدل']), $headers)
            ->assertOk();
        $this->putJson("/api/v1/users/{$target->id}", $this->updatePayload($target, 'sec20_pos_helper', ['default_store_id' => $storeB]), $this->adminHeaders)
            ->assertOk();
        $this->assertSame($storeB, $this->inTenant($this->tenant, fn (): int => (int) User::query()->findOrFail($target->id)->default_store_id));
    }

    public function test_a_delegate_cannot_demote_a_more_privileged_user_and_then_reset_the_password(): void
    {
        $delegate = $this->makeDelegate();
        $headers = $this->tenantHeaders($this->tenant, $delegate);
        $victim = $this->makeBillingClerk();
        $this->makeRole('sec20_no_permissions', []);
        $before = $this->credentialSnapshot($victim->id);

        // Step 1: the weaker role holds nothing the delegate lacks, but the victim's current role does.
        $this->putJson("/api/v1/users/{$victim->id}", $this->updatePayload($victim, 'sec20_no_permissions'), $headers)
            ->assertStatus(403)
            ->assertJsonPath('message', __('users.cannot_change_credentials_of_more_privileged_user'));
        $this->assertTrue($this->inTenant($this->tenant, fn (): bool => User::query()->findOrFail($victim->id)->hasRole('sec20_billing_clerk')));

        // Step 2 therefore still meets the victim's full permissions.
        $this->putJson("/api/v1/users/{$victim->id}", $this->updatePayload($victim, 'sec20_billing_clerk', ['password' => 'hijacked12']), $headers)
            ->assertStatus(403);
        $this->assertSame($before, $this->credentialSnapshot($victim->id));
    }

    public function test_a_delegate_may_change_credentials_of_a_user_whose_permissions_they_hold(): void
    {
        $delegate = $this->makeDelegate(['pos.access']);
        $this->makeRole('sec20_pos_helper', ['pos.access']);
        $target = $this->createTenantUser($this->tenant, 'sec20_pos_helper');
        $headers = $this->tenantHeaders($this->tenant, $delegate);

        $this->putJson("/api/v1/users/{$target->id}", $this->updatePayload($target, 'sec20_pos_helper', [
            'phone' => '01000007556',
            'email' => 'pos-helper@example.test',
            'password' => 'resetpass1',
        ]), $headers)->assertOk();

        $this->putJson("/api/v1/users/{$target->id}/pos-pin", ['pin' => '482613'], $headers)
            ->assertOk()
            ->assertJson(['success' => true, 'has_pos_pin' => true]);

        $this->inTenant($this->tenant, function () use ($target): void {
            $fresh = User::query()->findOrFail($target->id);
            $this->assertSame('01000007556', $fresh->phone);
            $this->assertSame('pos-helper@example.test', $fresh->email);
            $this->assertTrue(Hash::check('resetpass1', (string) $fresh->password));
            $this->assertTrue(Hash::check('482613', (string) $fresh->pos_pin));
        });
    }

    public function test_an_admin_may_change_credentials_and_pin_of_any_user(): void
    {
        $victim = $this->makeBillingClerk();

        $this->putJson("/api/v1/users/{$victim->id}", $this->updatePayload($victim, 'sec20_billing_clerk', [
            'phone' => '01000007557',
            'email' => 'clerk-by-admin@example.test',
            'password' => 'resetpass1',
        ]), $this->adminHeaders)->assertOk();
        $this->putJson("/api/v1/users/{$victim->id}/pos-pin", ['pin' => '739104'], $this->adminHeaders)->assertOk();

        $this->inTenant($this->tenant, function () use ($victim): void {
            $fresh = User::query()->findOrFail($victim->id);
            $this->assertSame('01000007557', $fresh->phone);
            $this->assertTrue(Hash::check('resetpass1', (string) $fresh->password));
            $this->assertTrue(Hash::check('739104', (string) $fresh->pos_pin));
        });
    }

    public function test_a_delegate_may_change_their_own_credentials_and_pin(): void
    {
        $delegate = $this->makeDelegate();
        $headers = $this->tenantHeaders($this->tenant, $delegate);

        $this->putJson("/api/v1/users/{$delegate->id}", $this->updatePayload($delegate, 'sec20_user_manager', [
            'phone' => '01000007558',
            'email' => 'delegate-self@example.test',
            'password' => 'resetpass1',
        ]), $headers)->assertOk();

        $selfHeaders = $this->tenantHeaders($this->tenant, $delegate);
        $this->putJson("/api/v1/users/{$delegate->id}/pos-pin", ['pin' => '246802'], $selfHeaders)->assertOk();

        $this->inTenant($this->tenant, function () use ($delegate): void {
            $fresh = User::query()->findOrFail($delegate->id);
            $this->assertSame('01000007558', $fresh->phone);
            $this->assertTrue(Hash::check('resetpass1', (string) $fresh->password));
            $this->assertTrue(Hash::check('246802', (string) $fresh->pos_pin));
            // Own changes are not "another user's credentials": no audit row for them.
            $this->assertFalse(ActivityLog::query()->where('subject_id', $delegate->id)->whereIn('action', ['password_reset', 'pos_pin_set'])->exists());
        });
    }

    public function test_changing_another_users_password_revokes_their_tokens_and_is_logged_without_the_value(): void
    {
        $delegate = $this->makeDelegate(['pos.access']);
        $this->makeRole('sec20_pos_helper', ['pos.access']);
        $target = $this->createTenantUser($this->tenant, 'sec20_pos_helper');
        $targetHeaders = $this->tenantHeaders($this->tenant, $target);
        $this->getJson('/api/v1/profile', $targetHeaders)->assertOk();

        $this->putJson("/api/v1/users/{$target->id}", $this->updatePayload($target, 'sec20_pos_helper', ['password' => 'Sec20Secret9']), $this->tenantHeaders($this->tenant, $delegate))
            ->assertOk();

        $this->getJson('/api/v1/profile', $targetHeaders)->assertStatus(401);
        $this->assertSame(0, $this->credentialSnapshot($target->id)['tokens']);

        $log = $this->inTenant($this->tenant, fn (): ?ActivityLog => ActivityLog::query()
            ->where('module', 'users')->where('action', 'password_reset')
            ->where('subject_type', User::class)->where('subject_id', $target->id)->first());
        $this->assertNotNull($log);
        $this->assertSame($delegate->id, $log->user_id);
        $hash = $this->credentialSnapshot($target->id)['password'];
        $serialized = json_encode($log->getAttributes(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Sec20Secret9', (string) $serialized);
        $this->assertStringNotContainsString($hash, (string) $serialized);
    }

    public function test_setting_another_users_pin_is_logged_without_the_value_and_keeps_their_sessions(): void
    {
        $delegate = $this->makeDelegate(['pos.access']);
        $this->makeRole('sec20_pos_helper', ['pos.access']);
        $target = $this->createTenantUser($this->tenant, 'sec20_pos_helper');
        $targetHeaders = $this->tenantHeaders($this->tenant, $target);
        $headers = $this->tenantHeaders($this->tenant, $delegate);

        $this->putJson("/api/v1/users/{$target->id}/pos-pin", ['pin' => '583920'], $headers)->assertOk();
        $this->putJson("/api/v1/users/{$target->id}/pos-pin", ['pin' => null], $headers)->assertOk();

        $logs = $this->inTenant($this->tenant, fn () => ActivityLog::query()
            ->where('module', 'users')->where('subject_id', $target->id)
            ->whereIn('action', ['pos_pin_set', 'pos_pin_cleared'])->orderBy('id')->get());
        $this->assertSame(['pos_pin_set', 'pos_pin_cleared'], $logs->pluck('action')->all());
        foreach ($logs as $log) {
            $this->assertSame($delegate->id, $log->user_id);
            $this->assertStringNotContainsString('583920', (string) json_encode($log->getAttributes()));
        }

        // Deliberate: the approval PIN is not a sign-in credential, so the target's sessions stay.
        $this->getJson('/api/v1/profile', $targetHeaders)->assertOk();
    }

    public function test_pin_and_password_of_another_tenants_user_cannot_be_changed(): void
    {
        $other = $this->createTenant();
        $foreign = $this->createTenantUser($other, 'cashier', attributes: ['phone' => '01000007559']);
        $foreignHash = $this->inTenant($other, fn (): string => (string) User::query()->findOrFail($foreign->id)->password);

        $this->putJson("/api/v1/users/{$foreign->id}/pos-pin", ['pin' => '135790'], $this->adminHeaders)
            ->assertNotFound()
            ->assertJsonPath('success', false);
        $this->putJson("/api/v1/users/{$foreign->id}", [
            'name' => 'اختراق',
            'phone' => '01000007559',
            'password' => 'hijacked12',
            'role' => 'cashier',
            'is_active' => true,
        ], $this->adminHeaders)->assertNotFound();

        $this->inTenant($other, function () use ($foreign, $foreignHash): void {
            $fresh = User::query()->findOrFail($foreign->id);
            $this->assertNull($fresh->pos_pin);
            $this->assertSame($foreignHash, (string) $fresh->password);
            $this->assertFalse(ActivityLog::query()->where('subject_id', $foreign->id)->exists());
        });
    }
}
