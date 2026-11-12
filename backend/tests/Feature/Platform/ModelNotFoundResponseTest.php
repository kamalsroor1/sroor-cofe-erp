<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * SEC-21 (comment of 2026-11-12): API 404s named the model class and id. Exploit before the fix:
 * GET /api/v1/shifts/2/z-report answered `{"message": "No query results for model
 * [App\Models\CashShift] 2"}` and GET /api/v1/billing/invoices/2 named App\Models\BillingInvoice:
 * internal class names (and which ids exist where) reached the end user. Every ModelNotFound
 * that reaches the handler is now the translated `common.record_not_found` in the API envelope.
 */
final class ModelNotFoundResponseTest extends TenantTestCase
{
    /** @return array<string, array{0: string}> */
    public static function missingRecords(): array
    {
        return [
            'shift z-report' => ['/api/v1/shifts/99999999/z-report'],
            'subscription invoice' => ['/api/v1/billing/invoices/99999999'],
            'sales invoice' => ['/api/v1/invoices/99999999'],
            'store' => ['/api/v1/stores/99999999'],
            'item movements' => ['/api/v1/items/99999999/movements'],
        ];
    }

    #[DataProvider('missingRecords')]
    public function test_a_missing_record_is_a_translated_404_without_the_model_class(string $url): void
    {
        $tenant = $this->createTenant();

        $response = $this->getJson($url, $this->tenantHeaders($tenant))->assertNotFound();

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('App\\\\Models', $body);
        $this->assertStringNotContainsString('App\\Models', $body);
        $this->assertStringNotContainsString('No query results', $body);
        $response->assertJsonPath('success', false)->assertJsonPath('message', __('common.record_not_found'));
    }

    public function test_the_message_follows_the_request_locale(): void
    {
        $tenant = $this->createTenant();

        $this->getJson('/api/v1/invoices/99999999', [...$this->tenantHeaders($tenant), 'X-Locale' => 'en'])
            ->assertNotFound()
            ->assertJsonPath('message', trans('common.record_not_found', [], 'en'));
    }

    public function test_an_unknown_api_route_is_still_a_plain_404(): void
    {
        $tenant = $this->createTenant();

        $body = (string) $this->getJson('/api/v1/this-route-does-not-exist', $this->tenantHeaders($tenant))->assertNotFound()->getContent();
        $this->assertStringNotContainsString('App\\Models', $body);
    }
}
