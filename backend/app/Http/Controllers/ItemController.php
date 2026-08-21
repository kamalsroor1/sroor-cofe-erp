<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\Item;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class ItemController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string)$request->input('search', ''));
        $category = $request->input('category', 'all');
        $stockStatus = $request->input('stock_status', 'all');
        $status = $request->input('status', 'all');

        $query = Item::with(['storeStocks.store']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        if ($stockStatus === 'low') {
            $query->whereColumn('current_stock', '<=', 'min_stock_level')->where('current_stock', '>', 0);
        } elseif ($stockStatus === 'out') {
            $query->where('current_stock', '<=', 0);
        } elseif ($stockStatus === 'in_stock') {
            $query->where('current_stock', '>', 0);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $items = $query->latest('id')->paginate(20)->withQueryString();

        $categories = Item::whereNotNull('category')->where('category', '!=', '')->distinct()->pluck('category')->values();

        // Calculate Quick Metrics
        $totalItemsCount = Item::count();
        $lowStockCount = Item::whereColumn('current_stock', '<=', 'min_stock_level')->where('is_active', true)->count();
        $totalStockValue = (float)Item::selectRaw('SUM(current_stock * cost_price) as total_val')->value('total_val');

        return Inertia::render('Items/Index', [
            'items' => $items->through(fn($item) => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'category' => $item->category,
                'unit' => $item->unit ?? 'كجم',
                'current_stock' => (float)$item->current_stock,
                'cost_price' => (float)$item->cost_price,
                'selling_price' => (float)$item->selling_price,
                'min_stock_level' => (float)$item->min_stock_level,
                'is_active' => (bool)$item->is_active,
                'is_low_stock' => $item->isLowStock(),
                'notes' => $item->notes,
                'store_stocks' => $item->storeStocks->map(fn($ss) => [
                    'store_id' => $ss->store_id,
                    'store_name' => $ss->store?->name,
                    'quantity' => (float)$ss->quantity,
                    'custom_selling_price' => $ss->custom_selling_price ? (float)$ss->custom_selling_price : null,
                ]),
                'can_be_deleted' => $item->canBeDeleted(),
                'deletion_blockers' => $item->getDeletionBlockers(),
            ]),
            'categories' => $categories,
            'metrics' => [
                'total_items' => $totalItemsCount,
                'low_stock_count' => $lowStockCount,
                'total_stock_value' => $totalStockValue,
            ],
            'filters' => [
                'search' => $search,
                'category' => $category ?: 'all',
                'stock_status' => $stockStatus ?: 'all',
                'status' => $status ?: 'all',
            ],
        ]);
    }

    public function store(StoreItemRequest $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            Item::create([
                'name'              => $validated['name'],
                'code'              => $validated['code'] ?? null,
                'category'          => $validated['category'] ?? null,
                'unit'              => $validated['unit'],
                'cost_price'        => $validated['cost_price'],
                'weighted_avg_cost' => $validated['cost_price'],
                'selling_price'     => $validated['selling_price'],
                'min_stock_level'   => $validated['min_stock_level'] ?? '0.000',
                'current_stock'     => '0.000',
                'is_active'         => true,
                'notes'             => $validated['notes'] ?? null,
            ]);
        });

        return redirect()->back()->with('success', __('inventory.item_added'));
    }

    public function update(UpdateItemRequest $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $item = Item::findOrFail($id);
        $validated = $request->validated();

        DB::transaction(function () use ($item, $validated) {
            $item->update($validated);
        });

        return redirect()->back()->with('success', __('inventory.item_updated'));
    }

    public function destroy(int $id)
    {
        $item = Item::findOrFail($id);

        if (!$item->canBeDeleted()) {
            return redirect()->back()->with('error', __('inventory.cannot_delete_has_movements'));
        }

        DB::transaction(function () use ($item) {
            $item->delete();
        });

        return redirect()->back()->with('success', __('inventory.item_deleted'));
    }

    public function movements(int $id, Request $request): Response
    {
        $item = Item::withTrashed()->findOrFail($id);
        $dateFrom = $request->input('from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('to', now()->toDateString());
        $storeId = $request->input('store_id', 'all');
        $movementType = $request->input('type', 'all');

        $storeFilter = ($storeId !== 'all' && is_numeric($storeId)) ? (int)$storeId : null;

        $inTypes = [
            'purchase_in', 'stock_deposit_in', 'stock_adjustment_in',
            'cancellation_in', 'transfer_in', 'sales_return_in', 'purchase_restore_in'
        ];
        $outTypes = [
            'sales_out', 'waste_out', 'stock_adjustment_out',
            'transfer_out', 'purchase_cancel_out', 'purchase_return_out'
        ];

        $query = \App\Models\StockMovement::with(['user', 'store'])
            ->where('item_id', $item->id);

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($storeFilter) {
            $query->where('store_id', $storeFilter);
        }

        if ($movementType !== 'all') {
            $query->where('movement_type', $movementType);
        }

        // Stats calculation for the filtered scope
        $statsQuery = clone $query;
        $allMovements = $statsQuery->get();

        $totalIn = '0.000';
        $totalOut = '0.000';

        foreach ($allMovements as $mov) {
            if (in_array($mov->movement_type, $inTypes)) {
                $totalIn = bcadd($totalIn, (string)$mov->quantity, 3);
            } elseif (in_array($mov->movement_type, $outTypes)) {
                $totalOut = bcadd($totalOut, (string)$mov->quantity, 3);
            }
        }

        $netMovement = bcsub($totalIn, $totalOut, 3);

        $currentScopeStock = $storeFilter
            ? (float)(\App\Models\StoreStock::where('store_id', $storeFilter)->where('item_id', $item->id)->value('quantity') ?: 0)
            : (float)$item->current_stock;

        $movements = $query->latest('id')->paginate(20)->withQueryString();
        $stores = Store::where('is_active', true)->select('id', 'name')->get();

        return Inertia::render('Items/Movements', [
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'code' => $item->code,
                'category' => $item->category,
                'unit' => $item->unit,
                'current_stock' => (float)$item->current_stock,
                'cost_price' => (float)$item->cost_price,
                'selling_price' => (float)$item->selling_price,
            ],
            'stores' => $stores,
            'filters' => [
                'from' => $dateFrom,
                'to' => $dateTo,
                'store_id' => $storeId,
                'type' => $movementType,
            ],
            'movements' => Inertia::defer(fn() => $query->latest('id')->paginate(20)->withQueryString()->through(fn($m) => [
                'id' => $m->id,
                'movement_type' => $m->movement_type,
                'quantity' => (float)$m->quantity,
                'stock_before' => (float)$m->stock_before,
                'stock_after' => (float)$m->stock_after,
                'unit_cost' => (float)$m->unit_cost,
                'document_number' => $m->document_number,
                'user_name' => $m->user?->name ?: 'النظام',
                'store_name' => $m->store?->name,
                'notes' => $m->notes,
                'created_at' => $m->created_at->format('Y-m-d H:i:s'),
            ]), 'itemMovementsData'),
            'stats' => Inertia::defer(fn() => [
                'total_in' => (float)$totalIn,
                'total_out' => (float)$totalOut,
                'net_movement' => (float)$netMovement,
                'current_scope_stock' => $currentScopeStock,
            ], 'itemMovementsData'),
        ]);
    }
}
