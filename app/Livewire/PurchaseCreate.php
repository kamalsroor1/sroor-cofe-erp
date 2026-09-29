<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\Store;
use App\Services\PurchaseService;
use Exception;

class PurchaseCreate extends Component
{
    public $supplier_id;
    public $store_id;
    public $purchase_date;
    public $paid_amount = '0.000';
    public $discount_amount = '0.000';
    public $supplier_invoice_ref = '';
    public $notes = '';

    public $searchQuery = '';
    public array $items = [];
    public array $additional_expenses = [];
    public $additional_expenses_total = '0.000';

    public $subtotal = '0.000';
    public $net_total = '0.000';
    public $remaining_amount = '0.000';

    public $errorMessage = '';

    protected $rules = [
        'supplier_id'   => 'required|exists:suppliers,id',
        'store_id'      => 'required|exists:stores,id',
        'purchase_date' => 'required|date',
        'items'         => 'required|array|min:1',
        'items.*.item_id'   => 'required|exists:items,id',
        'items.*.quantity'  => 'required|numeric|min:0.001',
        'items.*.cost_price'=> 'required|numeric|gt:0',
        'additional_expenses.*.title'  => 'nullable|string|max:150',
        'additional_expenses.*.amount' => 'nullable|numeric|min:0',
    ];

    public function mount()
    {
        abort_if(!auth()->user()?->can('purchases.create'), 403, 'غير مصرح لك بإنشاء فواتير مشتريات وتوريدات');
        $this->purchase_date = now()->toDateString();
        
        $this->store_id = session('current_store_id') 
            ?? auth()->user()?->getCurrentStore()?->id 
            ?? Store::getMainStore()?->id;

        $firstSupplier = Supplier::active()->first();
        if ($firstSupplier) {
            $this->supplier_id = $firstSupplier->id;
        }

        // Smart Reorder Prefill with Exact Suggested Quantities
        if (session()->has('smart_reorder_prefill')) {
            $prefill = (array)session()->pull('smart_reorder_prefill');
            foreach ($prefill as $entry) {
                if (is_array($entry)) {
                    $itemId = $entry['item_id'] ?? null;
                    $qty = (string)($entry['quantity'] ?? '1.000');
                    if ($itemId) {
                        $this->addItem($itemId, $qty);
                    }
                } else {
                    $this->addItem((int)$entry, '1.000');
                }
            }
        }
    }

    public function addExpenseRow($presetTitle = 'شحن ونقل', $presetMethod = 'by_quantity', $presetPaidBy = 'supplier_account')
    {
        $this->additional_expenses[] = [
            'title'             => $presetTitle,
            'amount'            => '',
            'allocation_method' => $presetMethod,
            'paid_by'           => $presetPaidBy,
            'notes'             => '',
        ];
        $this->calculateTotals();
    }

    public function removeExpenseRow($index)
    {
        unset($this->additional_expenses[$index]);
        $this->additional_expenses = array_values($this->additional_expenses);
        $this->calculateTotals();
    }

    public function updatedAdditionalExpenses()
    {
        $this->calculateTotals();
    }

    public function addItem($itemId, $quantity = '1.000')
    {
        $item = Item::active()->find($itemId);
        if (!$item) return;

        $qtyToAdd = (string) $quantity;

        foreach ($this->items as $idx => $line) {
            if ($line['item_id'] == $item->id) {
                $this->items[$idx]['quantity'] = bcadd($line['quantity'], $qtyToAdd, 3);
                $this->calculateTotals();
                $this->searchQuery = '';
                return;
            }
        }

        $this->items[] = [
            'item_id'    => $item->id,
            'code'       => $item->code,
            'name'       => $item->name,
            'unit'       => $item->unit ?: 'كجم',
            'quantity'   => $qtyToAdd,
            'cost_price' => $item->cost_price ?: '0.000',
            'total_price'=> bcmul($qtyToAdd, ($item->cost_price ?: '0.000'), 3),
        ];

        $this->calculateTotals();
        $this->searchQuery = '';
    }

    public function setLineQuantity($index, $qty)
    {
        if (isset($this->items[$index])) {
            $this->items[$index]['quantity'] = (string) $qty;
            $this->calculateTotals();
        }
    }

    public function addLineQuantity($index, $qtyToAdd)
    {
        if (isset($this->items[$index])) {
            $this->items[$index]['quantity'] = bcadd($this->items[$index]['quantity'], (string)$qtyToAdd, 3);
            $this->calculateTotals();
        }
    }

    public function removeItem($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->calculateTotals();
    }

    public function updatedItems()
    {
        $this->calculateTotals();
    }

    public function updatedDiscountAmount()
    {
        $this->calculateTotals();
    }

    public function updatedPaidAmount()
    {
        $this->calculateTotals();
    }

    public function calculateTotals()
    {
        $sub = '0.000';
        $items = is_array($this->items) ? $this->items : [];
        $expenses = is_array($this->additional_expenses) ? $this->additional_expenses : [];

        foreach ($items as $idx => $line) {
            $qty = $line['quantity'] ?? '1.000';
            $cost = $line['cost_price'] ?? '0.000';
            $lineTotal = bcmul((string)$qty, (string)$cost, 3);

            $this->items[$idx]['total_price'] = $lineTotal;
            $sub = bcadd($sub, $lineTotal, 3);
        }

        $this->subtotal = $sub;

        $disc = $this->discount_amount ?: '0.000';
        if (bccomp($disc, $this->subtotal, 3) > 0) {
            $disc = $this->subtotal;
        }
        $this->discount_amount = $disc;

        // Calculate additional expenses
        $expTotal = '0.000';
        $supplierExpTotal = '0.000';

        foreach ($expenses as $exp) {
            $amt = (string)($exp['amount'] ?? '0.000');
            if (is_numeric($amt) && bccomp($amt, '0.000', 3) > 0) {
                $expTotal = bcadd($expTotal, $amt, 3);
                if (($exp['paid_by'] ?? 'supplier_account') === 'supplier_account') {
                    $supplierExpTotal = bcadd($supplierExpTotal, $amt, 3);
                }
            }
        }

        $this->additional_expenses_total = $expTotal;

        // Net Total = (Subtotal - Discount) + Supplier-charged expenses
        $baseNet = bcsub($this->subtotal, $disc, 3);
        $this->net_total = bcadd($baseNet, $supplierExpTotal, 3);

        $paid = $this->paid_amount ?: '0.000';
        $rem = bcsub($this->net_total, $paid, 3);
        $this->remaining_amount = bccomp($rem, '0.000', 3) > 0 ? $rem : '0.000';
    }

    public function getLandedCostPreviewProperty(): array
    {
        $previews = [];
        $items = is_array($this->items) ? $this->items : [];
        $expenses = is_array($this->additional_expenses) ? $this->additional_expenses : [];

        $totalQty = '0.000';
        $totalBase = '0.000';
        $count = count($items);

        foreach ($items as $line) {
            $q = (string)($line['quantity'] ?? '0.000');
            $c = (string)($line['cost_price'] ?? '0.000');
            $totalQty = bcadd($totalQty, $q, 3);
            $totalBase = bcadd($totalBase, bcmul($q, $c, 3), 3);
        }

        $discount = (string)($this->discount_amount ?: '0.000');
        if (!is_numeric($discount) || bccomp($discount, '0.000', 3) < 0) {
            $discount = '0.000';
        }
        if (bccomp($discount, $totalBase, 3) > 0) {
            $discount = $totalBase;
        }

        foreach ($items as $idx => $line) {
            $q = (string)($line['quantity'] ?? '0.000');
            $baseCost = (string)($line['cost_price'] ?? '0.000');
            $lineBaseTotal = bcmul($q, $baseCost, 3);
            $lineAllocated = '0.000';

            foreach ($expenses as $exp) {
                $amt = (string)($exp['amount'] ?? '0.000');
                if (!is_numeric($amt) || bccomp($amt, '0.000', 3) <= 0) continue;
                $method = $exp['allocation_method'] ?? 'by_quantity';

                if ($method === 'by_quantity' && bccomp($totalQty, '0.000', 3) > 0) {
                    $ratio = bcdiv($q, $totalQty, 6);
                    $lineAllocated = bcadd($lineAllocated, bcmul($amt, $ratio, 3), 3);
                } elseif ($method === 'by_value' && bccomp($totalBase, '0.000', 3) > 0) {
                    $ratio = bcdiv($lineBaseTotal, $totalBase, 6);
                    $lineAllocated = bcadd($lineAllocated, bcmul($amt, $ratio, 3), 3);
                } elseif ($method === 'equal' && $count > 0) {
                    $lineAllocated = bcadd($lineAllocated, bcdiv($amt, (string)$count, 3), 3);
                }
            }

            $unitAlloc = bccomp($q, '0.000', 3) > 0 ? bcdiv($lineAllocated, $q, 3) : '0.000';

            // Mirror PurchaseService: invoice discount is allocated by line value
            $lineDiscount = (bccomp($discount, '0.000', 3) > 0 && bccomp($totalBase, '0.000', 3) > 0)
                ? bcdiv(bcmul($discount, $lineBaseTotal, 6), $totalBase, 3)
                : '0.000';
            $unitDiscount = bccomp($q, '0.000', 3) > 0 ? bcdiv($lineDiscount, $q, 3) : '0.000';

            $landedUnit = bcsub(bcadd($baseCost, $unitAlloc, 3), $unitDiscount, 3);

            $previews[$idx] = [
                'base_cost'      => $baseCost,
                'allocated'      => $lineAllocated,
                'unit_allocated' => $unitAlloc,
                'landed_cost'    => $landedUnit,
            ];
        }

        return $previews;
    }

    public function incrementLineQty($index, $step = '1.000')
    {
        if (isset($this->items[$index])) {
            $this->items[$index]['quantity'] = bcadd($this->items[$index]['quantity'], (string)$step, 3);
            $this->calculateTotals();
        }
    }

    public function decrementLineQty($index, $step = '1.000')
    {
        if (isset($this->items[$index])) {
            $current = $this->items[$index]['quantity'];
            $new = bcsub($current, (string)$step, 3);
            if (bccomp($new, '0.001', 3) > 0) {
                $this->items[$index]['quantity'] = $new;
            } else {
                $this->removeItem($index);
                return;
            }
            $this->calculateTotals();
        }
    }

    public function quickSetPaidExact()
    {
        $this->paid_amount = $this->net_total;
        $this->calculateTotals();
    }

    public function quickSetPaidAmount($amount)
    {
        $this->paid_amount = (string)$amount;
        $this->calculateTotals();
    }

    public function savePurchase(PurchaseService $purchaseService)
    {
        abort_if(!auth()->user()?->can('purchases.create'), 403, 'غير مصرح لك بإنشاء فواتير مشتريات وتوريدات');
        $this->errorMessage = '';
        $this->validate();

        try {
            $purchase = $purchaseService->createPurchase([
                'supplier_id'          => $this->supplier_id,
                'store_id'             => $this->store_id,
                'purchase_date'        => $this->purchase_date,
                'discount_amount'      => $this->discount_amount,
                'paid_amount'          => $this->paid_amount,
                'supplier_invoice_ref' => $this->supplier_invoice_ref,
                'notes'                => $this->notes,
                'items'                => $this->items,
                'additional_expenses'  => $this->additional_expenses,
            ]);

            session()->flash('success', "تم توريد وإضافة البضاعة للمخزن بنجاح برقم: {$purchase->purchase_number}");
            return redirect()->route('purchases.index');
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $suppliers = Supplier::active()->orderBy('name')->get();
        $stores = Store::active()->get();
        
        $quickCatalog = Item::active()
            ->when(strlen($this->searchQuery) >= 1, function($q) {
                $q->where('name', 'like', "%{$this->searchQuery}%")
                  ->orWhere('code', 'like', "%{$this->searchQuery}%");
            })
            ->take(12)
            ->get();

        return view('livewire.purchase-create', [
            'suppliers'     => $suppliers,
            'stores'        => $stores,
            'quickCatalog'  => $quickCatalog,
        ])->layout('components.layouts.app', ['title' => 'فاتورة مشتريات وتوريد مخزني']);
    }
}
