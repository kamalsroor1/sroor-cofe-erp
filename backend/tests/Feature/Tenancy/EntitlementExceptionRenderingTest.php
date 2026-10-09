<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Exceptions\Entitlements\FeatureUnavailableException;
use App\Exceptions\Entitlements\PlanLimitExceededException;
use App\Exceptions\TenantLifecycleException;
use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TenantTestCase;

/**
 * ENTI-2.1 (+ IDEN-3.3 lifecycle exception): the documented JSON contract.
 *
 *   403 { success: false, message, error_code: "subscription.limit_reached", details: { limit, max } }
 *   403 { success: false, message, error_code: "subscription.feature_unavailable", details: { feature } }
 *   423 / 403 / 409 / 422 { success: false, message, error_code: "subscription.<code>", details? }
 *
 * The exceptions render themselves (no bootstrap/app.php callback), messages are
 * translated in both locales, and none of them is reported as a server error.
 */
final class EntitlementExceptionRenderingTest extends TenantTestCase
{
    private const ROUTE = 'api/v1/qa-entitlement-render';

    protected function tearDown(): void
    {
        App::setLocale((string) config('app.locale'));

        parent::tearDown();
    }

    public function test_plan_limit_exceeded_renders_403_with_the_limit_details(): void
    {
        $response = $this->throwing(fn () => throw PlanLimitExceededException::for('stores', 3));

        $response->assertStatus(403)
            ->assertExactJson([
                'success' => false,
                'message' => __('subscription.errors.limit_reached', ['resource' => __('subscription.limit_resources.stores'), 'max' => '3']),
                'error_code' => 'subscription.limit_reached',
                'details' => ['limit' => 'stores', 'max' => 3],
            ]);
    }

    public function test_feature_unavailable_renders_403_with_the_feature_key(): void
    {
        $response = $this->throwing(fn () => throw FeatureUnavailableException::for('mixes.manage'));

        $response->assertStatus(403)
            ->assertExactJson([
                'success' => false,
                'message' => __('subscription.errors.feature_unavailable'),
                'error_code' => 'subscription.feature_unavailable',
                'details' => ['feature' => 'mixes.manage'],
            ]);
    }

    public function test_an_unknown_limit_key_still_gets_a_translated_message(): void
    {
        $exception = PlanLimitExceededException::for('drones', 1);

        $this->assertStringNotContainsString('subscription.', $exception->getMessage());
        $this->assertStringContainsString((string) __('subscription.limit_resources.other'), $exception->getMessage());
        $this->assertSame('drones', $exception->limit());
        $this->assertSame(1, $exception->max());
    }

    /**
     * @return iterable<string, array{0: Closure(): TenantLifecycleException, 1: int, 2: string, 3: array<string, mixed>|null}>
     */
    public static function lifecycleCases(): iterable
    {
        yield 'read only' => [fn () => TenantLifecycleException::readOnly(), 423, 'subscription.read_only', ['status' => 'read_only']];
        yield 'blocked' => [fn () => TenantLifecycleException::blocked(TenantStatus::Suspended), 403, 'subscription.access_blocked', ['status' => 'suspended']];
        yield 'activation' => [fn () => TenantLifecycleException::activationRequiresPayment(TenantLifecycleActor::SuperAdmin), 403, 'subscription.activation_requires_payment', ['actor' => 'super_admin']];
        yield 'invalid transition' => [
            fn () => TenantLifecycleException::invalidTransition('trial', TenantStatus::PastDue, TenantLifecycleActor::SuperAdmin),
            409,
            'subscription.invalid_transition',
            ['from' => 'trial', 'to' => 'past_due', 'actor' => 'super_admin'],
        ];
        yield 'status conflict' => [
            fn () => TenantLifecycleException::statusConflict([TenantStatus::Active], 'suspended'),
            409,
            'subscription.status_conflict',
            ['expected' => ['active'], 'current' => 'suspended'],
        ];
        yield 'archive not allowed' => [fn () => TenantLifecycleException::archiveNotAllowed('active'), 409, 'subscription.archive_not_allowed', ['from' => 'active']];
        yield 'not archived' => [fn () => TenantLifecycleException::notArchived('trial'), 409, 'subscription.not_archived', ['current' => 'trial']];
        yield 'reason required' => [fn () => TenantLifecycleException::reasonRequired(), 422, 'subscription.suspension_reason_required', null];
    }

    /**
     * @param  Closure(): TenantLifecycleException  $make
     * @param  array<string, mixed>|null  $details
     */
    #[DataProvider('lifecycleCases')]
    public function test_lifecycle_exception_renders_its_status_and_error_code(Closure $make, int $status, string $errorCode, ?array $details): void
    {
        $response = $this->throwing(fn () => throw $make());

        $response->assertStatus($status)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', $errorCode);

        $json = (array) $response->json();
        $this->assertSame(['success', 'message', 'error_code', ...($details === null ? [] : ['details'])], array_keys($json));
        $this->assertSame($details, $json['details'] ?? null);
        $this->assertIsString($json['message']);
        $this->assertStringNotContainsString('subscription.', $json['message'], 'The message must be translated, not a raw key.');
    }

    public function test_messages_are_localized_in_arabic_and_english(): void
    {
        foreach (['ar', 'en'] as $locale) {
            App::setLocale($locale);

            $limit = PlanLimitExceededException::for('users', 5)->getMessage();
            $feature = FeatureUnavailableException::for('reports.advanced')->getMessage();
            $readOnly = TenantLifecycleException::readOnly()->getMessage();

            $this->assertSame((string) __('subscription.errors.feature_unavailable', [], $locale), $feature);
            $this->assertSame((string) __('subscription.errors.read_only', [], $locale), $readOnly);
            $this->assertStringContainsString((string) __('subscription.limit_resources.users', [], $locale), $limit);
            $this->assertStringContainsString('5', $limit);
        }

        $this->assertNotSame(
            __('subscription.errors.feature_unavailable', [], 'ar'),
            __('subscription.errors.feature_unavailable', [], 'en'),
        );
    }

    public function test_new_subscription_keys_exist_in_both_locales(): void
    {
        $ar = Arr::dot((array) require lang_path('ar/subscription.php'));
        $en = Arr::dot((array) require lang_path('en/subscription.php'));

        $this->assertSame(array_keys($ar), array_keys($en), 'ar/en subscription.php must have the same keys in the same order.');

        foreach (['errors.limit_reached', 'errors.feature_unavailable', 'errors.read_only', 'errors.access_blocked', 'limit_resources.other', 'suspension_reasons.violation'] as $key) {
            $this->assertArrayHasKey($key, $ar);
            $this->assertNotSame('', trim((string) $ar[$key]));
            $this->assertNotSame('', trim((string) $en[$key]));
        }
    }

    public function test_none_of_them_is_reported_as_a_server_error(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $this->assertFalse($handler->shouldReport(PlanLimitExceededException::for('items', 10)));
        $this->assertFalse($handler->shouldReport(FeatureUnavailableException::for('pos.access')));
        $this->assertFalse($handler->shouldReport(TenantLifecycleException::readOnly()));
    }

    /**
     * Calls a throw-away API route (no auth, no tenancy) whose action throws.
     *
     * @param  Closure(): never  $throw
     * @return TestResponse<Response>
     */
    private function throwing(Closure $throw)
    {
        Route::get(self::ROUTE, $throw);

        return $this->getJson('/'.self::ROUTE);
    }
}
