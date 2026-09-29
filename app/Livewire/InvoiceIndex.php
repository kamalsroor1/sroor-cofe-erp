<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Exception;

class InvoiceIndex extends Component
{
    use WithPagination;

    public $search = '';
    public $status = 'all'; // all, confirmed, cancelled
    public $paymentStatus = 'all'; // all, paid, unpaid, partially_paid
    public $paymentMethod = 'all'; // all, cash, instapay, e_wallet, visa, bank_transfer
    public $selectedStore = ''; // all or store_id
    public $filterStatus = 'active'; // active, trashed, all
    public ?string $fromDate = null;
    public ?string $toDate = null;

    public $showCancelModal = false;
    public $cancelInvoiceId;
    public $cancelReason = '';
    public $errorMessage = '';

    public function mount()
    {
        abort_if(!auth()->user()?->can('invoices.view'), 403, 'غير مصرح لك بعرض سجل الفواتير');
    }

    public function openCancelModal($invoiceId)
    {
        if (!auth()->user()?->can('invoices.cancel')) {
            $this->dispatch('swal:toast', ['icon' => 'error', 'title' => 'عفواً، ليس لديك صلاحية إلغاء الفواتير.']);
            return;
        }

        $this->cancelInvoiceId = $invoiceId;
        $this->cancelReason = '';
        $this->errorMessage = '';
        $this->showCancelModal = true;
    }

    public function confirmCancel(InvoiceService $invoiceService)
    {
        abort_if(!auth()->user()?->can('invoices.cancel'), 403, 'غير مصرح لك بإلغاء الفواتير وعكس الأثر المخزني والمالي');

        $this->validate([
            'cancelReason' => 'required|string|min:3',
        ]);

        try {
            $invoice = Invoice::findOrFail($this->cancelInvoiceId);
            $invoiceService->cancelInvoice($invoice, $this->cancelReason);

            $this->showCancelModal = false;
            session()->flash('success', "تم إلغاء الفاتورة رقم {$invoice->invoice_number} وعكس أثرها المخزني والمالي بنجاح.");
            $this->dispatch('swal:toast', ['icon' => 'warning', 'title' => "تم إلغاء الفاتورة {$invoice->invoice_number} وإعادة الرصيد للمخزن!"]);
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
            $this->dispatch('swal:toast', ['icon' => 'error', 'title' => $e->getMessage()]);
        }
    }

    public function deleteInvoice($invoiceId, InvoiceService $invoiceService)
    {
        abort_if(!auth()->user()?->can('invoices.delete'), 403, 'غير مصرح لك بحذف أو أرشفة الفواتير');

        try {
            $invoice = Invoice::findOrFail($invoiceId);
            $num = $invoice->invoice_number;
            $invoiceService->deleteInvoice($invoice);

            session()->flash('success', "تم نقل الفاتورة رقم {$num} إلى سلة المحذوفات وإرجاع المخزون بنجاح.");
            $this->dispatch('swal:toast', [
                'icon'  => 'success',
                'title' => "تم أرشفة الفاتورة {$num} ونقلها لسلة المحذوفات!"
            ]);
        } catch (Exception $e) {
            $this->dispatch('swal:toast', ['icon' => 'error', 'title' => $e->getMessage()]);
        }
    }

    public function restoreInvoice($invoiceId)
    {
        abort_if(!auth()->user()?->can('trash.access'), 403, 'غير مصرح لك باسترجاع الفواتير المحذوفة');

        try {
            // Deleting an invoice reversed its stock, payments and lines; a bare restore() would bring back
            // its totals and COGS without them. Refused (same rule as TrashIndex::restoreInvoice).
            $invoice = Invoice::onlyTrashed()->findOrFail($invoiceId);

            $this->dispatch('swal:toast', [
                'icon'  => 'warning',
                'title' => "لا يمكن استعادة الفاتورة [{$invoice->invoice_number}]: تم عكس أثرها المخزني والمالي عند الحذف. برجاء إنشاء فاتورة جديدة بنفس البيانات.",
            ]);
        } catch (Exception $e) {
            $this->dispatch('swal:toast', ['icon' => 'error', 'title' => $e->getMessage()]);
        }
    }

    public function render()
    {
        $baseQuery = match ($this->filterStatus) {
            'trashed' => Invoice::onlyTrashed(),
            'all'     => Invoice::withTrashed(),
            default   => Invoice::query(),
        };

        $query = $baseQuery->with(['customer', 'user', 'store'])
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('invoice_number', 'like', "%{$this->search}%")
                        ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->status !== 'all', fn($q) => $q->where('status', $this->status))
            ->when($this->paymentStatus !== 'all', fn($q) => $q->where('payment_status', $this->paymentStatus))
            ->when($this->paymentMethod !== 'all', fn($q) => $q->where('payment_method', $this->paymentMethod))
            ->when($this->selectedStore !== '', fn($q) => $q->where('store_id', $this->selectedStore))
            ->when($this->fromDate, fn($q) => $q->whereDate('invoice_date', '>=', $this->fromDate))
            ->when($this->toDate, fn($q) => $q->whereDate('invoice_date', '<=', $this->toDate))
            ->latest('invoice_date');

        return view('livewire.invoice-index', [
            'invoices'     => $query->paginate(15),
            'stores'       => \App\Models\Store::orderBy('is_main', 'desc')->get(),
            'trashedCount' => Invoice::onlyTrashed()->count(),
        ])->layout('components.layouts.app', ['title' => 'سجل فواتير المبيعات']);
    }
}
