<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CancelInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceSummaryResource;
use App\Models\Invoice;
use App\Models\Store;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class InvoiceController extends Controller
{
    /**
     * Display a listing of sales invoices with dynamic filters.
     */
    public function index(Request $request): Response
    {
        $search = trim((string)$request->input('search', ''));
        $storeId = $request->input('store_id');
        $paymentType = $request->input('payment_type');
        $paymentMethod = $request->input('payment_method');
        $status = $request->input('status', 'active');
        $dateFrom = $request->input('from');
        $dateTo = $request->input('to');

        $query = Invoice::with(['customer', 'store', 'user']);

        if ($status === 'trash') {
            $query->onlyTrashed();
        } else {
            $query->withoutTrashed();
            if ($status === 'confirmed' || $status === 'cancelled') {
                $query->where('status', $status);
            }
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        if ($storeId && $storeId !== 'all') {
            $query->where('store_id', (int)$storeId);
        }

        if ($paymentType && $paymentType !== 'all') {
            $query->where('payment_type', $paymentType);
        }

        if ($paymentMethod && $paymentMethod !== 'all') {
            $query->where('payment_method', $paymentMethod);
        }

        if ($dateFrom) {
            $query->whereDate('invoice_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('invoice_date', '<=', $dateTo);
        }

        $statsQuery = clone $query;
        $stats = [
            'total_count' => (int)$statsQuery->count(),
            'total_net' => (float)$statsQuery->where('status', '!=', 'cancelled')->sum('net_total'),
            'total_paid' => (float)$statsQuery->where('status', '!=', 'cancelled')->sum('paid_amount'),
            'total_remaining' => (float)$statsQuery->where('status', '!=', 'cancelled')->sum('remaining_amount'),
        ];

        $invoices = $query->latest('id')->paginate(15)->withQueryString();

        $stores = Store::where('is_active', true)->get(['id', 'name', 'type', 'is_main']);

        return Inertia::render('Invoices/Index', [
            'invoices' => InvoiceSummaryResource::collection($invoices),
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'store_id' => $storeId ?: 'all',
                'payment_type' => $paymentType ?: 'all',
                'payment_method' => $paymentMethod ?: 'all',
                'status' => $status,
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'stores' => $stores,
        ]);
    }

    /**
     * Display the specified invoice details.
     */
    public function show(int $id): Response
    {
        $invoice = Invoice::with([
            'customer',
            'store',
            'user',
            'items.item',
            'additionalExpenses',
            'payments.user'
        ])->findOrFail($id);

        return Inertia::render('Invoices/Show', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date->toDateString(),
                'formatted_created_at' => $invoice->created_at->format('Y-m-d H:i'),
                'customer' => $invoice->customer ? [
                    'id' => $invoice->customer->id,
                    'name' => $invoice->customer->name,
                    'phone' => $invoice->customer->phone,
                    'balance' => (float)$invoice->customer->balance,
                ] : null,
                'store' => [
                    'id' => $invoice->store->id,
                    'name' => $invoice->store->name,
                ],
                'cashier_name' => $invoice->user?->name ?? 'الكاشير',
                'payment_type' => $invoice->payment_type,
                'payment_method' => $invoice->payment_method,
                'status' => $invoice->status,
                'total_amount' => (float)$invoice->total_amount,
                'discount_amount' => (float)$invoice->discount_amount,
                'net_total' => (float)($invoice->net_total ?? $invoice->total_amount),
                'paid_amount' => (float)$invoice->paid_amount,
                'remaining_amount' => (float)$invoice->remaining_amount,
                'notes' => $invoice->notes,
                'items' => $invoice->items->map(fn($item) => [
                    'id' => $item->id,
                    'item_name' => $item->item?->name ?? 'صنف محذوف',
                    'item_code' => $item->item?->code,
                    'unit' => $item->item?->unit ?? 'كجم',
                    'quantity' => (float)$item->quantity,
                    'unit_price' => (float)$item->unit_price,
                    'total_price' => (float)$item->total_price,
                    'notes' => $item->notes,
                ]),
                'expenses' => $invoice->additionalExpenses->map(fn($exp) => [
                    'id' => $exp->id,
                    'title' => $exp->title,
                    'amount' => (float)$exp->amount,
                ]),
                'payments' => $invoice->payments->map(fn($pay) => [
                    'id' => $pay->id,
                    'amount' => (float)$pay->amount,
                    'payment_method' => $pay->payment_method,
                    'payment_date' => $pay->payment_date->toDateString(),
                    'user_name' => $pay->user?->name,
                ]),
            ],
        ]);
    }

    public function edit(int $id): Response
    {
        $invoice = Invoice::with([
            'customer',
            'store',
            'items.item',
            'additionalExpenses'
        ])->findOrFail($id);

        if ($invoice->status === 'cancelled') {
            abort(400, 'لا يمكن تعديل فاتورة ملغاة.');
        }

        $customers = \App\Models\Customer::where('is_active', true)->select('id', 'name', 'phone')->get();
        $items = \App\Models\Item::where('is_active', true)->select('id', 'name', 'code', 'selling_price', 'current_stock', 'unit')->get();

        return Inertia::render('Invoices/Edit', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => $invoice->customer_id,
                'invoice_date' => $invoice->invoice_date->toDateString(),
                'payment_type' => $invoice->payment_type,
                'payment_method' => $invoice->payment_method,
                'discount_type' => $invoice->discount_type ?: 'fixed',
                'discount_value' => (float)$invoice->discount_value,
                'paid_amount' => (float)$invoice->paid_amount,
                'notes' => $invoice->notes,
                'items' => $invoice->items->map(fn($item) => [
                    'item_id' => $item->item_id,
                    'name' => $item->item?->name ?? 'صنف',
                    'code' => $item->item?->code,
                    'unit' => $item->item?->unit ?? 'كجم',
                    'current_stock' => (float)($item->item?->current_stock ?? 0),
                    'quantity' => (float)$item->quantity,
                    'unit_price' => (float)$item->unit_price,
                    'discount_amount' => (float)($item->discount_amount ?: 0),
                    'total_price' => (float)$item->total_price,
                ]),
                'additional_expenses' => $invoice->additionalExpenses->map(fn($exp) => [
                    'title' => $exp->title,
                    'amount' => (float)$exp->amount,
                ]),
            ],
            'customers' => $customers,
            'items_catalog' => $items,
        ]);
    }

    public function update(UpdateInvoiceRequest $request, int $id, InvoiceService $invoiceService): RedirectResponse
    {
        $invoice = Invoice::findOrFail($id);
        $validated = $request->validated();
        $updated = $invoiceService->updateInvoice($invoice, $validated);

        return redirect()->route('invoices.show', $updated->id)->with('success', __('invoices.updated_success', ['number' => $updated->invoice_number]));
    }

    /**
     * Cancel an invoice and safely reverse stock and financials.
     */
    public function cancel(CancelInvoiceRequest $request, int $id, InvoiceService $invoiceService): RedirectResponse
    {
        $validated = $request->validated();
        $invoice = Invoice::findOrFail($id);
        $invoiceService->cancelInvoice($invoice, $validated['reason']);

        return redirect()->back()->with('success', __('invoices.cancelled_success', ['number' => $invoice->invoice_number]));
    }

    /**
     * Archive/Delete an invoice and reverse stock.
     */
    public function destroy(int $id, \App\Services\InvoiceService $invoiceService)
    {
        abort_if(!auth()->user()?->can('invoices.delete'), 403, __('invoices.unauthorized_delete') ?? 'غير مصرح لك بحذف الفواتير');

        $invoice = Invoice::findOrFail($id);
        $num = $invoice->invoice_number;
        $invoiceService->deleteInvoice($invoice);

        return redirect()->back()->with('success', __('invoices.deleted_success', ['number' => $num]));
    }

    /**
     * Restore an archived/trashed invoice.
     */
    public function restore(int $id)
    {
        abort_if(!auth()->user()?->can('trash.access'), 403, __('common.unauthorized') ?? 'غير مصرح لك باسترجاع الفواتير المحذوفة');

        $invoice = Invoice::onlyTrashed()->findOrFail($id);
        $invoice->restore();

        return redirect()->back()->with('success', __('invoices.restored_success', ['number' => $invoice->invoice_number]));
    }
}
