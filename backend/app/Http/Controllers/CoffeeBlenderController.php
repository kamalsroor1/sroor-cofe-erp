<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateBlenderInvoiceRequest;
use App\Models\Customer;
use App\Models\Item;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class CoffeeBlenderController extends Controller
{
    public function index(): Response
    {
        $items = Item::where('is_active', true)
            ->whereNull('deleted_at')
            ->select('id', 'name', 'code', 'category', 'unit', 'cost_price', 'selling_price', 'current_stock')
            ->orderBy('name')
            ->get();

        $customers = Customer::where('is_active', true)
            ->whereNull('deleted_at')
            ->select('id', 'name', 'phone')
            ->orderBy('name')
            ->get();

        return Inertia::render('CoffeeBlender/Index', [
            'items'     => $items,
            'customers' => $customers,
        ]);
    }

    public function createInvoice(CreateBlenderInvoiceRequest $request, InvoiceService $invoiceService): RedirectResponse
    {
        $validated = $request->validated();
        $itemsForInvoice = [];

        foreach ($validated['components'] as $comp) {
            $kg = bcdiv((string)$comp['grams'], '1000', 4);
            if (bccomp($kg, '0.000', 4) > 0) {
                $itemsForInvoice[] = [
                    'item_id'         => (int)$comp['item_id'],
                    'quantity'        => $kg,
                    'unit_price'      => (string)$comp['unit_price'],
                    'discount_amount' => '0.000',
                ];
            }
        }

        $notesStr = "خلطة وتوليفة مخصوصة: {$validated['blend_name']}" . (!empty($validated['notes']) ? " - {$validated['notes']}" : '');

        $invoice = $invoiceService->confirmInvoice([
            'customer_id'    => (int)$validated['customer_id'],
            'invoice_date'   => now()->toDateString(),
            'items'          => $itemsForInvoice,
            'payment_method' => 'cash',
            'paid_amount'    => '0.000',
            'discount_type'  => 'fixed',
            'discount_value' => '0.000',
            'notes'          => $notesStr,
        ]);

        return redirect()->route('invoices.show', $invoice->id)->with('success', __('inventory.assembly_blends_sub') ?: 'تم إنشاء وتأكيد فاتورة التوليفة بنجاح');
    }
}