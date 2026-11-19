<?php

declare(strict_types=1);

namespace Tests\Feature\AccountDeletion;

use App\Enums\AccountDeletionRequestStatus;
use App\Jobs\NotifyPlatformOfAccountDeletionRequestJob;
use App\Models\AccountDeletionRequest;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * MOB-05: the public account-deletion page (legal document + SPA route) and
 * POST /api/v1/public/account-deletion-requests (public, throttled, central DB).
 */
final class PublicAccountDeletionRequestApiTest extends TenantTestCase
{
    private const URL = '/api/v1/public/account-deletion-requests';

    protected function setUp(): void
    {
        parent::setUp();

        $this->endTenancy();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'contact_name' => 'أحمد علي',
            'phone' => '01000000501',
            'email' => 'owner@example.test',
            'shop_code' => 'my-shop',
            'reason' => 'قفلت المحل',
            'confirm' => true,
        ], $overrides);
    }

    public function test_the_account_deletion_document_is_public_in_ar_and_en(): void
    {
        $ar = $this->getJson('/api/v1/public/legal/account-deletion', ['X-Locale' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.document', 'account-deletion')
            ->assertJsonPath('data.locale', 'ar');
        $this->assertStringContainsString('مسودة — تحتاج مراجعة قانونية قبل النشر', (string) $ar->json('data.markdown'));
        $this->assertStringContainsString('البيانات التي تُحذف', (string) $ar->json('data.markdown'));
        $this->assertStringContainsString('البيانات التي يُحتفظ بها', (string) $ar->json('data.markdown'));

        $en = $this->getJson('/api/v1/public/legal/account-deletion', ['X-Locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.locale', 'en');
        $this->assertStringContainsString('Data that is deleted', (string) $en->json('data.markdown'));
        $this->assertStringContainsString('Data that is retained', (string) $en->json('data.markdown'));
    }

    public function test_the_spa_route_is_served_without_authentication(): void
    {
        $this->withoutVite();

        $this->get('/account-deletion')->assertOk();
    }

    public function test_it_stores_a_pending_request_and_returns_only_a_message(): void
    {
        Queue::fake();

        $response = $this->postJson(self::URL, $this->payload(['phone' => '+20 100 000 0501', 'shop_code' => ' My-Shop ']))
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('account_deletion.submitted'));

        $this->assertArrayNotHasKey('data', $response->json());

        $row = AccountDeletionRequest::query()->sole();
        $this->assertSame(AccountDeletionRequestStatus::Pending, $row->status);
        $this->assertSame('01000000501', $row->phone);
        $this->assertSame('my-shop', $row->shop_code);
        $this->assertNotNull($row->ip);
        $this->assertSame((string) config('tenancy.database.central_connection'), $row->getConnectionName());

        Queue::assertPushed(NotifyPlatformOfAccountDeletionRequestJob::class, fn (NotifyPlatformOfAccountDeletionRequestJob $job): bool => $job->accountDeletionRequestId === $row->id);
    }

    public function test_it_needs_no_authentication_and_ignores_tenant_selection(): void
    {
        Queue::fake();

        $this->postJson(self::URL, $this->payload(), ['X-Tenant' => 'does-not-exist'])->assertCreated();
        $this->assertSame(0, Tenant::query()->count());
    }

    public function test_validation_rejects_bad_input(): void
    {
        Queue::fake();

        $this->postJson(self::URL, [])->assertStatus(422)->assertJsonValidationErrors(['contact_name', 'phone', 'confirm']);
        $this->postJson(self::URL, $this->payload(['phone' => '12345']))->assertStatus(422)->assertJsonValidationErrors(['phone']);
        $this->postJson(self::URL, $this->payload(['confirm' => false]))->assertStatus(422)->assertJsonValidationErrors(['confirm']);
        $this->postJson(self::URL, $this->payload(['email' => 'not-an-email']))->assertStatus(422)->assertJsonValidationErrors(['email']);
        $this->postJson(self::URL, $this->payload(['shop_code' => 'bad code!']))->assertStatus(422)->assertJsonValidationErrors(['shop_code']);
        $this->postJson(self::URL, $this->payload(['reason' => str_repeat('a', 1001)]))->assertStatus(422)->assertJsonValidationErrors(['reason']);

        $this->assertSame(0, AccountDeletionRequest::query()->count());
    }

    public function test_a_repeat_while_pending_creates_no_second_row_or_alert(): void
    {
        Queue::fake();

        $this->postJson(self::URL, $this->payload())->assertCreated();
        $this->postJson(self::URL, $this->payload())->assertCreated();

        $this->assertSame(1, AccountDeletionRequest::query()->count());
        Queue::assertPushed(NotifyPlatformOfAccountDeletionRequestJob::class, 1);
    }

    public function test_a_filled_honeypot_answers_201_but_stores_and_notifies_nothing(): void
    {
        Queue::fake();

        $this->postJson(self::URL, $this->payload(['website' => 'http://spam.test']))->assertCreated();

        $this->assertSame(0, AccountDeletionRequest::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_the_endpoint_is_throttled_per_ip(): void
    {
        Queue::fake();
        $perHour = (int) config('rate_limits.account_deletion_requests.per_hour', 3);

        for ($i = 0; $i < $perHour; $i++) {
            $this->postJson(self::URL, $this->payload(['phone' => '0100000060'.$i]))->assertCreated();
        }

        $this->postJson(self::URL, $this->payload(['phone' => '01000000699']))->assertStatus(429);
        $this->assertSame($perHour, AccountDeletionRequest::query()->count());
    }

    public function test_the_platform_bot_is_alerted_on_one_line_per_field(): void
    {
        config(['services.telegram.bot_token' => 'test-token', 'legal.account_deletion.telegram_chat_id' => '777']);
        Http::fake();
        $row = AccountDeletionRequest::query()->create([
            'contact_name' => "علي\nFAKE: line", 'phone' => '01000000502', 'shop_code' => 'shop-x', 'status' => 'pending',
        ]);

        (new NotifyPlatformOfAccountDeletionRequestJob($row->id))->handle();

        Http::assertSent(fn ($sent): bool => str_contains($sent->url(), 'sendMessage')
            && (string) $sent['chat_id'] === '777'
            && str_contains((string) $sent['text'], '01000000502')
            && str_contains((string) $sent['text'], 'shop-x')
            && ! str_contains((string) $sent['text'], "\nFAKE"));
    }

    public function test_a_notification_failure_never_throws_and_no_channel_means_no_call(): void
    {
        $row = AccountDeletionRequest::query()->create([
            'contact_name' => 'علي', 'phone' => '01000000503', 'status' => 'pending',
        ]);

        config(['services.telegram.bot_token' => '', 'services.telegram.chat_id' => '']);
        Http::fake();
        (new NotifyPlatformOfAccountDeletionRequestJob($row->id))->handle();
        Http::assertNothingSent();

        config(['services.telegram.bot_token' => 'test-token', 'services.telegram.chat_id' => '12345']);
        Http::fake(fn () => throw new RuntimeException('network down'));
        (new NotifyPlatformOfAccountDeletionRequestJob($row->id))->handle();

        $this->assertTrue(true);
    }
}
