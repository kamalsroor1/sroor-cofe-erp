<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\DatabaseBusyException;
use App\Support\SafeJsonError;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Throwable;

/**
 * BUG-04 / BUG-07: a lock conflict that reaches the exception handler (after the retries, or
 * from a flow without them) is answered 409 with a translated "busy, try again" message on
 * every API endpoint, even with APP_DEBUG=false. Never a 500.
 */
final class DatabaseBusyResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);
    }

    /** @return array<string, array{int, string, string}> */
    public static function lockConflicts(): array
    {
        return [
            'deadlock 1213, ar' => [1213, '40001', 'ar'],
            'lock wait timeout 1205, en' => [1205, 'HY000', 'en'],
            'MariaDB record changed 1020, ar' => [1020, 'HY000', 'ar'],
            'MariaDB record changed 1020, en' => [1020, 'HY000', 'en'],
        ];
    }

    #[DataProvider('lockConflicts')]
    public function test_a_raw_lock_conflict_renders_409_with_a_translated_message(int $code, string $sqlState, string $locale): void
    {
        App::setLocale($locale);

        $response = $this->render($this->queryException($code, $sqlState));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('1', $response->headers->get('Retry-After'));
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame([
            'success' => false,
            'message' => trans('common.database_busy', [], $locale),
            'error_code' => 'common.database_busy',
        ], $payload);
        $this->assertNotSame('common.database_busy', $payload['message'], 'The key must be translated.');
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent(), 'No SQL leaks to the client.');
    }

    public function test_a_lock_conflict_from_a_nested_transaction_renders_409(): void
    {
        // Laravel's DeadlockException (nested level) carries no errorInfo: the code is on its previous.
        App::setLocale('ar');

        $response = $this->render(new DeadlockException('Deadlock found', 40001, $this->queryException(1213, '40001')));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(trans('common.database_busy', [], 'ar'), json_decode((string) $response->getContent(), true)['message']);
    }

    public function test_the_busy_message_differs_per_locale(): void
    {
        $this->assertNotSame(trans('common.database_busy', [], 'ar'), trans('common.database_busy', [], 'en'));
    }

    public function test_the_busy_exception_after_the_last_retry_renders_409(): void
    {
        App::setLocale('en');

        $response = $this->render(DatabaseBusyException::fromThrowable($this->queryException(1213, '40001'), 3));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(trans('common.database_busy', [], 'en'), json_decode((string) $response->getContent(), true)['message']);
    }

    public function test_any_other_query_error_is_not_turned_into_busy(): void
    {
        $response = $this->render($this->queryException(1062, '23000'));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringNotContainsString('common.database_busy', (string) $response->getContent());
    }

    public function test_controller_catch_alls_pass_a_lock_conflict_through_as_busy(): void
    {
        // PaymentController / POSController catch Throwable into SafeJsonError: the lock conflict
        // must keep its 409, not become their generic 422.
        try {
            SafeJsonError::respond($this->queryException(1020, 'HY000'), 'contacts.receipt_record_failed_generic');
            $this->fail('DatabaseBusyException expected.');
        } catch (DatabaseBusyException $e) {
            $this->assertSame(409, $this->render($e)->getStatusCode());
        }

        $busy = DatabaseBusyException::fromThrowable($this->queryException(1213, '40001'), 3);
        try {
            SafeJsonError::respond($busy);
            $this->fail('DatabaseBusyException expected.');
        } catch (DatabaseBusyException $e) {
            $this->assertSame($busy, $e);
        }

        $this->assertSame(422, SafeJsonError::respond($this->queryException(1062, '23000'))->getStatusCode());
    }

    private function render(Throwable $e): Response
    {
        $request = Request::create('/api/busy-probe', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);

        return $this->app->make(ExceptionHandler::class)->render($request, $e);
    }

    private function queryException(int $driverCode, string $sqlState): QueryException
    {
        $pdo = new PDOException("SQLSTATE[{$sqlState}]: General error: {$driverCode}");
        $pdo->errorInfo = [$sqlState, $driverCode, 'lock conflict'];

        return new QueryException('mysql', 'select * from `store_stocks` for update', [], $pdo);
    }
}
