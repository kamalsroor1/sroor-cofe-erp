<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\Item;
use App\Support\SafeJsonError;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Laravel\Telescope\Telescope;
use RuntimeException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Tests\TenantTestCase;

/**
 * SEC-1 (W2 batch 6) response hygiene:
 *  - L4: SafeJsonError never echoes an exception message; 404/422/403 keep their own answers.
 *  - L5: spatie's UnauthorizedException renders the translated message only (no role or
 *    permission names).
 *  - L6: Telescope hides 2FA codes, passwords, integration secrets and the X-API-TOKEN header.
 */
final class ResponseHygieneTest extends TenantTestCase
{
    public function test_safe_json_error_hides_unexpected_exception_text(): void
    {
        $response = SafeJsonError::respond(new RuntimeException('SQLSTATE[HY000]: secret /var/www/app.php'));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(['success' => false, 'message' => __('common.operation_failed')], $response->getData(true));
    }

    public function test_safe_json_error_answers_a_missing_record_with_a_translated_404(): void
    {
        $response = SafeJsonError::respond((new ModelNotFoundException)->setModel(Item::class, [5]));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(__('common.record_not_found'), $response->getData(true)['message']);
        $this->assertStringNotContainsString('App\\Models', (string) $response->getContent());
    }

    public function test_safe_json_error_lets_validation_errors_through(): void
    {
        $this->expectException(ValidationException::class);

        SafeJsonError::respond(ValidationException::withMessages(['field' => 'translated']));
    }

    public function test_spatie_unauthorized_exception_does_not_leak_the_permission_name(): void
    {
        Route::middleware('api')->get('api/__qa/spatie-unauthorized', static function (): never {
            throw UnauthorizedException::forPermissions(['logs.view', 'super_admin.tenants.manage']);
        });

        $response = $this->getJson('/api/__qa/spatie-unauthorized')
            ->assertStatus(403)
            ->assertExactJson(['success' => false, 'message' => __('auth.unauthorized')]);

        $this->assertStringNotContainsString('logs.view', (string) $response->getContent());
    }

    public function test_telescope_hides_secrets_from_recorded_requests(): void
    {
        foreach (['code', 'recovery_code', 'current_password', 'new_password', 'telegram_bot_token', 'token', 'password'] as $parameter) {
            $this->assertContains($parameter, Telescope::$hiddenRequestParameters, $parameter);
        }

        foreach (['x-api-token', 'authorization', 'cookie'] as $header) {
            $this->assertContains($header, Telescope::$hiddenRequestHeaders, $header);
        }
    }
}
