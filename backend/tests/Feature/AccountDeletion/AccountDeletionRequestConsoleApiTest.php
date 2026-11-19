<?php

declare(strict_types=1);

namespace Tests\Feature\AccountDeletion;

use App\Enums\AccountDeletionRequestStatus;
use App\Enums\CentralAuditEvent;
use App\Enums\CentralPermission;
use App\Models\AccountDeletionRequest;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * MOB-05: platform console queue of account-deletion requests (routes/central.php).
 */
final class AccountDeletionRequestConsoleApiTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const LIST = '/api/v1/super-admin/account-deletion-requests';

    private CentralUser $operator;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        $this->operator = $this->centralSuperAdmin();
        $this->headers = $this->steppedUpCentralHeaders($this->operator);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pending(array $overrides = []): AccountDeletionRequest
    {
        return AccountDeletionRequest::query()->create(array_merge([
            'contact_name' => 'أحمد علي',
            'phone' => '01000000'.str_pad((string) random_int(100, 999), 3, '0', STR_PAD_LEFT),
            'email' => 'owner@example.test',
            'shop_code' => 'my-shop',
            'status' => AccountDeletionRequestStatus::Pending->value,
            'ip' => '10.0.0.1',
            'user_agent' => 'phpunit',
        ], $overrides));
    }

    public function test_unauthenticated_requests_are_401(): void
    {
        $row = $this->pending();

        $this->getJson(self::LIST)->assertStatus(401);
        $this->postJson(self::LIST."/{$row->id}/handle", [])->assertStatus(401);
    }

    public function test_an_operator_without_a_role_is_403(): void
    {
        $row = $this->pending();
        $headers = $this->centralHeaders($this->centralOperator(null));

        $this->getJson(self::LIST, $headers)->assertStatus(403);
        $this->postJson(self::LIST."/{$row->id}/handle", [], $headers)->assertStatus(403);
    }

    public function test_a_read_only_support_operator_can_list_but_not_handle(): void
    {
        $row = $this->pending();
        $headers = $this->steppedUpCentralHeaders($this->centralOperator(CentralPermission::ROLE_SUPPORT));

        $this->getJson(self::LIST, $headers)->assertOk();
        $this->postJson(self::LIST."/{$row->id}/handle", [], $headers)->assertStatus(403);

        $this->assertSame(AccountDeletionRequestStatus::Pending, $row->fresh()?->status);
    }

    public function test_a_tenant_token_is_not_a_console_identity(): void
    {
        $tenant = $this->createTenant();
        $bare = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($tenant)];

        $this->getJson(self::LIST, $bare)->assertStatus(401);
    }

    public function test_list_is_newest_first_filterable_and_hides_network_details(): void
    {
        $old = $this->pending(['contact_name' => 'قديم', 'created_at' => now()->subDays(2)]);
        $new = $this->pending(['contact_name' => 'جديد', 'shop_code' => 'other-shop']);
        $this->pending(['contact_name' => 'تم', 'status' => AccountDeletionRequestStatus::Handled->value]);

        $all = $this->getJson(self::LIST.'?status=all', $this->headers)->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
        $this->assertCount(3, $all->json('data'));

        $pending = $this->getJson(self::LIST.'?status=pending', $this->headers)->assertOk();
        $this->assertSame([$new->id, $old->id], array_column($pending->json('data'), 'id'));

        $row = $pending->json('data.0');
        $this->assertArrayNotHasKey('ip', $row);
        $this->assertArrayNotHasKey('user_agent', $row);

        $search = $this->getJson(self::LIST.'?status=all&search=other-shop', $this->headers)->assertOk();
        $this->assertSame([$new->id], array_column($search->json('data'), 'id'));

        $this->getJson(self::LIST.'?status=bogus', $this->headers)->assertStatus(422);
    }

    public function test_handle_closes_the_request_once_and_audits_it(): void
    {
        $row = $this->pending();

        $this->postJson(self::LIST."/{$row->id}/handle", ['note' => 'تم الحذف بعد التحقق'], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'handled')
            ->assertJsonPath('data.handled_note', 'تم الحذف بعد التحقق')
            ->assertJsonPath('data.handled_by_name', $this->operator->name);

        $fresh = $row->fresh();
        $this->assertSame(AccountDeletionRequestStatus::Handled, $fresh?->status);
        $this->assertSame((int) $this->operator->getKey(), $fresh?->handled_by);
        $this->assertNotNull($fresh?->handled_at);

        $this->assertTrue(CentralAuditLog::query()->where('event', CentralAuditEvent::AccountDeletionRequestHandled->value)->exists());

        $this->postJson(self::LIST."/{$row->id}/handle", [], $this->headers)->assertStatus(409);
    }

    public function test_handle_validates_the_note_and_404s_an_unknown_request(): void
    {
        $row = $this->pending();

        $this->postJson(self::LIST."/{$row->id}/handle", ['note' => str_repeat('a', 501)], $this->headers)
            ->assertStatus(422)->assertJsonValidationErrors(['note']);
        $this->postJson(self::LIST."/{$row->id}/handle", ['note' => '<script>'], $this->headers)
            ->assertStatus(422)->assertJsonValidationErrors(['note']);
        $this->postJson(self::LIST.'/999999/handle', [], $this->headers)->assertStatus(404);
    }
}
