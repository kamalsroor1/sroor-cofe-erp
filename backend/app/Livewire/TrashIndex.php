<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Item;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Store;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\ReturnDocument;
use App\Livewire\Traits\RequiresAuth;

class TrashIndex extends Component
{
    use WithPagination, RequiresAuth;

    public string $activeTab = 'items'; // items, customers, suppliers, stores, invoices, purchases, expenses, returns
    public string $search = '';

    public function mount()
    {
        abort_if(!auth()->user()?->can('trash.access'), 403, 'غير مصرح لك بالوصول لسلة المحذوفات المركزية');
    }

    public function setTab(string $tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function restoreItem(int $id)
    {
        $item = Item::onlyTrashed()->findOrFail($id);
        $item->restore();
        $this->dispatch('swal:toast', ['icon' => 'success', 'title' => "تم استعادة الصنف [{$item->name}] بنجاح!"]);
    }

    public function restoreCustomer(int $id)
    {
        $customer = Customer::onlyTrashed()->findOrFail($id);
        $customer->restore();
        $this->dispatch('swal:toast', ['icon' => 'success', 'title' => "تم استعادة العميل [{$customer->name}] بنجاح!"]);
    }

    public function restoreSupplier(int $id)
    {
        $supplier = Supplier::onlyTrashed()->findOrFail($id);
        $supplier->restore();
        $this->dispatch('swal:toast', ['icon' => 'success', 'title' => "تم استعادة المورد [{$supplier->name}] بنجاح!"]);
    }

    public function restoreStore(int $id)
    {
        $store = Store::onlyTrashed()->findOrFail($id);
        $store->restore();
        $this->dispatch('swal:toast', ['icon' => 'success', 'title' => "تم استعادة الفرع [{$store->name}] بنجاح!"]);
    }

    public function restoreInvoice(int $id)
    {
        $invoice = Invoice::onlyTrashed()->findOrFail($id);
        $invoice->restore();
        $this->dispatch('swal:toast', ['icon' => 'success', 'title' => "تم استعادة الفاتورة [{$invoice->invoice_number}] بنجاح!"]);
    }

    public function restorePurchase(int $id)
    {
        $purchase = Purchase::onlyTrashed()->findOrFail($id);
        $purchase->restore();
        $this->dispatch('swal:toast', ['icon' => 'success', 'title' => "تم استعادة فاتورة الشراء [{$purchase->purchase_number}] بنجاح!"]);
    }

    public function restoreExpense(int $id)
    {
        $expense = Expense::onlyTrashed()->findOrFail($id);
        $expense->restore();
        $this->dispatch('swal:toast', ['icon' => 'success', 'title' => "تم استعادة المصروف [{$expense->title}] بنجاح!"]);
    }

    public function restoreReturn(int $id)
    {
        $return = ReturnDocument::onlyTrashed()->findOrFail($id);
        $return->restore();
        $this->dispatch('swal:toast', ['icon' => 'success', 'title' => "تم استعادة المرتجع [{$return->return_number}] بنجاح!"]);
    }

    public function render()
    {
        $counts = [
            'items'     => Item::onlyTrashed()->count(),
            'customers' => Customer::onlyTrashed()->count(),
            'suppliers' => Supplier::onlyTrashed()->count(),
            'stores'    => Store::onlyTrashed()->count(),
            'invoices'  => Invoice::onlyTrashed()->count(),
            'purchases' => Purchase::onlyTrashed()->count(),
            'expenses'  => Expense::onlyTrashed()->count(),
            'returns'   => ReturnDocument::onlyTrashed()->count(),
        ];

        $records = match ($this->activeTab) {
            'customers' => Customer::onlyTrashed()
                ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('phone', 'like', "%{$this->search}%"))
                ->latest('deleted_at')
                ->paginate(15),
            'suppliers' => Supplier::onlyTrashed()
                ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('company_name', 'like', "%{$this->search}%"))
                ->latest('deleted_at')
                ->paginate(15),
            'stores' => Store::onlyTrashed()
                ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%"))
                ->latest('deleted_at')
                ->paginate(15),
            'invoices' => Invoice::onlyTrashed()
                ->with(['customer', 'user', 'store'])
                ->when($this->search, fn($q) => $q->where('invoice_number', 'like', "%{$this->search}%"))
                ->latest('deleted_at')
                ->paginate(15),
            'purchases' => Purchase::onlyTrashed()
                ->with(['supplier', 'items.item', 'user'])
                ->when($this->search, fn($q) => $q->where('purchase_number', 'like', "%{$this->search}%"))
                ->latest('deleted_at')
                ->paginate(15),
            'expenses' => Expense::onlyTrashed()
                ->with(['user', 'store'])
                ->when($this->search, fn($q) => $q->where('title', 'like', "%{$this->search}%")->orWhere('expense_number', 'like', "%{$this->search}%"))
                ->latest('deleted_at')
                ->paginate(15),
            'returns' => ReturnDocument::onlyTrashed()
                ->with(['customer', 'supplier', 'items.item'])
                ->when($this->search, fn($q) => $q->where('return_number', 'like', "%{$this->search}%"))
                ->latest('deleted_at')
                ->paginate(15),
            default => Item::onlyTrashed()
                ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%"))
                ->latest('deleted_at')
                ->paginate(15),
        };

        return view('livewire.trash-index', [
            'counts'  => $counts,
            'records' => $records,
            'totalTrashed' => array_sum($counts),
        ])->layout('components.layouts.app', ['title' => '🗑️ سلة المحذوفات المركزية | منظومة ERP']);
    }
}
