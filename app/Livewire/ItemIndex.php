<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Item;
use App\Services\StockService;
use App\Livewire\Traits\RequiresAuth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ItemIndex extends Component
{
    use WithPagination, RequiresAuth;

    public $search = '';
    public $filterStock = 'all'; // all, low, out
    public $filterCategory = 'all';
    public $filterStatus = 'active'; // active, trashed, all

    // Modal Properties (Create & Edit)
    public $showModal = false;
    public $isEditMode = false;
    public $editItemId = null;

    public $code = '';
    public $name = '';
    public $category = 'بن وتوليفات';
    public $unit = 'كجم';
    public $current_stock = '0.000';
    public $cost_price = '0.000';
    public $costLocked = false; // item has stock movements: cost is derived from purchases (WAC)
    public $selling_price = '0.000';
    public $min_stock_level = '5.000';
    public $notes = '';

    // Stock Adjustment Modal Properties
    public $showAdjustmentModal = false;
    public $adjustItemId = null;
    public $adjustItemName = '';
    public $adjustItemUnit = 'كجم';
    public $currentRecordedStock = '0.000';
    public $actualCountStock = '0.000';
    public $adjustmentReason = 'عجز جرد وتصحيح وزن';
    public $adjustmentNotes = '';

    protected function rules()
    {
        return [
            'code'            => [
                'required',
                'max:50',
                Rule::unique('items', 'code')->whereNull('deleted_at')->ignore($this->editItemId),
            ],
            'name'            => 'required|string|max:255',
            'category'        => 'nullable|string|max:100',
            'unit'            => 'nullable|string|max:50',
            'current_stock'   => 'required|numeric|min:0',
            'cost_price'      => 'required|numeric|min:0',
            'selling_price'   => 'required|numeric|min:0',
            'min_stock_level' => 'required|numeric|min:0',
        ];
    }

    public function openCreateModal()
    {
        abort_if(!auth()->user()?->can('items.create'), 403, 'غير مصرح لك بإضافة أصناف جديدة');
        $this->reset(['code', 'name', 'notes', 'editItemId']);
        $this->isEditMode = false;
        $this->costLocked = false;
        $this->code = 'ITM-' . rand(1000, 9999);
        $this->category = 'بن وتوليفات';
        $this->unit = 'كجم';
        $this->current_stock = '0.000';
        $this->cost_price = '0.000';
        $this->selling_price = '0.000';
        $this->min_stock_level = '5.000';
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        abort_if(!auth()->user()?->can('items.edit'), 403, 'غير مصرح لك بتعديل بيانات الأصناف');
        $item = Item::withTrashed()->findOrFail($id);
        $this->isEditMode = true;
        $this->editItemId = $item->id;
        $this->code = $item->code;
        $this->name = $item->name;
        $this->category = $item->category ?: 'بن وتوليفات';
        $this->unit = $item->unit ?: 'كجم';
        $this->current_stock = $item->current_stock;
        $this->cost_price = $item->cost_price;
        $this->costLocked = $item->stockMovements()->exists();
        $this->selling_price = $item->selling_price;
        $this->min_stock_level = $item->min_stock_level;
        $this->notes = $item->notes ?? '';
        $this->showModal = true;
    }

    public function saveItem(StockService $stockService)
    {
        if ($this->isEditMode && $this->editItemId) {
            abort_if(!auth()->user()?->can('items.edit'), 403, 'غير مصرح لك بتعديل بيانات الأصناف');
        } else {
            abort_if(!auth()->user()?->can('items.create'), 403, 'غير مصرح لك بإضافة أصناف جديدة');
        }

        $this->validate();

        if ($this->isEditMode && $this->editItemId) {
            $item = Item::withTrashed()->findOrFail($this->editItemId);

            $payload = [
                'code'            => $this->code,
                'name'            => $this->name,
                'category'        => $this->category,
                'unit'            => $this->unit,
                'selling_price'   => $this->selling_price,
                'min_stock_level' => $this->min_stock_level,
                'notes'           => $this->notes,
            ];

            // Once an item has stock history its cost is derived from purchases (weighted average);
            // a manual edit would silently change the cost of every future sale.
            if (!$item->stockMovements()->exists()) {
                $payload['cost_price'] = $this->cost_price;
                $payload['weighted_avg_cost'] = $this->cost_price;
            } elseif (bccomp((string)$this->cost_price, (string)$item->cost_price, 3) !== 0) {
                $this->addError('cost_price', 'لا يمكن تعديل سعر التكلفة يدوياً لصنف له حركات مخزنية؛ التكلفة تُحسب تلقائياً من فواتير الشراء (متوسط مرجح).');
                return;
            }

            $item->update($payload);

            session()->flash('success', "تم تعديل بيانات الصنف [{$item->name}] بنجاح.");
            $this->dispatch('swal:toast', ['icon' => 'success', 'title' => "تم تعديل بيانات الصنف [{$item->name}] بنجاح!"]);
        } else {
            $initialStock = (string)$this->current_stock;

            if (bccomp($initialStock, '0.000', 3) > 0 && bccomp((string)$this->cost_price, '0.000', 3) <= 0) {
                $this->addError('cost_price', 'سعر التكلفة مطلوب (أكبر من صفر) عند إدخال رصيد أول المدة للصنف.');
                return;
            }

            $item = DB::transaction(function () use ($stockService, $initialStock) {
                $item = Item::create([
                    'code'              => $this->code,
                    'name'              => $this->name,
                    'category'          => $this->category,
                    'unit'              => $this->unit,
                    'current_stock'     => '0.000',
                    'cost_price'        => $this->cost_price,
                    'weighted_avg_cost' => $this->cost_price,
                    'selling_price'     => $this->selling_price,
                    'min_stock_level'   => $this->min_stock_level,
                    'is_active'         => true,
                    'notes'             => $this->notes,
                ]);

                if (bccomp($initialStock, '0.000', 3) > 0) {
                    $stockService->depositStock(
                        item: $item,
                        quantity: $initialStock,
                        costPrice: $this->cost_price,
                        depositType: 'opening_balance',
                        reason: 'رصيد أول المدة عند تعريف الصنف'
                    );
                }

                return $item;
            });

            session()->flash('success', "تم إضافة الصنف [{$item->name}] بنجاح.");
            $this->dispatch('swal:toast', ['icon' => 'success', 'title' => "تم إضافة الصنف [{$item->name}] بنجاح للمخزون!"]);
        }

        $this->showModal = false;
    }

    public function openAdjustmentModal($itemId)
    {
        abort_if(!auth()->user()?->can('items.edit'), 403, 'غير مصرح لك بتسوية المخزون');
        $item = Item::findOrFail($itemId);
        $this->adjustItemId = $item->id;
        $this->adjustItemName = $item->name;
        $this->adjustItemUnit = $item->unit ?: 'كجم';
        $this->currentRecordedStock = (string)$item->current_stock;
        $this->actualCountStock = (string)$item->current_stock;
        $this->adjustmentReason = 'عجز جرد وتصحيح وزن';
        $this->adjustmentNotes = '';
        $this->showAdjustmentModal = true;
    }

    public function saveStockAdjustment(StockService $stockService)
    {
        abort_if(!auth()->user()?->can('items.edit'), 403, 'غير مصرح لك بتسوية المخزون');
        $this->validate([
            'actualCountStock' => 'required|numeric|min:0',
            'adjustmentReason' => 'required|string|min:3',
        ]);

        try {
            $item = Item::findOrFail($this->adjustItemId);
            $fullReason = $this->adjustmentReason . ($this->adjustmentNotes ? " - {$this->adjustmentNotes}" : '');
            
            $stockService->adjustStock(
                item: $item,
                actualQuantity: (string)$this->actualCountStock,
                reason: $fullReason
            );

            $this->showAdjustmentModal = false;
            session()->flash('success', "تم تنفيذ التسوية الجردية لصنف [{$item->name}] وضبط الرصيد الفعلي إلى {$this->actualCountStock} {$this->adjustItemUnit} بنجاح.");
            $this->dispatch('swal:toast', [
                'icon'  => 'success',
                'title' => "تم تسوية وتصحيح رصيد [{$item->name}] بنجاح!"
            ]);
        } catch (\Exception $e) {
            $this->dispatch('swal:toast', [
                'icon'  => 'error',
                'title' => $e->getMessage()
            ]);
        }
    }

    public function toggleActive($id)
    {
        abort_if(!auth()->user()?->can('items.edit'), 403, 'غير مصرح لك بتعديل حالة الصنف');
        $item = Item::withTrashed()->findOrFail($id);
        $item->is_active = !$item->is_active;
        $item->save();

        $state = $item->is_active ? 'تفعيل' : 'تعطيل وإخفاء من المبيعات';
        $this->dispatch('swal:toast', [
            'icon'  => 'info',
            'title' => "تم {$state} للصنف [{$item->name}] بنجاح."
        ]);
    }

    public function deleteItem($id)
    {
        abort_if(!auth()->user()?->can('items.delete'), 403, 'غير مصرح لك بحذف أو أرشفة الأصناف');
        $item = Item::findOrFail($id);
        $name = $item->name;

        $blockers = $item->getDeletionBlockers();
        if (!empty($blockers)) {
            $reasons = implode(' • ', $blockers);
            $this->dispatch('swal:toast', [
                'icon'  => 'warning',
                'title' => "⚠️ لا يمكن حذف [{$name}] لوجود معاملات مرتبطة! يمكنك تعطيله بدلاً من حذفه."
            ]);
            session()->flash('error', "لا يمكن حذف الصنف [{$name}] لوجود قيود تاريخية: " . implode(' ، ', $blockers) . ". يمكنك الضغط على زر (تعطيل) لإيقاف بيعه دون التأثير على الحسابات.");
            return;
        }

        $item->delete(); // Soft delete allowed only if 0 history

        session()->flash('success', "تم نقل الصنف [{$name}] إلى سلة المحذوفات بنجاح.");
        $this->dispatch('swal:toast', ['icon' => 'success', 'title' => "تم أرشفة الصنف [{$name}] بنجاح."]);
    }

    public function restoreItem($id)
    {
        abort_if(!auth()->user()?->can('trash.access'), 403, 'غير مصرح لك باسترجاع الأصناف');
        $item = Item::onlyTrashed()->findOrFail($id);
        $item->restore();

        session()->flash('success', "تم استعادة الصنف [{$item->name}] بنجاح.");
        $this->dispatch('swal:toast', ['icon' => 'success', 'title' => "تم استعادة الصنف [{$item->name}] بنجاح!"]);
    }

    public function render()
    {
        $baseQuery = match ($this->filterStatus) {
            'trashed'  => Item::onlyTrashed(),
            'disabled' => Item::where('is_active', false)->whereNull('deleted_at'),
            'all'      => Item::withTrashed(),
            default    => Item::active(),
        };

        $query = $baseQuery
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('name', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%")
                        ->orWhere('category', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterCategory !== 'all', fn($q) => $q->where('category', $this->filterCategory))
            ->when($this->filterStock === 'in_stock', fn($q) => $q->where('current_stock', '>', '0.000'))
            ->when($this->filterStock === 'low', fn($q) => $q->lowStock())
            ->when($this->filterStock === 'out', fn($q) => $q->where('current_stock', '<=', '0.000'))
            ->orderBy('name');

        return view('livewire.item-index', [
            'items'         => $query->paginate(15),
            'trashedCount'  => Item::onlyTrashed()->count(),
            'disabledCount' => Item::where('is_active', false)->whereNull('deleted_at')->count(),
        ])->layout('components.layouts.app', ['title' => 'دليل الأصناف والمخزون']);
    }
}
