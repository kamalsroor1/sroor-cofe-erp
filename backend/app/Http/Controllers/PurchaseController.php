<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseRequest;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Item;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PurchaseController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string)$request->input('search', ''));
        $status = $request->input('status', 'all');
        $supplierId = $request->input('supplier_id');
        $dateFrom = $request->input('from');
        $dateTo = $request->input('to');

        $query = Purchase::with(['supplier', 'items.item', 'user', 'store']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('purchase_number', 'like', "%{$search}%")
                  ->orWhere('supplier_invoice_ref', 'like', "%{$search}%")
                  ->orWhereHas('supplier', fn($sq) => $sq->where('name', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%"));
            });
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($supplierId && $supplierId !== 'all') {
            $query->where('supplier_id', (int)$supplierId);
        }

        if ($dateFrom) {
            $query->whereDate('purchase_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('purchase_date', '<=', $dateTo);
        }

        $purchases = $query->latest('id')->paginate(15)->withQueryString();

        $totalPurchases = (float)Purchase::where('status', 'confirmed')->sum('net_total');
        $confirmedCount = Purchase::where('status', 'confirmed')->count();
        $unpaidTotal = (float)Purchase::where('status', 'confirmed')->sum('remaining_amount');

        $suppliers = Supplier::where('is_active', true)->select('id', 'name', 'company_name')->get();

        return Inertia::render('Purchases/Index', [
            'purchases' => $purchases->through(fn($p) => [
                'id' => $p->id,
                'purchase_number' => $p->purchase_number,
                'supplier_name' => $p->supplier?->name ?: 'مورد عام',
                'company_name' => $p->supplier?->company_name,
                'purchase_date' => $p->purchase_date->toDateString(),
                'net_total' => (float)$p->net_total,
                'paid_amount' => (float)$p->paid_amount,
                'remaining_amount' => (float)$p->remaining_amount,
                'status' => $p->status,
                'payment_status' => $p->payment_status,
                'items_count' => $p->items->count(),
                'user_name' => $p->user?->name,
                'store_name' => $p->store?->name,
                'items' => $p->items->map(fn($item) => [
                    'id' => $item->id,
                    'item_name' => $item->item?->name,
                    'quantity' => (float)$item->quantity,
                    'unit_cost' => (float)$item->unit_cost,
                    'subtotal' => (float)$item->subtotal,
                ]),
            ]),
            'metrics' => [
                'total_purchases' => $totalPurchases,
                'confirmed_count' => $confirmedCount,
                'unpaid_total' => $unpaidTotal,
            ],
            'suppliers' => $suppliers->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->company_name ? "{$s->name} ({$s->company_name})" : $s->name,
            ]),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'supplier_id' => $supplierId,
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $suppliers = Supplier::where('is_active', true)
            ->select('id', 'name', 'company_name', 'phone')
            ->orderBy('name')
            ->get();

        $items = Item::where('is_active', true)
            ->select('id', 'name', 'code', 'category', 'unit', 'cost_price', 'current_stock')
            ->orderBy('name')
            ->get();

        $prefillItems = [];
        if ($request->has('prefill')) {
            $rawPrefill = json_decode($request->query('prefill', '[]'), true) ?: [];
            foreach ($rawPrefill as $p) {
                $itemId = $p['item_id'] ?? $p['id'] ?? null;
                $item = $items->firstWhere('id', $itemId);
                if ($item) {
                    $prefillItems[] = [
                        'item_id' => $item->id,
                        'name' => $item->name,
                        'unit' => $item->unit ?: 'كجم',
                        'quantity' => (float)($p['quantity'] ?? $p['suggested_quantity'] ?? 10),
                        'unit_cost' => (float)$item->cost_price,
                    ];
                }
            }
        }

        return Inertia::render('Purchases/Create', [
            'suppliers' => $suppliers->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->company_name ? "{$s->name} - {$s->company_name}" : $s->name,
            ]),
            'items' => $items,
            'prefill_items' => $prefillItems,
        ]);
    }

    public function store(StorePurchaseRequest $request, PurchaseService $purchaseService): RedirectResponse
    {
        $validated = $request->validated();
        $purchase = $purchaseService->createPurchase($validated);

        return redirect()->route('purchases.index')->with('success', __('purchases.created_success', ['number' => $purchase->purchase_number]));
    }

    public function cancel(int $id, PurchaseService $purchaseService)
    {
        $purchase = Purchase::findOrFail($id);

        $purchaseService->cancelPurchase($purchase, __('purchases.cancelled_via_dashboard') ?? 'إلغاء من لوحة التحكم');

        return redirect()->back()->with('success', __('purchases.cancelled_success', ['number' => $purchase->purchase_number]));
    }

    public function smartReorder(Request $request, \App\Services\ReorderAssistantService $reorderService): Response
    {
        $storeId = $request->input('store_id');
        $storeFilter = ($storeId && $storeId !== 'all') ? (int)$storeId : null;
        $analysisDays = (int)$request->input('analysis_days', 14);
        $targetCoverDays = (int)$request->input('target_cover_days', 15);
        $urgency = $request->input('urgency', 'all');
        $search = trim((string)$request->input('search', ''));

        $data = $reorderService->getReorderSuggestions(
            storeId: $storeFilter,
            analysisDays: $analysisDays,
            targetCoverDays: $targetCoverDays
        );

        $suggestions = collect($data['suggestions']);

        if ($search !== '') {
            $suggestions = $suggestions->filter(fn($it) => 
                str_contains(mb_strtolower($it['name']), mb_strtolower($search)) ||
                str_contains(mb_strtolower($it['code']), mb_strtolower($search))
            );
        }

        if ($urgency !== 'all') {
            $suggestions = $suggestions->where('urgency', $urgency);
        }

        $stores = \App\Models\Store::where('is_active', true)->select('id', 'name')->get();

        return Inertia::render('Purchases/SmartReorder', [
            'stores' => $stores,
            'filters' => [
                'store_id' => $storeId ?: 'all',
                'analysis_days' => $analysisDays,
                'target_cover_days' => $targetCoverDays,
                'urgency' => $urgency,
                'search' => $search,
            ],
            'suggestions' => Inertia::defer(fn() => $suggestions->values(), 'smartReorderData'),
            'metrics' => Inertia::defer(fn() => [
                'critical_count' => $data['critical_count'] ?? 0,
                'warning_count' => $data['warning_count'] ?? 0,
                'safe_count' => $data['safe_count'] ?? 0,
                'total_estimated_cost' => (float)$data['total_estimated_cost'],
            ], 'smartReorderData'),
        ]);
    }
}