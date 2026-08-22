<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Suppliers\CreateSupplierAction;
use App\Actions\Suppliers\DeleteSupplierAction;
use App\Actions\Suppliers\GetSupplierStatementAction;
use App\Actions\Suppliers\PaySupplierAction;
use App\Actions\Suppliers\ToggleSupplierActiveAction;
use App\Actions\Suppliers\UpdateSupplierAction;
use App\DTOs\Suppliers\PaySupplierDTO;
use App\DTOs\Suppliers\SupplierDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaySupplierRequest;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SupplierController extends Controller
{
    public function __construct(
        private readonly CreateSupplierAction $createSupplierAction,
        private readonly UpdateSupplierAction $updateSupplierAction,
        private readonly DeleteSupplierAction $deleteSupplierAction,
        private readonly ToggleSupplierActiveAction $toggleSupplierActiveAction,
        private readonly PaySupplierAction $paySupplierAction,
        private readonly GetSupplierStatementAction $getSupplierStatementAction
    ) {}

    /**
     * List / Search Suppliers with filters & metrics
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string)$request->input('search', ''));
        $debtStatus = (string)$request->input('debt_status', 'all');
        $status = (string)$request->input('status', 'all');
        $perPage = (int)$request->input('per_page', 20);

        $query = Supplier::withCount(['purchases', 'payments']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($debtStatus === 'creditor') {
            $query->where('current_balance', '>', 0);
        } elseif ($debtStatus === 'zero') {
            $query->where('current_balance', '=', 0);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $suppliers = $query->latest('id')->paginate($perPage);

        $totalPayable = (float)Supplier::where('current_balance', '>', 0)->sum('current_balance');
        $creditorsCount = Supplier::where('current_balance', '>', 0)->count();
        $totalSuppliersCount = Supplier::count();

        return response()->json([
            'success' => true,
            'data'    => SupplierResource::collection($suppliers->items())->resolve(),
            'meta'    => [
                'current_page' => $suppliers->currentPage(),
                'last_page'    => $suppliers->lastPage(),
                'per_page'     => $suppliers->perPage(),
                'total'        => $suppliers->total(),
            ],
            'summary' => [
                'total_payable'       => $totalPayable,
                'creditors_count'     => $creditorsCount,
                'total_suppliers'     => $totalSuppliersCount,
            ],
        ], 200);
    }

    /**
     * Store a newly created supplier
     */
    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $dto = SupplierDTO::fromArray($request->validated());
        $supplier = $this->createSupplierAction->execute($dto);

        return response()->json([
            'success' => true,
            'message' => __('contacts.supplier_added') ?: 'تم إضافة المورد بنجاح',
            'data'    => (new SupplierResource($supplier))->resolve(),
        ], 201);
    }

    /**
     * Display the specified supplier
     */
    public function show(int $id): JsonResponse
    {
        $supplier = Supplier::withCount(['purchases', 'payments'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => (new SupplierResource($supplier))->resolve(),
        ], 200);
    }

    /**
     * Update the specified supplier
     */
    public function update(UpdateSupplierRequest $request, int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);
        $dto = SupplierDTO::fromArray($request->validated());
        $updatedSupplier = $this->updateSupplierAction->execute($supplier, $dto);

        return response()->json([
            'success' => true,
            'message' => __('contacts.supplier_updated') ?: 'تم تعديل بيانات المورد بنجاح',
            'data'    => (new SupplierResource($updatedSupplier))->resolve(),
        ], 200);
    }

    /**
     * Record payment to supplier
     */
    public function pay(PaySupplierRequest $request, int $id): JsonResponse
    {
        $dto = PaySupplierDTO::fromArray($id, $request->validated());
        $result = $this->paySupplierAction->execute($dto);

        return response()->json([
            'success' => true,
            'message' => __('contacts.supplier_payment_recorded') ?: 'تم تسجيل سند الصرف بنجاح',
            'data'    => [
                'supplier' => (new SupplierResource($result['supplier']))->resolve(),
                'payment'  => $result['payment'],
            ],
        ], 200);
    }

    /**
     * Get Supplier Statement of Account (Ledger)
     */
    public function statement(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);
        $fromDate = $request->query('from_date') ?: $request->query('from');
        $toDate = $request->query('to_date') ?: $request->query('to');

        $data = $this->getSupplierStatementAction->execute($supplier, $fromDate, $toDate);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ], 200);
    }

    /**
     * Toggle Supplier Active Status
     */
    public function toggleActive(int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);
        $toggled = $this->toggleSupplierActiveAction->execute($supplier);

        return response()->json([
            'success' => true,
            'message' => __('contacts.supplier_status_updated') ?: 'تم تحديث حالة المورد بنجاح',
            'data'    => (new SupplierResource($toggled))->resolve(),
        ], 200);
    }

    /**
     * Delete the specified supplier
     */
    public function destroy(int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);
        $this->deleteSupplierAction->execute($supplier);

        return response()->json([
            'success' => true,
            'message' => __('contacts.supplier_deleted') ?: 'تم حذف المورد بنجاح',
        ], 200);
    }
}
