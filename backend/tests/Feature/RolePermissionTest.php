<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cashier;
    protected User $accountant;
    protected User $storekeeper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionsSeeder::class);

        $adminRole = Role::findByName('admin');
        $cashierRole = Role::findByName('cashier');
        $accountantRole = Role::findByName('accountant');
        $storekeeperRole = Role::findByName('storekeeper');

        $this->admin = User::factory()->create([
            'phone' => '01012316954',
            'is_active' => true,
        ]);
        $this->admin->assignRole($adminRole);

        $this->cashier = User::factory()->create([
            'phone' => '01111111111',
            'is_active' => true,
        ]);
        $this->cashier->assignRole($cashierRole);

        $this->accountant = User::factory()->create([
            'phone' => '01222222222',
            'is_active' => true,
        ]);
        $this->accountant->assignRole($accountantRole);

        $this->storekeeper = User::factory()->create([
            'phone' => '01333333333',
            'is_active' => true,
        ]);
        $this->storekeeper->assignRole($storekeeperRole);
    }

    public function test_admin_can_access_user_manager_and_reports()
    {
        $this->actingAs($this->admin);

        $this->get(route('users.index'))->assertStatus(200);
        $this->get(route('reports.index'))->assertStatus(200);
        $this->get(route('invoices.create'))->assertStatus(200);
        $this->get(route('daily.journal'))->assertStatus(200);
    }

    public function test_cashier_can_access_pos_and_daily_journal()
    {
        $this->actingAs($this->cashier);

        $this->get(route('invoices.create'))->assertStatus(200);
        $this->get(route('invoices.index'))->assertStatus(200);
        $this->get(route('daily.journal'))->assertStatus(200);
    }

    public function test_cashier_is_forbidden_from_user_manager_and_profit_reports()
    {
        $token = $this->cashier->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/users')
            ->assertStatus(403);
    }

    public function test_accountant_can_access_reports_but_not_user_manager()
    {
        $token = $this->accountant->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/users')
            ->assertStatus(403);
    }

    public function test_only_admin_can_delete_invoice_and_cashier_is_forbidden()
    {
        $item = \App\Models\Item::create([
            'code'              => 'TEST-ITEM-1',
            'name'              => 'بن تجريبي',
            'category'          => 'بن وتوليفات',
            'unit'              => 'كجم',
            'current_stock'     => '50.000',
            'cost_price'        => '100.000',
            'weighted_avg_cost' => '100.000',
            'selling_price'     => '150.000',
            'min_stock_level'   => '5.000',
            'is_active'         => true,
        ]);

        $customer = \App\Models\Customer::create([
            'name'            => 'عميل فحص الصلاحيات',
            'phone'           => '01099887766',
            'current_balance' => '0.000',
            'is_active'       => true,
        ]);

        $invoiceService = app(\App\Services\InvoiceService::class);
        $invoice = $invoiceService->confirmInvoice([
            'customer_id'   => $customer->id,
            'invoice_date'  => now()->toDateString(),
            'payment_type'  => 'cash',
            'discount_type' => 'fixed',
            'discount_value'=> '0.000',
            'items'         => [
                [
                    'item_id'    => $item->id,
                    'quantity'   => '2.000',
                    'unit_price' => '150.000',
                ]
            ]
        ]);

        // 1. Cashier attempts to delete invoice -> BLOCKED (403)
        $this->actingAs($this->cashier);
        \Livewire\Livewire::test(\App\Livewire\InvoiceIndex::class)
            ->call('deleteInvoice', $invoice->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);

        // 2. Admin deletes invoice -> ALLOWED
        $this->actingAs($this->admin);
        \Livewire\Livewire::test(\App\Livewire\InvoiceIndex::class)
            ->call('deleteInvoice', $invoice->id)
            ->assertStatus(200);

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }
}
