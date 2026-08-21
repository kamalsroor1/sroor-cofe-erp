<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockTransferRequest;
use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Services\StockTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class StockTransferController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string)$request->input('search', ''));
        $fromStore = $request->input('from_store_id');
        $toStore = $request->input('to_store_id');
        $dateFrom = $request->input('from');
        $dateTo = $request->input('to');

        $query = StockTransfer::with(['fromStore', 'toStore', 'items.item', 'user']);

        if ($search !== '') {
            $query->where('transfer_number', 'like', "%{$search}%");
        }

        if ($fromStore && $fromStore !== 'all') {
            $query->where('from_store_id', (int)$fromStore);
        }

        if ($toStore && $toStore !== 'all') {
            $query->where('to_store_id', (int)$toStore);
        }

        if ($dateFrom) {
            $query->whereDate('transfer_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('transfer_date', '<=', $dateTo);
        }

        $transfers = $query->latest('id')->paginate(15)->withQueryString();
        $stores = Store::where('is_active', true)->select('id', 'name')->get();

        return Inertia::render('StockTransfers/Index', [
            'transfers' => $transfers->through(fn($t) => [
                'id'              => $t->id,
                'transfer_number' => $t->transfer_number,
                'from_store_name' => $t->fromStore?->name,
                'to_store_name'   => $t->toStore?->name,
                'transfer_date'   => $t->transfer_date ? $t->transfer_date->toDateString() : $t->created_at->toDateString(),
                'status'          => $t->status,
                'items_count'     => $t->items->count(),
                'user_name'       => $t->user?->name,
                'notes'           => $t->notes,
                'items'           => $t->items->map(fn($it) => [
                    'id'        => $it->id,
                    'item_name' => $it->item?->name,
                    'quantity'  => (float)$it->quantity,
                ]),
            ]),
            'stores'    => $stores,
            'filters'   => [
                'search'        => $search,
                'from_store_id' => $fromStore,
                'to_store_id'   => $toStore,
                'from'          => $dateFrom,
                'to'            => $dateTo,
            ],
        ]);
    }

    public function create(): Response
    {
        $stores = Store::where('is_active', true)->select('id', 'name', 'type')->get();
        $items = Item::where('is_active', true)->select('id', 'name', 'code', 'unit', 'current_stock')->get();

        return Inertia::render('StockTransfers/Create', [
            'stores' => $stores,
            'items'  => $items,
        ]);
    }

    public function store(StoreStockTransferRequest $request, StockTransferService $transferService): RedirectResponse
    {
        $validated = $request->validated();
        $transfer = $transferService->createTransfer($validated);

        return redirect()->route('stock-transfers.index')->with('success', __('inventory.confirm_transfer') ?: "تم تنفيذ إذن التحويل المخزني رقم {$transfer->transfer_number} بنجاح");
    }
}