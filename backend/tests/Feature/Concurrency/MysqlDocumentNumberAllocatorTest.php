<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Services\DocumentNumberService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * PERF-01: the MySQL/MariaDB hot path of DocumentNumberService (UPDATE ... LAST_INSERT_ID(`last_value` + 1))
 * against a REAL server. The unit test mocks the connection, which is how a reserved-word syntax
 * error (`last_value` on MySQL 8) once slipped through: this test runs the actual SQL.
 * Concurrent allocation under real checkouts is covered by BranchSalesAndTransfersRaceTest.
 */
#[Group('mysql')]
#[Group('mysql-only')]
#[Group('concurrency')]
final class MysqlDocumentNumberAllocatorTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $driver = DB::connection($this->centralConnectionName())->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->fail("MySQL-only test run on `{$driver}`. Use: php artisan test -c phpunit.mysql.xml");
        }
    }

    public function test_sequential_allocations_are_gapless_and_unique(): void
    {
        $tenant = $this->createTenant();

        $numbers = $this->inTenant($tenant, function (): array {
            $service = app(DocumentNumberService::class);
            $issued = [];
            for ($i = 0; $i < 5; $i++) {
                $issued[] = DB::transaction(fn (): string => $service->next('QA-SEQ', 4));
            }

            return $issued;
        });

        $this->assertSame(['QA-SEQ-0001', 'QA-SEQ-0002', 'QA-SEQ-0003', 'QA-SEQ-0004', 'QA-SEQ-0005'], $numbers);
    }

    public function test_a_rolled_back_transaction_gives_its_number_back(): void
    {
        $tenant = $this->createTenant();

        $numbers = $this->inTenant($tenant, function (): array {
            $service = app(DocumentNumberService::class);
            $first = DB::transaction(fn (): string => $service->next('QA-RB', 4));

            try {
                DB::transaction(function () use ($service): void {
                    $service->next('QA-RB', 4);

                    throw new RuntimeException('roll back');
                });
            } catch (RuntimeException) {
                // expected
            }

            $after = DB::transaction(fn (): string => $service->next('QA-RB', 4));

            return [$first, $after];
        });

        $this->assertSame(['QA-RB-0001', 'QA-RB-0002'], $numbers);
    }

    public function test_a_number_already_taken_by_hand_is_skipped(): void
    {
        $tenant = $this->createTenant();

        $number = $this->inTenant($tenant, function (): string {
            $taken = ['QA-EX-0001', 'QA-EX-0002'];

            return DB::transaction(fn (): string => app(DocumentNumberService::class)
                ->next('QA-EX', 4, fn (string $candidate): bool => in_array($candidate, $taken, true)));
        });

        $this->assertSame('QA-EX-0003', $number);
    }
}
