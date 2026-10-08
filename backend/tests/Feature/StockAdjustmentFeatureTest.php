<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected StockService $stockService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->stockService = app(StockService::class);
    }

    public function test_adjust_stock_with_surplus_increases_stock_and_logs_inbound_movement(): void
    {
        $item = Item::create([
            'code' => 'ITM-ADJ-PLUS',
            'name' => 'شاي أسود كيني فاخر',
            'current_stock' => '20.000',
            'cost_price' => '50.000',
            'selling_price' => '80.000',
            'is_active' => true,
        ]);

        // Physical count shows 25 kg (+5 kg difference)
        $movement = $this->stockService->adjustStock(
            item: $item,
            actualQuantity: '25.000',
            reason: 'زيادة جرد دوري معتمد'
        );

        $item->refresh();
        $this->assertEquals('25.000', $item->current_stock);
        $this->assertEquals('stock_adjustment_in', $movement->movement_type);
        $this->assertEquals('5.000', $movement->quantity);
        $this->assertEquals('20.000', $movement->stock_before);
        $this->assertEquals('25.000', $movement->stock_after);
    }

    public function test_adjust_stock_with_deficit_decreases_stock_and_logs_outbound_movement(): void
    {
        $item = Item::create([
            'code' => 'ITM-ADJ-MINUS',
            'name' => 'بن محوج خاص',
            'current_stock' => '50.000',
            'cost_price' => '150.000',
            'selling_price' => '220.000',
            'is_active' => true,
        ]);

        // Physical count shows 47.5 kg (-2.5 kg deficit)
        $movement = $this->stockService->adjustStock(
            item: $item,
            actualQuantity: '47.500',
            reason: 'عجز جرد وهالك تشغيل'
        );

        $item->refresh();
        $this->assertEquals('47.500', $item->current_stock);
        $this->assertEquals('stock_adjustment_out', $movement->movement_type);
        $this->assertEquals('2.500', $movement->quantity);
        $this->assertEquals('50.000', $movement->stock_before);
        $this->assertEquals('47.500', $movement->stock_after);
    }

    public function test_adjust_stock_with_identical_quantity_throws_exception(): void
    {
        $item = Item::create([
            'code' => 'ITM-ADJ-SAME',
            'name' => 'أكواب ورقية 8 أونص',
            'current_stock' => '100.000',
            'cost_price' => '1.000',
            'selling_price' => '2.000',
            'is_active' => true,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('مطابق تماماً للرصيد المسجل');

        $this->stockService->adjustStock(
            item: $item,
            actualQuantity: '100.000',
            reason: 'تسوية وهمية'
        );
    }
}
