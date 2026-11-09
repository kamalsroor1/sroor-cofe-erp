<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Item;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Tests\TenantTestCase;

final class ReportMinPriceEqualsCostCommandTest extends TenantTestCase
{
    /** @return array{0: Tenant, 1: int, 2: int, 3: int} */
    private function seedTenant(): array
    {
        $tenant = $this->createTenant();

        [$affected, $deliberate, $noFloor] = $this->inTenant($tenant, function (): array {
            $make = fn (string $code, string $cost, string $min): int => (int) Item::query()->create([
                'name' => $code, 'code' => $code, 'unit' => 'قطعة',
                'cost_price' => $cost, 'min_selling_price' => $min, 'selling_price' => '500.000',
                'weighted_avg_cost' => $cost, 'current_stock' => '0.000', 'is_active' => true,
            ])->id;

            return [$make('A', '100.000', '100.000'), $make('B', '100.000', '150.000'), $make('C', '0.000', '0.000')];
        });

        return [$tenant, $affected, $deliberate, $noFloor];
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function mins(Tenant $tenant, int ...$ids): array
    {
        return $this->inTenant($tenant, fn (): array => array_map(
            fn (int $id): string => (string) Item::query()->findOrFail($id)->min_selling_price,
            $ids,
        ));
    }

    public function test_dry_run_reports_but_changes_nothing(): void
    {
        [$tenant, $a, $b, $c] = $this->seedTenant();

        $this->assertSame(0, Artisan::call('items:report-min-price-equals-cost', ['--tenant' => $tenant->id]));
        $output = Artisan::output();

        $this->assertStringContainsString('A', $output);
        $this->assertSame(['100.000', '150.000', '0.000'], $this->mins($tenant, $a, $b, $c));
    }

    public function test_fix_resets_only_items_where_min_equals_cost(): void
    {
        [$tenant, $a, $b, $c] = $this->seedTenant();

        $this->assertSame(0, Artisan::call('items:report-min-price-equals-cost', ['--tenant' => $tenant->id, '--fix' => true]));

        $this->assertSame(['0.000', '150.000', '0.000'], $this->mins($tenant, $a, $b, $c));
    }

    public function test_unknown_tenant_fails(): void
    {
        $this->assertSame(1, Artisan::call('items:report-min-price-equals-cost', ['--tenant' => 'nope']));
    }
}
