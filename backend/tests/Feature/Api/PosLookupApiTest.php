<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\Store;
use App\Models\StorePosSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Pos\ScaleBarcodeParser;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * POS-01: GET /api/v1/pos/lookup?code= — exact code match, scale labels, store scoping.
 */
final class PosLookupApiTest extends TenantTestCase
{
    private const URL = '/api/v1/pos/lookup';

    private function createItem(Tenant $tenant, string $code, array $extra = []): int
    {
        return $this->inTenant($tenant, fn (): int => (int) Item::query()->create(array_merge([
            'code' => $code,
            'name' => 'صنف '.$code,
            'category' => 'general',
            'unit' => 'قطعة',
            'cost_price' => '10.000',
            'selling_price' => '32.500',
            'current_stock' => '7.000',
            'min_stock_level' => '0.000',
            'is_active' => true,
        ], $extra))->id);
    }

    private function enableScale(Tenant $tenant, int $storeId, array $extra = []): void
    {
        $this->inTenant($tenant, fn () => StorePosSetting::query()->create(array_merge([
            'store_id' => $storeId,
            'scale_barcode_enabled' => true,
            'scale_prefixes' => ['2'],
            'scale_plu_length' => 5,
            'scale_value_type' => 'weight',
            'scale_value_length' => 5,
            'scale_weight_divisor' => 1000,
            'scale_price_divisor' => 100,
            'scale_check_digit' => true,
        ], $extra)));
    }

    private function scaleLabel(string $plu, string $grams): string
    {
        $body = '2'.$plu.$grams;

        return $body.ScaleBarcodeParser::checkDigit($body);
    }

    public function test_exact_code_returns_the_pos_item(): void
    {
        $tenant = $this->createTenant();
        $id = $this->createItem($tenant, '1234');
        $this->createItem($tenant, '12345');

        $this->getJson(self::URL.'?code=1234', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.item.id', $id)
            ->assertJsonPath('data.item.code', '1234')
            ->assertJsonPath('data.item.current_stock', '7.000')
            ->assertJsonPath('data.quantity', null)
            ->assertJsonPath('data.source', 'code');
    }

    public function test_partial_code_and_name_do_not_match(): void
    {
        $tenant = $this->createTenant();
        $this->createItem($tenant, '1234', ['name' => 'قهوة 123']);
        $headers = $this->tenantHeaders($tenant);

        foreach (['123', '12', '%', '_', 'قهوة'] as $code) {
            $this->getJson(self::URL.'?code='.urlencode($code), $headers)
                ->assertStatus(404)
                ->assertJsonPath('success', false);
        }
        $this->assertStringContainsString('123', (string) $this->getJson(self::URL.'?code=123', $headers)->json('message'));
    }

    public function test_inactive_item_is_not_found(): void
    {
        $tenant = $this->createTenant();
        $this->createItem($tenant, 'OFF', ['is_active' => false]);

        $this->getJson(self::URL.'?code=OFF', $this->tenantHeaders($tenant))->assertStatus(404);
    }

    public function test_code_is_required(): void
    {
        $tenant = $this->createTenant();

        $this->getJson(self::URL, $this->tenantHeaders($tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_scale_label_returns_item_and_weight(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->id;
        $id = $this->createItem($tenant, '00123', ['is_weighted' => true, 'unit' => 'كجم']);
        $this->enableScale($tenant, $storeId);

        $this->getJson(self::URL.'?code='.$this->scaleLabel('00123', '01250'), $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.item.id', $id)
            ->assertJsonPath('data.item.is_weighted', true)
            ->assertJsonPath('data.quantity', '1.250')
            ->assertJsonPath('data.source', 'scale');
    }

    public function test_scale_label_is_ignored_when_disabled_or_unknown_plu_or_bad_check_digit(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->id;
        $this->createItem($tenant, '00123', ['is_weighted' => true]);
        $headers = $this->tenantHeaders($tenant);
        $label = $this->scaleLabel('00123', '01250');

        // Scale labels are off by default.
        $this->getJson(self::URL.'?code='.$label, $headers)->assertStatus(404);

        $this->enableScale($tenant, $storeId);
        $this->getJson(self::URL.'?code='.$this->scaleLabel('99999', '01250'), $headers)->assertStatus(404);

        $bad = substr($label, 0, -1).((int) substr($label, -1) + 1) % 10;
        $this->getJson(self::URL.'?code='.$bad, $headers)->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_price_label_derives_quantity_from_unit_price(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->id;
        $this->createItem($tenant, '00123', ['selling_price' => '50.000']);
        $this->enableScale($tenant, $storeId, ['scale_value_type' => 'price']);

        // 02500 piasters = 25.00 EGP / 50 per unit = 0.500
        $this->getJson(self::URL.'?code='.$this->scaleLabel('00123', '02500'), $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.quantity', '0.500')
            ->assertJsonPath('data.scanned_price', '25.000');
    }

    public function test_price_label_on_an_unpriced_item_is_rejected(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->id;
        $this->createItem($tenant, '00123', ['selling_price' => '0.000']);
        $this->enableScale($tenant, $storeId, ['scale_value_type' => 'price']);

        $this->getJson(self::URL.'?code='.$this->scaleLabel('00123', '02500'), $this->tenantHeaders($tenant))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('pos.scale_barcode_unpriced_item'));
    }

    public function test_stock_is_scoped_to_the_active_store_and_cost_is_hidden_from_cashiers(): void
    {
        $tenant = $this->createTenant();
        $storeA = (int) $this->tenantStore($tenant)->id;
        $storeB = (int) $this->inTenant($tenant, fn (): int => (int) Store::query()->create([
            'name' => 'فرع B', 'code' => 'B-01', 'type' => 'retail', 'is_main' => false, 'is_active' => true,
        ])->id);
        $id = $this->createItem($tenant, 'S1');
        $this->inTenant($tenant, function () use ($id, $storeA, $storeB): void {
            DB::table('store_stocks')->insert([
                ['store_id' => $storeA, 'item_id' => $id, 'quantity' => '3.000', 'created_at' => now(), 'updated_at' => now()],
                ['store_id' => $storeB, 'item_id' => $id, 'quantity' => '9.000', 'created_at' => now(), 'updated_at' => now()],
            ]);
        });

        $this->getJson(self::URL.'?code=S1', $this->tenantHeaders($tenant, null, $storeB))
            ->assertOk()->assertJsonPath('data.item.current_stock', '9.000');
        $this->getJson(self::URL.'?code=S1', $this->tenantHeaders($tenant, null, $storeA))
            ->assertOk()->assertJsonPath('data.item.current_stock', '3.000');

        $cashier = $this->createTenantUser($tenant, 'cashier');
        $this->getJson(self::URL.'?code=S1', $this->tenantHeaders($tenant, $cashier))
            ->assertOk()->assertJsonPath('data.item.cost_price', null);
    }

    public function test_requires_authentication(): void
    {
        $tenant = $this->createTenant();
        $this->createItem($tenant, 'A1');

        $this->getJson(self::URL.'?code=A1', ['Accept' => 'application/json', 'X-Tenant' => (string) $tenant->getTenantKey()])
            ->assertStatus(401);
    }

    public function test_requires_pos_access_and_store_access(): void
    {
        $tenant = $this->createTenant();
        $storeA = (int) $this->tenantStore($tenant)->id;
        $storeB = (int) $this->inTenant($tenant, fn (): int => (int) Store::query()->create([
            'name' => 'فرع B', 'code' => 'B-01', 'type' => 'retail', 'is_main' => false, 'is_active' => true,
        ])->id);
        $this->createItem($tenant, 'A1');

        $nobody = $this->createTenantUser($tenant);
        $this->getJson(self::URL.'?code=A1', $this->tenantHeaders($tenant, $nobody))->assertStatus(403);

        /** @var User $cashier */
        $cashier = $this->createTenantUser($tenant, 'cashier', [], ['default_store_id' => $storeA]);
        $this->inTenant($tenant, fn () => User::query()->findOrFail($cashier->id)->stores()->sync([$storeA]));

        $this->getJson(self::URL.'?code=A1', $this->tenantHeaders($tenant, $cashier, $storeA))->assertOk();
        $this->getJson(self::URL.'?code=A1', $this->tenantHeaders($tenant, $cashier, $storeB))->assertStatus(403);
    }

    public function test_items_are_isolated_between_tenants(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $this->createItem($tenantA, 'ONLY-A');

        $this->getJson(self::URL.'?code=ONLY-A', $this->tenantHeaders($tenantB))->assertStatus(404);
        $this->getJson(self::URL.'?code=ONLY-A', $this->tenantHeaders($tenantA))->assertOk();
    }
}
