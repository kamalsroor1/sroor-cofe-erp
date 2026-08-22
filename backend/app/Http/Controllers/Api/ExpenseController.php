<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Expenses\CreateExpenseAction;
use App\Actions\Expenses\DeleteExpenseAction;
use App\Actions\Expenses\GetExpensesSummaryAction;
use App\Actions\Expenses\UpdateExpenseAction;
use App\DTOs\Expenses\ExpenseDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ExpenseController extends Controller
{
    public function __construct(
        private readonly CreateExpenseAction $createExpenseAction,
        private readonly UpdateExpenseAction $updateExpenseAction,
        private readonly DeleteExpenseAction $deleteExpenseAction,
        private readonly GetExpensesSummaryAction $getExpensesSummaryAction
    ) {}

    /**
     * List / Search Expenses with filters & metrics
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string)$request->input('search', ''));
        $category = (string)$request->input('category', 'all');
        $costCenter = (string)$request->input('cost_center', 'all');
        $paymentMethod = (string)$request->input('payment_method', 'all');
        $fromDate = $request->input('from_date') ?: $request->input('from');
        $toDate = $request->input('to_date') ?: $request->input('to');
        $perPage = (int)$request->input('per_page', 20);

        $storeId = $request->header('X-Store-Id')
            ?: $request->input('store_id')
            ?: auth()->user()?->getCurrentStore()?->id
            ?: Store::getMainStore()?->id;

        $query = Expense::with(['user', 'store']);

        if ($storeId) {
            $query->where('store_id', (int)$storeId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('expense_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($category !== 'all' && $category !== '') {
            $query->where('category', $category);
        }

        if ($costCenter !== 'all' && $costCenter !== '') {
            $query->where('cost_center', $costCenter);
        }

        if ($paymentMethod !== 'all' && $paymentMethod !== '') {
            $query->where('payment_method', $paymentMethod);
        }

        if ($fromDate) {
            $query->whereDate('expense_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('expense_date', '<=', $toDate);
        }

        $expenses = $query->latest('expense_date')->latest('id')->paginate($perPage);

        $summary = $this->getExpensesSummaryAction->execute(
            $storeId ? (int)$storeId : null,
            $fromDate ? (string)$fromDate : null,
            $toDate ? (string)$toDate : null
        );

        $costCentersList = [
            'operational' => 'مصاريف تشغيلية ونثريات',
            'rent'        => 'إيجارات مقرات وفروع',
            'utilities'   => 'كهرباء ومياه وغاز ومرافق',
            'salaries'    => 'رواتب وعمالة وإكراميات',
            'vehicles'    => 'وقود وزيوت وصيانة سيارات',
            'maintenance' => 'صيانة معدات وديكورات',
            'packaging'   => 'مطبوعات وكراتين وتعبئة',
            'hospitality' => 'ضيافة ونظافة وبوفيه',
            'marketing'   => 'تسويق وإعلانات ودعاية',
            'shipping'    => 'شحن ونولون وتوصيل خارجي',
        ];

        $quickCategories = [
            'شنط وأكياس',
            'أكواب ورقية وبلاستيكية',
            'لاصق وشرائط تغليف',
            'بوفيه وضيافة',
            'صيانة مطاحن ومعدات',
            'إيجار وكهرباء ومرافق',
            'نثريات ومصاريف تشغيل',
        ];

        return response()->json([
            'success'          => true,
            'data'             => ExpenseResource::collection($expenses->items())->resolve(),
            'meta'             => [
                'current_page' => $expenses->currentPage(),
                'last_page'    => $expenses->lastPage(),
                'per_page'     => $expenses->perPage(),
                'total'        => $expenses->total(),
            ],
            'summary'          => $summary,
            'cost_centers'     => $costCentersList,
            'quick_categories' => $quickCategories,
        ], 200);
    }

    /**
     * Store new expense
     */
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $storeId = $request->header('X-Store-Id')
            ?: $request->input('store_id')
            ?: auth()->user()?->getCurrentStore()?->id
            ?: Store::getMainStore()?->id;

        $dto = ExpenseDTO::fromArray($request->validated(), $storeId ? (int)$storeId : null);
        $userId = (int)auth()->id();

        $expense = $this->createExpenseAction->execute($dto, $userId);

        return response()->json([
            'success' => true,
            'message' => __('expenses.recorded_success') ?: "تم تسجيل المصروف رقم {$expense->expense_number} بنجاح",
            'data'    => (new ExpenseResource($expense))->resolve(),
        ], 201);
    }

    /**
     * Display specified expense
     */
    public function show(int $id): JsonResponse
    {
        $expense = Expense::with(['user', 'store'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => (new ExpenseResource($expense))->resolve(),
        ], 200);
    }

    /**
     * Update existing expense
     */
    public function update(UpdateExpenseRequest $request, int $id): JsonResponse
    {
        $expense = Expense::findOrFail($id);
        $dto = ExpenseDTO::fromArray($request->validated(), $expense->store_id);

        $updated = $this->updateExpenseAction->execute($expense, $dto);

        return response()->json([
            'success' => true,
            'message' => __('expenses.updated_success') ?: "تم تعديل المصروف [{$updated->title}] بنجاح",
            'data'    => (new ExpenseResource($updated))->resolve(),
        ], 200);
    }

    /**
     * Delete an expense
     */
    public function destroy(int $id): JsonResponse
    {
        $expense = Expense::findOrFail($id);
        $this->deleteExpenseAction->execute($expense);

        return response()->json([
            'success' => true,
            'message' => __('expenses.deleted_success') ?: 'تم حذف المصروف بنجاح',
        ], 200);
    }
}
