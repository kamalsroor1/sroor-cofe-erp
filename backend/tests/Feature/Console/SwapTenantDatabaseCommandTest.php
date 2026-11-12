<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Actions\Tenants\AdvanceDocumentSequencesAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TenantTestCase;

/**
 * BKP-02: backup:swap-tenant refusals and the document counter jump that stops numbers printed
 * after the backup from being issued again.
 */
final class SwapTenantDatabaseCommandTest extends TenantTestCase
{
    public function test_an_unknown_tenant_is_refused(): void
    {
        $this->artisan('backup:swap-tenant', ['tenant' => 'no-such-shop', '--database' => 'tenant_zz_restore_x', '--force' => true])
            ->expectsOutputToContain((string) __('console.swap.tenant_not_found', ['tenant' => 'no-such-shop']))
            ->assertExitCode(1);
    }

    public function test_a_missing_or_unsafe_database_name_is_refused(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();

        $this->artisan('backup:swap-tenant', ['tenant' => $id, '--force' => true])
            ->expectsOutputToContain((string) __('console.swap.invalid_database'))
            ->assertExitCode(1);

        $this->artisan('backup:swap-tenant', ['tenant' => $id, '--database' => 'x`; drop database y', '--force' => true])
            ->expectsOutputToContain((string) __('console.swap.invalid_database'))
            ->assertExitCode(1);
    }

    public function test_a_margin_below_one_is_refused(): void
    {
        $tenant = $this->createTenant();

        $this->artisan('backup:swap-tenant', [
            'tenant' => (string) $tenant->getTenantKey(),
            '--database' => 'tenant_zz_restore_x',
            '--number-margin' => 0,
            '--force' => true,
        ])->expectsOutputToContain((string) __('console.swap.invalid_margin'))->assertExitCode(1);
    }

    public function test_every_counter_moves_forward_and_missing_days_get_a_row(): void
    {
        $this->inTenant($this->createTenant(), $this->assertCountersAdvance(...));
    }

    private function assertCountersAdvance(): void
    {
        DB::table('document_sequences')->insert([
            ['scope' => 'INV-MAIN-20200101', 'last_value' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['scope' => 'PAY-INV-20200101', 'last_value' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['scope' => 'SHIFT', 'last_value' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $result = app(AdvanceDocumentSequencesAction::class)->execute(1000);

        $this->assertSame(3, $result['bumped']);
        $this->assertSame(1007, (int) DB::table('document_sequences')->where('scope', 'INV-MAIN-20200101')->value('last_value'));
        $this->assertSame(1003, (int) DB::table('document_sequences')->where('scope', 'PAY-INV-20200101')->value('last_value'));
        $this->assertSame(1005, (int) DB::table('document_sequences')->where('scope', 'SHIFT')->value('last_value'));
        // 2020 is far in the past: only the last 31 days up to today get a seeded row, per family.
        $this->assertSame(1000, (int) DB::table('document_sequences')->where('scope', 'like', 'INV-MAIN-%')->where('scope', '!=', 'INV-MAIN-20200101')->min('last_value'));
        $this->assertGreaterThan(0, $result['created']);
    }

    public function test_a_margin_below_one_is_rejected_by_the_action(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(AdvanceDocumentSequencesAction::class)->execute(0);
    }
}
