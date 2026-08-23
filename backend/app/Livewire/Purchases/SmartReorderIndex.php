<?php

namespace App\Livewire\Purchases;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Store;
use App\Models\Supplier;
use App\Services\ReorderAssistantService;

#[Layout('components.layouts.app')]
#[Title('مساعد المشتريات الذكي والتنبؤ بالنواقص | منظومة ERP')]
class SmartReorderIndex extends Component
{
    public string $selectedStoreId = 'all';
    public int $analysisDays = 14;
    public int $targetCoverDays = 15;
    public string $filterUrgency = 'all'; // all, critical, warning, safe
    public string $search = '';
    public array $selectedItems = [];

    public function mount()
    {
        abort_if(!auth()->user()?->can('purchases.view'), 403, 'غير مصرح لك بالوصول لمساعد المشتريات.');
    }

    public function selectAllCritical(array $criticalItemIds)
    {
        $this->selectedItems = $criticalItemIds;
    }

    public function createPurchaseOrder(ReorderAssistantService $service)
    {
        if (empty($this->selectedItems)) {
            $this->dispatch('swal:toast', [
                'type'  => 'warning',
                'title' => 'يرجى تحديد أصناف أولاً',
                'text'  => 'اختر صنفاً واحداً على الأقل لإنشاء فاتورة الشراء والتوريد.'
            ]);
            return;
        }

        $storeFilter = ($this->selectedStoreId && $this->selectedStoreId !== 'all') ? (int)$this->selectedStoreId : null;
        $data = $service->getReorderSuggestions(
            storeId: $storeFilter,
            analysisDays: $this->analysisDays,
            targetCoverDays: $this->targetCoverDays
        );

        $suggestionsMap = collect($data['suggestions'])->keyBy('id');

        $prefillItems = [];
        foreach ($this->selectedItems as $itemId) {
            $suggested = $suggestionsMap->get($itemId);
            $qty = $suggested && bccomp((string)$suggested['suggested_quantity'], '0.000', 3) > 0
                ? (string)$suggested['suggested_quantity']
                : '1.000';

            $prefillItems[] = [
                'item_id'  => (int)$itemId,
                'quantity' => $qty,
            ];
        }

        // Store structured items in session to pre-fill purchase create
        session(['smart_reorder_prefill' => $prefillItems]);

        return redirect()->route('purchases.create');
    }

    public function render(ReorderAssistantService $service)
    {
        $storeFilter = ($this->selectedStoreId && $this->selectedStoreId !== 'all') ? (int)$this->selectedStoreId : null;

        $data = $service->getReorderSuggestions(
            storeId: $storeFilter,
            analysisDays: $this->analysisDays,
            targetCoverDays: $this->targetCoverDays
        );

        $suggestions = collect($data['suggestions'])
            ->when($this->search, function ($collection) {
                return $collection->filter(function ($item) {
                    return str_contains(mb_strtolower($item['name']), mb_strtolower($this->search))
                        || str_contains(mb_strtolower($item['code']), mb_strtolower($this->search));
                });
            })
            ->when($this->filterUrgency !== 'all', function ($collection) {
                return $collection->where('urgency', $this->filterUrgency);
            });

        $criticalItemIds = collect($data['suggestions'])
            ->whereIn('urgency', ['critical', 'warning'])
            ->pluck('id')
            ->toArray();

        return view('livewire.purchases.smart-reorder-index', [
            'stores'          => Store::active()->get(),
            'suppliers'       => Supplier::active()->get(),
            'suggestions'     => $suggestions,
            'summary'         => $data,
            'criticalItemIds' => $criticalItemIds,
        ]);
    }
}
