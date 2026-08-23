<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Expense;
use Illuminate\Support\Facades\Auth;
use App\Livewire\Traits\RequiresAuth;

#[Layout('components.layouts.app')]
#[Title('سجل المصروفات والنثريات | منظومة ERP')]
class ExpenseIndex extends Component
{
    use WithPagination, RequiresAuth;

    public string $search = '';
    public string $filterCategory = 'all';
    public string $filterCostCenter = 'all';
    public string $filterPaymentMethod = 'all'; // all, cash, instapay, e_wallet, visa, bank_transfer, check
    public string $filterStatus = 'active'; // active, trashed, all
    public ?string $fromDate = null;
    public ?string $toDate = null;

    // Modal state
    public bool $showModal = false;
    public bool $isEditMode = false;
    public ?int $editExpenseId = null;

    // Form fields
    public string $category = 'شنط وأكياس';
    public string $cost_center = 'operational';
    public string $title = '';
    public string $amount = '0.000';
    public string $expense_date = '';
    public string $payment_method = 'cash';
    public string $notes = '';

    public array $quickCategories = [
        'شنط وأكياس',
        'أكواب ورقية وبلاستيكية',
        'لاصق وشرائط تغليف',
        'بوفيه وضيافة',
        'صيانة مطاحن ومعدات',
        'إيجار وكهرباء ومرافق',
        'نثريات ومصاريف تشغيل',
    ];

    public array $costCenters = [
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

    public function mount()
    {
        abort_if(!auth()->user()?->can('expenses.manage'), 403, 'غير مصرح لك بإدارة المصروفات');
        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->toDateString();
        $this->expense_date = now()->toDateString();
    }

    protected function rules(): array
    {
        return [
            'category'       => 'required|string|max:100',
            'cost_center'    => 'required|string|max:50',
            'title'          => 'required|string|max:255',
            'amount'         => 'required|numeric|min:0.01',
            'expense_date'   => 'required|date',
            'payment_method' => 'required|string|max:50',
            'notes'          => 'nullable|string|max:1000',
        ];
    }

    protected function messages(): array
    {
        return [
            'category.required'     => 'يرجى اختيار تصنيف المصروف.',
            'cost_center.required'  => 'يرجى تحديد مركز التكلفة.',
            'title.required'        => 'يرجى إدخال اسم البند / بيان الصرف (مثل: شراء شنط أو أكواب).',
            'amount.required'       => 'يرجى تحديد المبلغ المصروف.',
            'amount.min'            => 'المبلغ يجب أن يكون أكبر من الصفر.',
            'expense_date.required' => 'يرجى تحديد تاريخ المصروف.',
        ];
    }

    public function openCreateModal()
    {
        abort_if(!auth()->user()?->can('expenses.manage'), 403, 'غير مصرح لك بتسجيل مصروفات جديدة');
        $this->resetValidation();
        $this->reset(['title', 'notes', 'editExpenseId']);
        $this->isEditMode = false;
        $this->category = 'شنط وأكياس';
        $this->cost_center = 'packaging';
        $this->amount = '0.000';
        $this->expense_date = now()->toDateString();
        $this->payment_method = 'cash';
        $this->showModal = true;
    }

    public function selectQuickCategory(string $cat)
    {
        $this->category = $cat;
        if (empty($this->title)) {
            $this->title = 'شراء ' . $cat;
        }

        // Auto-assign smart cost center
        $this->cost_center = match ($cat) {
            'شنط وأكياس', 'أكواب ورقية وبلاستيكية', 'لاصق وشرائط تغليف' => 'packaging',
            'بوفيه وضيافة' => 'hospitality',
            'صيانة مطاحن ومعدات' => 'maintenance',
            'إيجار وكهرباء ومرافق' => 'utilities',
            default => 'operational',
        };
    }

    public function selectQuickAmount($val)
    {
        $this->amount = (string)$val;
    }

    public function openEditModal(int $id)
    {
        abort_if(!auth()->user()?->can('expenses.manage'), 403, 'غير مصرح لك بتعديل المصروفات');
        $this->resetValidation();
        $expense = Expense::findOrFail($id);
        $this->isEditMode = true;
        $this->editExpenseId = $expense->id;
        $this->category = $expense->category;
        $this->cost_center = $expense->cost_center ?: 'operational';
        $this->title = $expense->title;
        $this->amount = (string)$expense->amount;
        $this->expense_date = $expense->expense_date->format('Y-m-d');
        $this->payment_method = $expense->payment_method;
        $this->notes = $expense->notes ?? '';
        $this->showModal = true;
    }

    public function saveExpense()
    {
        abort_if(!auth()->user()?->can('expenses.manage'), 403, 'غير مصرح لك بحفظ المصروفات');
        $this->validate();

        $currentStoreId = session('current_store_id') ?? auth()->user()?->getCurrentStore()?->id ?? 1;

        if ($this->isEditMode && $this->editExpenseId) {
            $expense = Expense::findOrFail($this->editExpenseId);
            $expense->update([
                'category'       => $this->category,
                'cost_center'    => $this->cost_center,
                'title'          => $this->title,
                'amount'         => $this->amount,
                'expense_date'   => $this->expense_date,
                'payment_method' => $this->payment_method,
                'notes'          => $this->notes,
            ]);

            $this->dispatch('swal:toast', [
                'type'  => 'success',
                'title' => 'تم تعديل المصروف!',
                'text'  => "تم تحديث بيان المصروف [{$expense->title}] بنجاح."
            ]);
        } else {
            $prefix = 'EXP-' . date('Ymd');
            $count = Expense::whereDate('created_at', now()->toDateString())->count() + 1;
            $expenseNumber = $prefix . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            $expense = Expense::create([
                'expense_number' => $expenseNumber,
                'category'       => $this->category,
                'cost_center'    => $this->cost_center,
                'title'          => $this->title,
                'amount'         => $this->amount,
                'expense_date'   => $this->expense_date,
                'payment_method' => $this->payment_method,
                'store_id'       => $currentStoreId,
                'user_id'        => Auth::id() ?? 1,
                'notes'          => $this->notes,
            ]);

            $this->dispatch('swal:toast', [
                'type'  => 'success',
                'title' => 'تم تسجيل المصروف!',
                'text'  => "تم إضافة بند المصروف [{$expense->title}] بمبلغ " . number_format($expense->amount, 2) . " ج.م بنجاح."
            ]);

            app(\App\Services\ActivityLogService::class)->log(
                module: 'expenses',
                action: 'created',
                description: "تم تسجيل مصروف تشغيلي جديد [{$expense->title}] بقيمة " . number_format((float)$expense->amount, 2) . " ج.م",
                subject: $expense
            );
        }

        $this->showModal = false;
        $this->reset(['editExpenseId', 'title', 'amount', 'notes']);
    }

    public function deleteExpense(int $id)
    {
        abort_if(!auth()->user()?->can('expenses.manage'), 403, 'غير مصرح لك بحذف المصروفات');
        $expense = Expense::findOrFail($id);
        $title = $expense->title;
        $amount = $expense->amount;
        $expense->delete(); // Soft delete

        app(\App\Services\ActivityLogService::class)->log(
            module: 'expenses',
            action: 'deleted',
            description: "تم نقل بيان المصروف [{$title}] بمبلغ " . number_format((float)$amount, 2) . " ج.م إلى سلة المحذوفات",
            subject: $expense
        );

        $this->dispatch('swal:toast', [
            'type'  => 'success',
            'title' => 'تم أرشفة المصروف!',
            'text'  => "تم نقل بيان المصروف [{$title}] إلى سلة المحذوفات بنجاح."
        ]);
    }

    public function restoreExpense(int $id)
    {
        abort_if(!auth()->user()?->can('trash.access'), 403, 'غير مصرح لك باسترجاع المصروفات المحذوفة');
        $expense = Expense::onlyTrashed()->findOrFail($id);
        $expense->restore();

        $this->dispatch('swal:toast', [
            'type'  => 'success',
            'title' => 'تم استعادة المصروف!',
            'text'  => "تم استعادة بيان المصروف [{$expense->title}] بنجاح."
        ]);
    }

    public function render()
    {
        $baseQuery = match ($this->filterStatus) {
            'trashed' => Expense::onlyTrashed(),
            'all'     => Expense::withTrashed(),
            default   => Expense::query(),
        };

        $query = $baseQuery->with(['user', 'store'])
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('title', 'like', "%{$this->search}%")
                        ->orWhere('expense_number', 'like', "%{$this->search}%")
                        ->orWhere('category', 'like', "%{$this->search}%")
                        ->orWhere('notes', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterCategory !== 'all', fn($q) => $q->where('category', $this->filterCategory))
            ->when($this->filterCostCenter !== 'all', fn($q) => $q->where('cost_center', $this->filterCostCenter))
            ->when($this->filterPaymentMethod !== 'all', fn($q) => $q->where('payment_method', $this->filterPaymentMethod))
            ->when($this->fromDate, fn($q) => $q->whereDate('expense_date', '>=', $this->fromDate))
            ->when($this->toDate, fn($q) => $q->whereDate('expense_date', '<=', $this->toDate));

        $totalExpenses = (clone $query)->sum('amount') ?: '0.000';
        $expensesCount = (clone $query)->count();

        return view('livewire.expense-index', [
            'expenses'      => $query->latest('expense_date')->paginate(15),
            'totalExpenses' => $totalExpenses,
            'expensesCount' => $expensesCount,
            'trashedCount'  => Expense::onlyTrashed()->count(),
        ]);
    }
}
