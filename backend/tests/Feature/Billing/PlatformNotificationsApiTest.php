<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\PlatformNotificationType;
use App\Mail\BillingReceiptUploadedMail;
use App\Mail\BillingTemporaryAccessReminderMail;
use App\Models\PlatformNotification;
use App\Models\PlatformNotificationRead;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;

/**
 * #232 spec E / UI §4.2: the super-admin panel notifications (the bell) API.
 * 200 / 401 / 403, per-operator read marks, 404 on unknown ids, a tenant token never gets in,
 * and the operator e-mails render in both locales without the receipt.
 */
#[Group('billing')]
final class PlatformNotificationsApiTest extends BillingTestCase
{
    private const URL = '/api/v1/super-admin/notifications';

    private function item(PlatformNotificationType $type = PlatformNotificationType::BillingReceiptUploaded, array $data = []): PlatformNotification
    {
        $this->endTenancy();

        return PlatformNotification::query()->create([
            'type' => $type,
            'tenant_id' => 'tenant-x',
            'billing_invoice_id' => 7,
            'billing_payment_id' => 9,
            'data' => array_merge([
                'tenant_name' => 'محل الأمل',
                'invoice_number' => 'SUB-2026-00007',
                'invoice_type' => 'plan',
                'amount' => '449.000',
                'currency' => 'EGP',
                'method' => 'instapay',
                'temporary_access' => true,
                'temporary_access_ends_at' => '2026-11-28T10:00:00+02:00',
            ], $data),
            'dedupe_key' => 'test:'.Str::random(12),
        ]);
    }

    public function test_an_operator_lists_items_with_their_own_unread_count(): void
    {
        $older = $this->item();
        $newer = $this->item(PlatformNotificationType::BillingTemporaryAccessReminder);
        $operator = $this->centralSuperAdmin();

        $this->getJson(self::URL, $this->centralHeaders($operator) + ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('unread_count', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', (int) $newer->getKey())
            ->assertJsonPath('data.0.type', 'billing_temporary_access_reminder')
            ->assertJsonPath('data.0.severity', 'warning')
            ->assertJsonPath('data.1.id', (int) $older->getKey())
            ->assertJsonPath('data.1.is_read', false)
            ->assertJsonPath('data.1.billing_payment_id', 9)
            ->assertJsonPath('data.1.link.query.payment', 9)
            ->assertJsonStructure(['data' => [['id', 'type', 'title', 'body', 'tenant', 'facts', 'link', 'is_read', 'created_at']], 'meta', 'links']);
    }

    public function test_marking_read_is_per_operator_and_idempotent(): void
    {
        $first = $this->item();
        $this->item();
        $me = $this->centralSuperAdmin();
        $colleague = $this->centralSuperAdmin();
        $headers = $this->centralHeaders($me);

        $this->postJson(self::URL.'/'.$first->getKey().'/read', [], $headers)->assertOk()->assertJsonPath('data.is_read', true);
        $this->postJson(self::URL.'/'.$first->getKey().'/read', [], $headers)->assertOk();
        $this->assertSame(1, PlatformNotificationRead::query()->count());

        $this->getJson(self::URL.'?unread=1', $headers)->assertOk()->assertJsonPath('unread_count', 1)->assertJsonCount(1, 'data');
        $this->getJson(self::URL, $this->centralHeaders($colleague))->assertJsonPath('unread_count', 2);

        $this->postJson(self::URL.'/read-all', [], $headers)->assertOk()->assertJsonPath('data.marked', 1);
        $this->getJson(self::URL, $headers)->assertJsonPath('unread_count', 0);
        $this->getJson(self::URL, $this->centralHeaders($colleague))->assertJsonPath('unread_count', 2);
    }

    public function test_support_can_read_the_bell_and_an_operator_without_billing_view_cannot(): void
    {
        $this->item();

        $this->getJson(self::URL, $this->centralHeaders($this->centralSupport()))->assertOk()->assertJsonPath('unread_count', 1);

        $noRole = $this->centralHeaders($this->centralOperator(null));
        $this->getJson(self::URL, $noRole)->assertForbidden();
        $this->postJson(self::URL.'/read-all', [], $noRole)->assertForbidden();
    }

    public function test_unauthenticated_and_tenant_tokens_are_refused(): void
    {
        $item = $this->item();

        $this->getJson(self::URL)->assertUnauthorized();
        $this->postJson(self::URL.'/'.$item->getKey().'/read')->assertUnauthorized();

        $tenant = $this->createTenant();
        $status = $this->getJson(self::URL, $this->tenantHeaders($tenant))->status();
        $this->assertContains($status, [401, 403, 404], 'A shop token never reaches the console bell.');
        $this->assertSame(0, PlatformNotificationRead::query()->count());
    }

    public function test_unknown_ids_and_validation(): void
    {
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $this->postJson(self::URL.'/999999/read', [], $headers)->assertNotFound();
        $this->getJson(self::URL.'?per_page=500', $headers)->assertStatus(422)->assertJsonValidationErrors(['per_page']);
        $this->getJson(self::URL.'?unread=maybe', $headers)->assertStatus(422)->assertJsonValidationErrors(['unread']);
    }

    public function test_the_operator_emails_render_in_both_locales_without_the_receipt(): void
    {
        foreach (['ar' => 'rtl', 'en' => 'ltr'] as $locale => $dir) {
            $receipt = new BillingReceiptUploadedMail('محل الأمل', 'SUB-2026-00007', 'plan', '449.000', 'EGP', 'instapay', '2026-11-26 10:00', null, 'https://admin.example.test/super-admin/billing?payment=9', $locale);
            $html = $receipt->render();
            $this->assertStringContainsString('dir="'.$dir.'"', $html);
            $this->assertStringContainsString((string) __('mail.billing_receipt.access_manual', [], $locale), $html);
            $this->assertStringContainsString('SUB-2026-00007', $html);
            $this->assertStringContainsString((string) __('mail.billing_receipt.button', [], $locale), $html);
            $this->assertStringNotContainsString((string) __('mail.layout.support_title', [], $locale), $html, 'Operator mails carry their own footer.');
            $this->assertSame([], $receipt->attachments);

            $reminder = new BillingTemporaryAccessReminderMail('محل الأمل', 'SUB-2026-00007', '449.000', 'EGP', 'instapay', '2026-11-28 10:00', 'https://admin.example.test/super-admin/billing?payment=9', $locale);
            $this->assertStringContainsString((string) __('mail.billing_grace_reminder.consequence', [], $locale), $reminder->render());
            $this->assertStringContainsString('2026-11-28 10:00', $reminder->render());
        }
    }
}
