<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Tests\TenantTestCase;

/**
 * TXT-01: every 403 authorization refusal on the API must return a translated
 * message from lang/{ar,en}, never Laravel's default "This action is unauthorized.".
 */
final class UnauthorizedMessageTest extends TenantTestCase
{
    public function test_policy_or_authorize_403_is_translated_in_arabic_and_english(): void
    {
        Route::middleware('api')->get('/api/v1/_test-authorize-denial', function (): void {
            throw new AuthorizationException;
        });

        $this->getJson('/api/v1/_test-authorize-denial', ['X-Locale' => 'ar'])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('auth.unauthorized', [], 'ar'));

        $this->getJson('/api/v1/_test-authorize-denial', ['X-Locale' => 'en'])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('auth.unauthorized', [], 'en'));
    }

    public function test_unauthorized_transfer_post_returns_translated_403(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createTenantUser($tenant, attributes: ['name' => 'كاشير']);
        $headers = $this->tenantHeaders($tenant, $user);

        $arResponse = $this->postJson('/api/v1/transfers', [], $headers + ['X-Locale' => 'ar'])
            ->assertStatus(403);
        $this->assertNotSame('This action is unauthorized.', $arResponse->json('message'));
        $this->assertSame(__('auth.unauthorized', [], 'ar'), $arResponse->json('message'));

        $enResponse = $this->postJson('/api/v1/transfers', [], $headers + ['X-Locale' => 'en'])
            ->assertStatus(403);
        $this->assertNotSame('This action is unauthorized.', $enResponse->json('message'));
        $this->assertSame(__('auth.unauthorized', [], 'en'), $enResponse->json('message'));
    }
}
