<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ItemMovementsAndReportsPrintFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Permissions
        Permission::firstOrCreate(['name' => 'items.view']);
        Permission::firstOrCreate(['name' => 'reports.view']);

        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->givePermissionTo(['items.view', 'reports.view']);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
        $this->actingAs($this->user);

        $this->item = Item::create([
            'code'          => 'ITM-MOV-01',
            'name'          => 'بن جواتيمالا فخم',
            'current_stock' => '30.000',
            'cost_price'    => '250.000',
            'selling_price' => '350.000',
            'is_active'     => true,
        ]);
    }

    public function test_item_movements_page_renders_successfully(): void
    {
        $response = $this->get(route('items.movements', $this->item->id));
        $response->assertStatus(200);
    }

    public function test_item_movements_a4_print_page_renders_successfully(): void
    {
        $response = $this->get(route('items.movements.print', $this->item->id));
        $response->assertStatus(200);
        $response->assertSee('كارت حركة الصنف');
        $response->assertSee('بن جواتيمالا فخم');
    }

    public function test_item_movements_csv_export_returns_streamed_download(): void
    {
        $response = $this->get(route('items.movements.export', $this->item->id));
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_all_reports_a4_print_tabs_render_successfully(): void
    {
        $tabs = ['sales', 'items', 'stores', 'customers', 'expenses', 'inventory'];

        foreach ($tabs as $tab) {
            $response = $this->get(route('reports.print', ['tab' => $tab]));
            $response->assertStatus(200);
            $response->assertSee('A4');
        }
    }

    public function test_internal_transfers_do_not_inflate_company_total_inbound_outbound(): void
    {
        $store1 = Store::create(['name' => 'المخزن الرئيسي', 'code' => 'STR-MAIN', 'is_main' => true, 'is_active' => true]);
        $store2 = Store::create(['name' => 'عربية 1', 'code' => 'VAN-01', 'type' => 'wholesale_van', 'is_active' => true]);

        // 1. Initial Deposit to Company: 150 kg
        StockMovement::create([
            'item_id'       => $this->item->id,
            'store_id'      => $store1->id,
            'movement_type' => 'stock_deposit_in',
            'quantity'      => '150.000',
            'stock_before'  => '0.000',
            'stock_after'   => '150.000',
            'unit_cost'     => '100.000',
            'source_type'   => Item::class,
            'source_id'     => $this->item->id,
            'user_id'       => $this->user->id,
        ]);

        // 2. Sales: 3 kg
        StockMovement::create([
            'item_id'       => $this->item->id,
            'store_id'      => $store1->id,
            'movement_type' => 'sales_out',
            'quantity'      => '3.000',
            'stock_before'  => '150.000',
            'stock_after'   => '147.000',
            'unit_cost'     => '100.000',
            'source_type'   => Item::class,
            'source_id'     => $this->item->id,
            'user_id'       => $this->user->id,
        ]);

        // 3. Internal Transfer of 10 kg from Store 1 to Store 2
        StockMovement::create([
            'item_id'       => $this->item->id,
            'store_id'      => $store1->id,
            'movement_type' => 'transfer_out',
            'quantity'      => '10.000',
            'stock_before'  => '147.000',
            'stock_after'   => '137.000',
            'unit_cost'     => '100.000',
            'source_type'   => Item::class,
            'source_id'     => $this->item->id,
            'user_id'       => $this->user->id,
        ]);
        StockMovement::create([
            'item_id'       => $this->item->id,
            'store_id'      => $store2->id,
            'movement_type' => 'transfer_in',
            'quantity'      => '10.000',
            'stock_before'  => '0.000',
            'stock_after'   => '10.000',
            'unit_cost'     => '100.000',
            'source_type'   => Item::class,
            'source_id'     => $this->item->id,
            'user_id'       => $this->user->id,
        ]);

        // When viewing ALL stores: Inbound must be 150.000 (NOT 160.000), Outbound must be 3.000 (NOT 13.000)
        \Livewire\Livewire::test(\App\Livewire\ItemMovements::class, ['id' => $this->item->id])
            ->set('selectedStoreId', 'all')
            ->set('datePreset', 'all')
            ->assertViewHas('totalIn', '150.000')
            ->assertViewHas('totalOut', '3.000')
            ->assertViewHas('netMovement', '147.000');

        // When viewing Store 1 specifically: Outbound includes transfer_out (3 sales + 10 transfer = 13.000)
        \Livewire\Livewire::test(\App\Livewire\ItemMovements::class, ['id' => $this->item->id])
            ->set('selectedStoreId', (string)$store1->id)
            ->set('datePreset', 'all')
            ->assertViewHas('totalIn', '150.000')
            ->assertViewHas('totalOut', '13.000')
            ->assertViewHas('netMovement', '137.000');

        // When viewing Store 2 specifically: Inbound is the 10 kg transfer
        \Livewire\Livewire::test(\App\Livewire\ItemMovements::class, ['id' => $this->item->id])
            ->set('selectedStoreId', (string)$store2->id)
            ->set('datePreset', 'all')
            ->assertViewHas('totalIn', '10.000')
            ->assertViewHas('totalOut', '0.000')
            ->assertViewHas('netMovement', '10.000');
    }
}
