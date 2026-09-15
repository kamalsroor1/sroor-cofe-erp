<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Actions\Invoices\ProcessPOSInvoiceAction;
use App\Actions\Customers\QuickCreateCustomerAction;
use App\Actions\Customers\GetCustomerLastSoldPriceAction;
use App\DTOs\POSInvoiceDTO;
use App\Http\Requests\StorePOSInvoiceRequest;
use App\Http\Requests\StoreQuickCustomerRequest;
use Exception;

class POSController extends Controller
{
    public function __construct(
        protected ProcessPOSInvoiceAction $processInvoiceAction,
        protected QuickCreateCustomerAction $quickCreateCustomerAction,
        protected GetCustomerLastSoldPriceAction $getCustomerLastPriceAction
    ) {}

    /**
     * Serve the Vue 3 SPA shell; the cashier screen pulls its bootstrap
     * payload from Api\PosController.
     */
    public function index(): View
    {
        return view('app');
    }

    /**
     * Submit and confirm POS invoice atomically
     */
    public function store(StorePOSInvoiceRequest $request)
    {
        $dto = POSInvoiceDTO::fromArray($request->validated());

        try {
            $invoice = $this->processInvoiceAction->execute($dto);

            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => __('pos.invoice_saved_success'),
                    'invoice' => [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'total_amount' => (float)$invoice->total_amount,
                        'net_total' => (float)$invoice->net_total,
                        'paid_amount' => (float)$invoice->paid_amount,
                        'remaining_amount' => (float)$invoice->remaining_amount,
                        'print_thermal_url' => route('invoices.print.thermal', $invoice->id),
                        'print_a4_url' => route('invoices.print.a4', $invoice->id),
                    ]
                ]);
            }

            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', __('pos.invoice_confirmed_success', ['number' => $invoice->invoice_number]));

        } catch (Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Fast Quick Customer Registration from POS Screen
     */
    public function storeCustomer(StoreQuickCustomerRequest $request)
    {
        $customer = $this->quickCreateCustomerAction->execute($request->validated());

        return response()->json([
            'status' => 'success',
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'price_tier' => $customer->price_tier,
                'current_balance' => 0,
            ]
        ]);
    }

    /**
     * Get Last Sold Price for customer on specific item
     */
    public function getCustomerLastPrice(Request $request)
    {
        $lastPrice = $this->getCustomerLastPriceAction->execute(
            customerId: (int)$request->query('customer_id'),
            itemId: (int)$request->query('item_id'),
            storeId: $request->query('store_id') ? (int)$request->query('store_id') : null
        );

        return response()->json([
            'last_price' => $lastPrice
        ]);
    }
}
