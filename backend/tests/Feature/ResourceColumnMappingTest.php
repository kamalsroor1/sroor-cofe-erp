<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Invoices\ProcessPOSInvoiceAction;
use App\Http\Controllers\POSController;
use App\Http\Requests\StorePOSInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\InvoiceSummaryResource;
use App\Http\Resources\PurchaseItemResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PurchaseItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Mockery;
use Tests\TestCase;

/**
 * Regression: resources/controllers read attribute names that are not real
 * columns (customer->balance, invoice->total_amount) and silently emitted 0,
 * and PurchaseItemResource computed money with native float math.
 */
class ResourceColumnMappingTest extends TestCase
{
    private function makeInvoice(): Invoice
    {
        $invoice = new Invoice;
        $invoice->forceFill([
            'id' => 7,
            'invoice_number' => 'INV-7',
            'customer_id' => 3,
            'subtotal' => '1000.000',
            'discount_amount' => '50.000',
            'net_total' => '950.000',
            'paid_amount' => '500.000',
            'remaining_amount' => '450.000',
            'status' => 'confirmed',
        ]);

        $customer = new Customer;
        $customer->forceFill(['id' => 3, 'name' => 'Customer', 'current_balance' => '1250.500']);
        $invoice->setRelation('customer', $customer);

        return $invoice;
    }

    public function test_invoice_resource_exposes_customer_current_balance(): void
    {
        $data = (new InvoiceResource($this->makeInvoice()))->resolve();

        $this->assertSame(1250.5, $data['customer_balance']);
        $this->assertSame(1250.5, $data['customer']['balance']);
    }

    public function test_invoice_summary_resource_total_amount_is_net_total(): void
    {
        $data = (new InvoiceSummaryResource($this->makeInvoice()))->resolve();

        $this->assertSame(950.0, $data['total_amount']);
        $this->assertSame(950.0, $data['net_total']);
    }

    public function test_purchase_item_resource_falls_back_to_exact_quantity_times_cost(): void
    {
        $line = new PurchaseItem;
        $line->forceFill([
            'id' => 1,
            'item_id' => 2,
            'quantity' => '1.100',
            'cost_price' => '1.100',
            'total_price' => null,
        ]);

        $data = (new PurchaseItemResource($line))->resolve();

        // 1.1 * 1.1 in float is 1.2100000000000002; bcmath gives the exact 1.210.
        $this->assertSame(1.21, $data['total_price']);
    }

    public function test_web_pos_store_json_reports_net_total_as_total_amount(): void
    {
        $action = Mockery::mock(ProcessPOSInvoiceAction::class);
        $action->shouldReceive('execute')->once()->andReturn($this->makeInvoice());
        $this->app->instance(ProcessPOSInvoiceAction::class, $action);

        $payload = ['customer_id' => 3, 'store_id' => 1, 'items' => []];
        $request = StorePOSInvoiceRequest::create('/pos/invoices', 'POST', $payload, [], [], ['HTTP_ACCEPT' => 'application/json']);
        $request->setValidator(Validator::make($payload, [
            'customer_id' => 'required',
            'store_id' => 'required',
            'items' => 'present|array',
        ]));

        $response = $this->app->make(POSController::class)->store($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $invoice = $response->getData(true)['invoice'];
        $this->assertSame(950, $invoice['total_amount']);
        $this->assertSame(950, $invoice['net_total']);
    }
}
