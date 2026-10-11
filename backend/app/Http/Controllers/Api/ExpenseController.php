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
use App\Models\User;
use App\Support\ActiveStore;
use App\Support\ClientStoreGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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
    public function index(Request $request, ActiveStore $activeStore): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('expenses.manage') && ! $user->can('daily_journal.view')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $search = trim((string) $request->input('search', ''));
        $category = (string) $request->input('category', 'all');
        $costCenter = (string) $request->input('cost_center', 'all');
        $paymentMethod = (string) $request->input('payment_method', 'all');
        $fromDate = $request->input('from_date') ?: $request->input('from');
        $toDate = $request->input('to_date') ?: $request->input('to');
        $perPage = max(1, min(200, (int) $request->input('per_page', 20)));

        // STOR-2: the active store (X-Store-Id); a query store_id is an access-checked branch
        // filter (403). Null (every store) only through a verified `all`.
        $storeId = $activeStore->readFilter($request);

        $query = Expense::with(['user', 'store']);

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
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
            $storeId,
            $fromDate ? (string) $fromDate : null,
            $toDate ? (string) $toDate : null
        );

        $costCentersList = [
            'operational' => 'مصاريف تشغيلية ونثريات',
            'rent' => 'إيجارات مقرات وفروع',
            'utilities' => 'كهرباء ومياه وغاز ومرافق',
            'salaries' => 'رواتب وعمالة وإكراميات',
            'vehicles' => 'وقود وزيوت وصيانة سيارات',
            'maintenance' => 'صيانة معدات وديكورات',
            'packaging' => 'مطبوعات وكراتين وتعبئة',
            'hospitality' => 'ضيافة ونظافة وبوفيه',
            'marketing' => 'تسويق وإعلانات ودعاية',
            'shipping' => 'شحن ونولون وتوصيل خارجي',
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
            'success' => true,
            'data' => ExpenseResource::collection($expenses->items())->resolve(),
            'meta' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
            ],
            'summary' => $summary,
            'cost_centers' => $costCentersList,
            'quick_categories' => $quickCategories,
        ], 200);
    }

    /**
     * Store new expense
     */
    public function store(StoreExpenseRequest $request, ActiveStore $activeStore): JsonResponse
    {
        // STOR-2 (CTO Q14): the expense is booked on the active store only (store.active:concrete).
        // A body store_id is still access-checked (403) but never chooses the store.
        ClientStoreGuard::verified($request);

        $dto = ExpenseDTO::fromArray($request->validated(), (int) $activeStore->requireStore()->id);
        $userId = (int) auth()->id();

        $expense = $this->createExpenseAction->execute($dto, $userId);

        return response()->json([
            'success' => true,
            'message' => __('expenses.recorded_success'),
            'data' => (new ExpenseResource($expense))->resolve(),
        ], 201);
    }

    /**
     * Display specified expense
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('expenses.manage') && ! $user->can('daily_journal.view')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        if ($user instanceof User) {
            $this->findExpenseOr404($user, $id, 'view');
        }

        $expense = Expense::with(['user', 'store'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => (new ExpenseResource($expense))->resolve(),
        ], 200);
    }

    /**
     * Update existing expense
     */
    public function update(UpdateExpenseRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user instanceof User) {
            $this->findExpenseOr404($user, $id, 'update');
        }

        $expense = Expense::findOrFail($id);
        $dto = ExpenseDTO::fromArray($request->validated(), $expense->store_id);

        $updated = $this->updateExpenseAction->execute($expense, $dto);

        return response()->json([
            'success' => true,
            'message' => __('expenses.updated_success'),
            'data' => (new ExpenseResource($updated))->resolve(),
        ], 200);
    }

    /**
     * Delete an expense
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('expenses.manage')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        if ($user instanceof User) {
            $this->findExpenseOr404($user, $id, 'delete');
        }

        $expense = Expense::findOrFail($id);
        $this->deleteExpenseAction->execute($expense);

        return response()->json([
            'success' => true,
            'message' => __('expenses.deleted_success'),
        ], 200);
    }

    /**
     * STOR-2 (CTO Q13): the permission was already checked (403), so a policy denial here can only
     * be the expense's store: answered exactly like a missing expense (404), never revealing it.
     */
    private function findExpenseOr404(User $user, int $id, string $ability): void
    {
        $expense = Expense::query()->select(['id', 'store_id'])->find($id);

        if (! $expense instanceof Expense || ! Gate::forUser($user)->allows($ability, $expense)) {
            throw (new ModelNotFoundException)->setModel(Expense::class, [$id]);
        }
    }
}
