<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Quotation;
use App\Services\QuotationService;
use App\Livewire\Traits\RequiresAuth;
use Exception;

class QuotationIndex extends Component
{
    use WithPagination, RequiresAuth;

    public $search = '';
    public $statusFilter = 'all';
    public $tierFilter = 'all';

    protected $queryString = [
        'search'       => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'tierFilter'   => ['except' => 'all'],
    ];

    public function mount()
    {
        abort_if(!auth()->user()?->can('invoices.view') && !auth()->user()?->can('pos.access'), 403, 'غير مصرح لك بعرض عروض الأسعار');
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingTierFilter()
    {
        $this->resetPage();
    }

    public function convertQuotationToInvoice($quotationId, QuotationService $service)
    {
        try {
            $quotation = Quotation::with('items')->findOrFail($quotationId);

            if ($quotation->isConverted()) {
                $this->dispatch('swal:toast', [
                    'icon'  => 'warning',
                    'title' => 'تم تحويل عرض السعر هذا مسبقاً إلى فاتورة مبيعات.',
                ]);
                return;
            }

            $invoice = $service->convertToInvoice($quotation);

            $this->dispatch('swal:toast', [
                'icon'  => 'success',
                'title' => "تم بنجاح تحويل عرض السعر إلى فاتورة مبيعات معتمدة برقم: {$invoice->invoice_number} ✅",
            ]);

            return redirect()->route('invoices.show', $invoice->id);
        } catch (Exception $e) {
            $this->dispatch('swal:toast', [
                'icon'  => 'error',
                'title' => 'حدث خطأ أثناء التحويل: ' . $e->getMessage(),
            ]);
        }
    }

    public function sendWhatsApp($quotationId, QuotationService $service)
    {
        $quotation = Quotation::with(['customer', 'items.item'])->findOrFail($quotationId);
        $rawMsg = $service->formatWhatsAppMessage($quotation);
        $encoded = urlencode($rawMsg);
        $phone = preg_replace('/[^0-9]/', '', $quotation->target_customer_phone ?? '');
        if (str_starts_with($phone, '01')) {
            $phone = '2' . $phone;
        }
        $waUrl = $phone 
            ? "https://api.whatsapp.com/send?phone={$phone}&text={$encoded}"
            : "https://api.whatsapp.com/send?text={$encoded}";

        $quotation->update(['status' => 'sent']);

        $this->dispatch('open-window', ['url' => $waUrl]);
    }

    public function deleteQuotation($id)
    {
        $quotation = Quotation::findOrFail($id);
        if ($quotation->isConverted()) {
            $this->dispatch('swal:toast', [
                'icon'  => 'error',
                'title' => 'لا يمكن حذف عرض سعر تم تحويله لفاتورة مبيعات.',
            ]);
            return;
        }
        $quotation->delete();
        $this->dispatch('swal:toast', [
            'icon'  => 'success',
            'title' => 'تم حذف عرض السعر بنجاح.',
        ]);
    }

    public function render()
    {
        $query = Quotation::with(['customer', 'store', 'user', 'convertedInvoice', 'items.item'])
            ->when($this->search, function ($q) {
                $term = trim($this->search);
                $q->where(function ($sub) use ($term) {
                    $sub->where('quotation_number', 'like', "%{$term}%")
                        ->orWhere('customer_name', 'like', "%{$term}%")
                        ->orWhere('customer_phone', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$term}%"));
                });
            })
            ->when($this->statusFilter !== 'all', function ($q) {
                if ($this->statusFilter === 'expired') {
                    $q->where('valid_until', '<', now()->toDateString())->where('status', '!=', 'converted');
                } else {
                    $q->where('status', $this->statusFilter);
                }
            })
            ->when($this->tierFilter !== 'all', fn($q) => $q->where('pricing_tier', $this->tierFilter))
            ->latest();

        $quotations = $query->paginate(15);

        // Stats
        $totalCount = Quotation::count();
        $convertedCount = Quotation::where('status', 'converted')->count();
        $activeCount = Quotation::where('status', '!=', 'converted')
            ->where(fn($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()->toDateString()))
            ->count();
        $expiredCount = Quotation::where('status', '!=', 'converted')
            ->where('valid_until', '<', now()->toDateString())
            ->count();

        return view('livewire.quotation-index', [
            'quotations'     => $quotations,
            'totalCount'     => $totalCount,
            'convertedCount' => $convertedCount,
            'activeCount'    => $activeCount,
            'expiredCount'   => $expiredCount,
        ])->layout('components.layouts.app', ['title' => 'عروض الأسعار (Price Quotations)']);
    }
}
