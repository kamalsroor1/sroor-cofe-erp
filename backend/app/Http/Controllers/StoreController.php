<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class StoreController extends Controller
{
    public function index(): Response
    {
        $stores = Store::with(['users' => fn($q) => $q->select('users.id', 'users.name')])
            ->withCount(['stocks', 'invoices', 'purchases'])
            ->orderBy('is_main', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        $allUsers = \App\Models\User::where('is_active', true)->select('id', 'name', 'email')->get();

        return Inertia::render('Stores/Index', [
            'stores' => $stores->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'type' => $s->type, // retail_shop, wholesale_van, main_warehouse, branch, warehouse, van
                'address' => $s->address,
                'phone' => $s->phone,
                'is_active' => (bool)$s->is_active,
                'is_main' => (bool)$s->is_main,
                'stocks_count' => $s->stocks_count,
                'invoices_count' => $s->invoices_count,
                'purchases_count' => $s->purchases_count,
                'assigned_user_ids' => $s->users->pluck('id')->toArray(),
                'assigned_users' => $s->users->map(fn($u) => ['id' => $u->id, 'name' => $u->name]),
                'can_be_deleted' => $s->canBeDeleted(),
                'deletion_blockers' => $s->getDeletionBlockers(),
            ]),
            'all_users' => $allUsers,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:stores,code',
            'type' => 'required|string',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'is_main' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($validated) {
            $isMain = !empty($validated['is_main']);
            if ($isMain) {
                Store::where('is_main', true)->update(['is_main' => false]);
            }

            $store = Store::create([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? strtoupper(substr($validated['type'], 0, 3)) . '-' . rand(100, 999),
                'type' => $validated['type'],
                'address' => $validated['address'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'is_active' => true,
                'is_main' => $isMain,
            ]);

            if (auth()->check()) {
                $store->users()->syncWithoutDetaching([auth()->id()]);
            }
        });

        return redirect()->back()->with('success', __('inventory.store_added') ?? 'تم إضافة الفرع / عربية التوزيع بنجاح');
    }

    public function update(Request $request, int $id)
    {
        $store = Store::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:stores,code,' . $store->id,
            'type' => 'required|string',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'is_main' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($store, $validated) {
            $isMain = !empty($validated['is_main']);
            if ($isMain && !$store->is_main) {
                Store::where('id', '!=', $store->id)->update(['is_main' => false]);
            }

            $store->update([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? $store->code,
                'type' => $validated['type'],
                'address' => $validated['address'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'is_active' => isset($validated['is_active']) ? (bool)$validated['is_active'] : $store->is_active,
                'is_main' => $isMain,
            ]);
        });

        return redirect()->back()->with('success', __('inventory.store_updated') ?? 'تم تعديل بيانات الفرع بنجاح');
    }

    public function toggleActive(int $id)
    {
        $store = Store::findOrFail($id);
        if ($store->is_main && $store->is_active) {
            return redirect()->back()->with('error', __('inventory.cannot_disable_main_store') ?? 'لا يمكن تعطيل الفرع الرئيسي للمنشأة');
        }

        $store->update(['is_active' => !$store->is_active]);

        return redirect()->back()->with('success', __('inventory.store_status_updated') ?? "تم تحديث حالة الفرع ({$store->name}) بنجاح");
    }

    public function assignUsers(Request $request, int $id)
    {
        $store = Store::findOrFail($id);
        $validated = $request->validate([
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $store->users()->sync($validated['user_ids'] ?? []);

        return redirect()->back()->with('success', __('inventory.store_users_updated') ?? "تم تحديث تعيينات الموظفين لفرع ({$store->name}) بنجاح");
    }

    public function destroy(int $id)
    {
        $store = Store::findOrFail($id);

        if (!$store->canBeDeleted()) {
            $blockers = implode(', ', $store->getDeletionBlockers());
            return redirect()->back()->with('error', "لا يمكن حذف الفرع ({$store->name}) لوجود ارتباطات: {$blockers}");
        }

        $store->delete();

        return redirect()->back()->with('success', __('inventory.store_deleted') ?? "تم نقل الفرع ({$store->name}) إلى سلة المحذوفات بنجاح");
    }

    public function stocks(Request $request): Response
    {
        $storeId = $request->input('store_id');
        $search = trim((string)$request->input('search', ''));
        $stockStatus = $request->input('stock_status', 'all');

        $stores = Store::where('is_active', true)->select('id', 'name', 'type')->get();
        $selectedStoreId = $storeId ? (int)$storeId : ($stores->first()?->id ?? 1);

        $query = StoreStock::with('item')
            ->where('store_id', $selectedStoreId);

        if ($search !== '') {
            $query->whereHas('item', fn($iq) => $iq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
        }

        if ($stockStatus === 'low') {
            $query->whereHas('item', fn($iq) => $iq->whereColumn('store_stocks.quantity', '<=', 'items.min_stock_level'));
        } elseif ($stockStatus === 'out') {
            $query->where('quantity', '<=', 0);
        }

        return Inertia::render('Stores/Stocks', [
            'stores' => $stores,
            'selected_store_id' => $selectedStoreId,
            'filters' => [
                'store_id' => $selectedStoreId,
                'search' => $search,
                'stock_status' => $stockStatus,
            ],
            'stocks' => Inertia::defer(fn() => $query->paginate(20)->withQueryString()->through(fn($st) => [
                'id' => $st->id,
                'item_name' => $st->item?->name,
                'item_code' => $st->item?->code,
                'unit' => $st->item?->unit,
                'quantity' => (float)$st->quantity,
                'min_stock_level' => (float)$st->item?->min_stock_level,
                'cost_price' => (float)$st->item?->cost_price,
                'total_valuation' => (float)($st->quantity * ($st->item?->cost_price ?? 0)),
            ]), 'storeStocksData'),
        ]);
    }
}