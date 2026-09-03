<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Customer;
use App\Models\Store;
use App\Services\InvoiceService;
use App\Services\CustomerPricingHelper;
use App\Livewire\Traits\RequiresAuth;
use Exception;

class InvoiceEdit extends Component
{
    use RequiresAuth;

    public Invoice $invoice;
    public $invoice_id;
    public $invoice_number;
    public $customer_id;
    public $store_id;
    public $invoice_date;
    public $payment_type = 'cash'; // cash, credit, partial
    public $payment_method = 'cash'; // cash, instapay, e_wallet, visa, bank_transfer
    public $discount_type = 'fixed'; // fixed, percentage
    public $discount_value = '0.000';
    public $paid_amount = '0.000';
    public $notes;

    // Search and Quick Add
    public $searchQuery = '';
    public $selectedCategory = 'all';
    public $items = [];

    // Customer Search and Quick Registration
    public $customerSearch = '';
    public $showCustomerDropdown = false;
    public $showNewCustomerModal = false;
    public $newCustomerName = '';
    public $newCustomerPhone = '';
    public $newCustomerAddress = '';
    public $newCustomerNotes = '';

    // Summary calculations
    public $subtotal = '0.000';
    public $discount_amount = '0.000';
    public array $additional_expenses = [];
    public $additional_expenses_total = '0.000';
    public $shipping_cost = '0.000';
    public $net_total = '0.000';
    public $remaining_amount = '0.000';

    public $errorMessage = '';

    protected $rules = [
        'customer_id'       => 'required|exists:customers,id',
        'store_id'          => 'required|exists:stores,id',
        'invoice_date'      => 'required|date',
        'payment_type'      => 'required|in:cash,credit,partial',
        'items'             => 'required|array|min:1',
        'items.*.item_id'   => 'required|exists:items,id',
        'items.*.quantity'  => 'required|numeric|min:0.001',
        'items.*.unit_price'=> 'required|numeric|min:0',
        'additional_expenses.*.title'  => 'nullable|string|max:150',
        'additional_expenses.*.amount' => 'nullable|numeric|min:0',
    ];

    public function mount($id)
    {
        abort_if(!auth()->user()?->can('invoices.edit'), 403, 'غير مصرح لك بتعديل فواتير المبيعات المعتمدة');

        $this->invoice = Invoice::with(['items.item', 'customer', 'additionalExpenses'])->findOrFail($id);
        $this->invoice_id = $this->invoice->id;
        $this->invoice_number = $this->invoice->invoice_number;
        $this->customer_id = $this->invoice->customer_id;
        $this->store_id = $this->invoice->store_id ?? session('current_store_id') ?? Store::getMainStore()?->id;
        $this->invoice_date = $this->invoice->invoice_date->format('Y-m-d');
        $this->payment_type = $this->invoice->payment_type ?: 'cash';
        $this->payment_method = $this->invoice->payment_method ?: 'cash';
        $this->discount_type = $this->invoice->discount_type ?: 'fixed';
        $this->discount_value = (string)($this->invoice->discount_value ?: '0.000');
        $this->paid_amount = (string)($this->invoice->paid_amount ?: '0.000');
        $this->notes = $this->invoice->notes;

        if ($this->invoice->customer) {
            $this->customerSearch = $this->invoice->customer->name . ($this->invoice->customer->phone ? " ({$this->invoice->customer->phone})" : '');
        }

        $pricingHelper = app(CustomerPricingHelper::class);
        $this->items = [];
        foreach ($this->invoice->items as $line) {
            $itm = $line->item ?: Item::withTrashed()->find($line->item_id);
            $stockInStore = $itm ? (string)$itm->getStockInStore($this->store_id) : '0.000';
            // In edit mode, the item already deducted $line->quantity, so effective available is current_stock + line->quantity
            $effectiveStock = bcadd($stockInStore, (string)$line->quantity, 3);
            
            $retailPrice = (string)($itm?->selling_price ?: $line->unit_price);
            $wholesalePrice = (string)($itm?->wholesale_price ?: $itm?->selling_price ?: $line->unit_price);
            $lastCustomerPrice = $this->customer_id 
                ? $pricingHelper->getLastSoldPrice($this->customer_id, $line->item_id, $this->store_id)
                : null;

            $this->items[] = [
                'item_id'             => $line->item_id,
                'code'                => $itm?->code ?? '—',
                'name'                => $itm?->name ?? 'صنف غير معروف',
                'category'            => $itm?->category ?? 'عام',
                'unit'                => $itm?->unit ?: 'كجم',
                'current_stock'       => $effectiveStock,
                'quantity'            => (string)$line->quantity,
                'unit_price'          => (string)$line->unit_price,
                'price_retail'        => $retailPrice,
                'price_wholesale'     => $wholesalePrice,
                'discount_amount'     => (string)($line->discount_amount ?: '0.000'),
                'total_price'         => (string)$line->total_price,
                'last_customer_price' => $lastCustomerPrice,
            ];
        }

        $this->additional_expenses = [];
        foreach ($this->invoice->additionalExpenses as $exp) {
            $this->additional_expenses[] = [
                'title'             => $exp->title,
                'amount'            => (string)$exp->amount,
                'allocation_method' => $exp->allocation_method ?: 'by_quantity',
                'paid_by'           => $exp->paid_by ?: 'customer_account',
                'notes'             => $exp->notes ?: '',
            ];
        }

        $this->calculateTotals();
    }

    public function selectCustomer($customerId)
    {
        $customer = Customer::find($customerId);
        if ($customer) {
            $this->customer_id = $customer->id;
            $this->customerSearch = $customer->name . ($customer->phone ? " ({$customer->phone})" : '');
            $this->showCustomerDropdown = false;
            $this->updatedCustomerId();
        }
    }

    public function openNewCustomerModal()
    {
        $this->newCustomerName = '';
        $this->newCustomerPhone = '';
        $this->newCustomerAddress = '';
        $this->newCustomerNotes = '';
        $this->showNewCustomerModal = true;
    }

    public function closeNewCustomerModal()
    {
        $this->showNewCustomerModal = false;
    }

    public function quickCreateCustomer()
    {
        $this->validate([
            'newCustomerName' => 'required|string|max:150',
            'newCustomerPhone' => 'nullable|string|max:30',
        ], [
            'newCustomerName.required' => 'يرجى إدخال اسم العميل',
        ]);

        $customer = Customer::create([
            'name'            => trim($this->newCustomerName),
            'phone'           => trim($this->newCustomerPhone) ?: null,
            'address'         => trim($this->newCustomerAddress) ?: null,
            'notes'           => trim($this->newCustomerNotes) ?: null,
            'is_active'       => true,
            'current_balance' => '0.000',
        ]);

        $this->customer_id = $customer->id;
        $this->customerSearch = $customer->name . ($customer->phone ? " ({$customer->phone})" : '');
        $this->showNewCustomerModal = false;
        $this->showCustomerDropdown = false;
        $this->updatedCustomerId();

        $this->dispatch('swal:toast', [
            'icon'  => 'success',
            'title' => "تم تسجيل العميل ({$customer->name}) وتحديده للفاتورة فوراً ✅",
        ]);
    }

    public function updatedCustomerId()
    {
        $pricingHelper = app(CustomerPricingHelper::class);
        foreach ($this->items as $idx => $line) {
            $this->items[$idx]['last_customer_price'] = $this->customer_id
                ? $pricingHelper->getLastSoldPrice($this->customer_id, $line['item_id'], $this->store_id)
                : null;
        }
    }

    public function updatedStoreId()
    {
        foreach ($this->items as $idx => $line) {
            $item = Item::find($line['item_id']);
            if ($item) {
                $stockInStore = (string)$item->getStockInStore($this->store_id);
                $this->items[$idx]['current_stock'] = $stockInStore;
            }
        }
        $this->updatedCustomerId();
    }

    public function addItem($itemId, $quantity = '1.000')
    {
        $item = Item::active()->find($itemId);
        if (!$item) return;

        $qtyToAdd = (string) $quantity;
        $currentStock = (string) $item->getStockInStore($this->store_id);

        if (bccomp($currentStock, '0.000', 3) <= 0) {
            $msg = "عفواً، الصنف ({$item->name}) غير متوفر حالياً بالمخزن/الفرع المحدد (الرصيد المتاح: 0).";
            $this->errorMessage = $msg;
            $this->dispatch('swal:toast', [
                'icon'  => 'error',
                'title' => $msg,
            ]);
            $this->dispatch('swal:alert', [
                'icon'    => 'error',
                'title'   => 'نفاد الكمية بالمخزن!',
                'message' => $msg,
            ]);
            return;
        }

        foreach ($this->items as $index => $line) {
            if ($line['item_id'] == $item->id) {
                $newQty = bcadd($line['quantity'], $qtyToAdd, 3);
                $this->items[$index]['quantity'] = $newQty;
                $this->items[$index]['total_price'] = bcmul($newQty, $this->items[$index]['unit_price'], 3);
                $this->calculateTotals();
                $this->searchQuery = '';
                return;
            }
        }

        $effectivePrice = $item->getEffectivePriceForStore($this->store_id);
        $retailPrice = (string)($item->selling_price ?: $effectivePrice);
        $wholesalePrice = (string)($item->wholesale_price ?: $item->selling_price);

        $lastCustomerPrice = $this->customer_id 
            ? app(CustomerPricingHelper::class)->getLastSoldPrice($this->customer_id, $item->id, $this->store_id)
            : null;

        $this->items[] = [
            'item_id'             => $item->id,
            'code'                => $item->code,
            'name'                => $item->name,
            'category'            => $item->category,
            'unit'                => $item->unit ?: 'كجم',
            'current_stock'       => $currentStock,
            'quantity'            => $qtyToAdd,
            'unit_price'          => $effectivePrice,
            'price_retail'        => $retailPrice,
            'price_wholesale'     => $wholesalePrice,
            'discount_amount'     => '0.000',
            'total_price'         => bcmul($qtyToAdd, $effectivePrice, 3),
            'last_customer_price' => $lastCustomerPrice,
        ];

        $this->calculateTotals();
        $this->searchQuery = '';
    }

    public function applyCustomerLastPrice($index)
    {
        if (isset($this->items[$index]) && !empty($this->items[$index]['last_customer_price'])) {
            $this->updateLinePrice($index, $this->items[$index]['last_customer_price']['unit_price']);
        }
    }

    public function setLinePriceRetail($index)
    {
        if (isset($this->items[$index])) {
            $price = $this->items[$index]['price_retail'] ?? $this->items[$index]['unit_price'];
            $this->updateLinePrice($index, $price);
        }
    }

    public function setLinePriceWholesale($index)
    {
        if (isset($this->items[$index])) {
            $price = $this->items[$index]['price_wholesale'] ?? $this->items[$index]['unit_price'];
            $this->updateLinePrice($index, $price);
        }
    }

    public function updateLinePrice($index, $price)
    {
        if (isset($this->items[$index])) {
            $this->items[$index]['unit_price'] = (string)$price;
            $this->updatedItems($price, "{$index}.unit_price");
        }
    }

    public function updateLineQty($index, $qty)
    {
        if (isset($this->items[$index])) {
            $this->items[$index]['quantity'] = (string)$qty;
            $this->updatedItems($qty, "{$index}.quantity");
        }
    }

    public function setLineWeightPreset($index, $weight)
    {
        if (isset($this->items[$index])) {
            $this->updateLineQty($index, (string)$weight);
        }
    }

    public function setLineGrams($index, $grams)
    {
        if (isset($this->items[$index])) {
            $kg = bcdiv((string)$grams, '1000', 4);
            $this->updateLineQty($index, $kg);
        }
    }

    public function addLineWeightPreset($index, $weightToAdd)
    {
        if (isset($this->items[$index])) {
            $newQty = bcadd($this->items[$index]['quantity'], (string)$weightToAdd, 3);
            $this->updateLineQty($index, $newQty);
        }
    }

    public function removeItem($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->calculateTotals();
    }

    public function incrementLineQty($index, $step = '1.000')
    {
        if (isset($this->items[$index])) {
            $newQty = bcadd($this->items[$index]['quantity'], (string)$step, 3);
            $this->updateLineQty($index, $newQty);
        }
    }

    public function decrementLineQty($index, $step = '1.000')
    {
        if (isset($this->items[$index])) {
            $minQty = ($this->items[$index]['unit'] === 'كجم') ? '0.125' : '1.000';
            $newQty = bcsub($this->items[$index]['quantity'], (string)$step, 3);

            if (bccomp($newQty, $minQty, 3) < 0) {
                $this->removeItem($index);
                return;
            }

            $this->updateLineQty($index, $newQty);
        }
    }

    public function quickSetPaidExact()
    {
        $this->payment_type = 'cash';
        $this->paid_amount = $this->net_total;
        $this->calculateTotals();
    }

    public function quickSetPaidAmount($amount)
    {
        $amt = (string) $amount;
        $this->paid_amount = $amt;
        if (bccomp($amt, $this->net_total, 3) >= 0) {
            $this->payment_type = 'cash';
        } else {
            $this->payment_type = 'partial';
        }
        $this->calculateTotals();
    }

    public function quickSetDiscountPercent($percent)
    {
        if (!auth()->user()?->can('invoices.discount')) {
            $this->dispatch('swal:toast', ['icon' => 'error', 'title' => 'غير مصرح لك بمنح خصومات']);
            return;
        }
        $this->discount_type = 'percentage';
        $this->discount_value = (string) $percent;
        $this->calculateTotals();
    }

    public function quickSetPaymentType($type)
    {
        $this->payment_type = $type;
        $this->updatedPaymentType();
    }

    public function quickSetPaymentMethod($method)
    {
        $this->payment_method = $method;
    }

    public function updatedItems($value, $key)
    {
        $parts = explode('.', $key);
        $idx = $parts[0] ?? null;

        if ($idx !== null && isset($this->items[$idx])) {
            // Recalculate line total
            $qty = (string)($this->items[$idx]['quantity'] ?: '0');
            $price = (string)($this->items[$idx]['unit_price'] ?: '0');
            $disc = (string)($this->items[$idx]['discount_amount'] ?? '0.000');
            
            $gross = bcmul($qty, $price, 3);
            $net = bcsub($gross, $disc, 3);
            $this->items[$idx]['total_price'] = bccomp($net, '0.000', 3) > 0 ? $net : '0.000';
        }

        $this->calculateTotals();
    }

    public function updatedDiscountType()
    {
        $this->calculateTotals();
    }

    public function updatedDiscountValue()
    {
        $this->calculateTotals();
    }

    public function updatedPaymentType()
    {
        if ($this->payment_type === 'cash') {
            $this->paid_amount = $this->net_total;
        } elseif ($this->payment_type === 'credit') {
            $this->paid_amount = '0.000';
        }
        $this->calculateTotals();
    }

    public function updatedPaidAmount()
    {
        $this->calculateTotals();
    }

    public function calculateTotals()
    {
        $subtotal = '0.000';
        foreach ($this->items as $index => $line) {
            $qty = (string)($line['quantity'] ?: '0');
            $unitPrice = (string)($line['unit_price'] ?: '0');
            $lineDiscount = (string)($line['discount_amount'] ?? '0.000');

            $lineGross = bcmul($qty, $unitPrice, 3);
            $lineTotal = bcsub($lineGross, $lineDiscount, 3);
            $lineTotal = bccomp($lineTotal, '0.000', 3) > 0 ? $lineTotal : '0.000';

            $this->items[$index]['total_price'] = $lineTotal;
            $subtotal = bcadd($subtotal, $lineTotal, 3);
        }

        $this->subtotal = $subtotal;

        $discountVal = (string)($this->discount_value ?: '0.000');
        if ($this->discount_type === 'percentage') {
            $percAmount = bcmul($subtotal, bcdiv($discountVal, '100', 6), 3);
            $this->discount_amount = bccomp($percAmount, $subtotal, 3) > 0 ? $subtotal : $percAmount;
        } else {
            $this->discount_amount = bccomp($discountVal, $subtotal, 3) > 0 ? $subtotal : $discountVal;
        }

        $afterDiscount = bcsub($subtotal, $this->discount_amount, 3);

        // Dynamic Additional Expenses Processing
        $expTotal = '0.000';
        $customerExpTotal = '0.000';
        $expenses = is_array($this->additional_expenses) ? $this->additional_expenses : [];

        foreach ($expenses as $exp) {
            $amt = (string)($exp['amount'] ?? '0.000');
            if (is_numeric($amt) && bccomp($amt, '0.000', 3) > 0) {
                $expTotal = bcadd($expTotal, $amt, 3);
                $paidBy = $exp['paid_by'] ?? 'customer_account';
                if ($paidBy === 'customer_account' || $paidBy === 'supplier_account') {
                    $customerExpTotal = bcadd($customerExpTotal, $amt, 3);
                }
            }
        }

        if (bccomp((string)($this->shipping_cost ?: '0.000'), '0.000', 3) > 0 && empty($expenses)) {
            $customerExpTotal = bcadd($customerExpTotal, (string)$this->shipping_cost, 3);
            $expTotal = bcadd($expTotal, (string)$this->shipping_cost, 3);
        }

        $this->additional_expenses_total = $expTotal;
        $this->shipping_cost = $customerExpTotal;

        $net = bcadd($afterDiscount, $customerExpTotal, 3);
        $this->net_total = bccomp($net, '0.000', 3) > 0 ? $net : '0.000';

        if ($this->payment_type === 'cash') {
            $this->paid_amount = $this->net_total;
            $this->remaining_amount = '0.000';
        } elseif ($this->payment_type === 'credit') {
            $this->paid_amount = '0.000';
            $this->remaining_amount = $this->net_total;
        } else {
            $paid = $this->paid_amount ?: '0.000';
            $rem = bcsub($this->net_total, $paid, 3);
            $this->remaining_amount = bccomp($rem, '0.000', 3) > 0 ? $rem : '0.000';
        }
    }

    public function addExpenseRow($presetTitle = 'شحن وتوصيل', $presetPaidBy = 'customer_account')
    {
        $this->additional_expenses[] = [
            'title'             => $presetTitle,
            'amount'            => '',
            'allocation_method' => 'by_quantity',
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

    public function updatedShippingCost()
    {
        $this->calculateTotals();
    }

    public function saveInvoice(InvoiceService $invoiceService, $printMode = null)
    {
        abort_if(!auth()->user()?->can('invoices.edit'), 403, 'غير مصرح لك بتعديل فواتير المبيعات');

        if (bccomp((string)($this->discount_value ?: '0.000'), '0.000', 3) > 0) {
            abort_if(!auth()->user()?->can('invoices.discount'), 403, 'غير مصرح لك بمنح خصومات على الفواتير');
        }

        $this->errorMessage = '';
        $this->validate();

        try {
            $invoice = $invoiceService->updateInvoice($this->invoice, [
                'customer_id'         => $this->customer_id,
                'store_id'            => $this->store_id,
                'invoice_date'        => $this->invoice_date,
                'payment_type'        => $this->payment_type,
                'payment_method'      => $this->payment_method,
                'discount_type'       => $this->discount_type,
                'discount_value'      => $this->discount_value,
                'shipping_cost'       => $this->shipping_cost,
                'paid_amount'         => $this->paid_amount,
                'notes'               => $this->notes,
                'items'               => $this->items,
                'additional_expenses' => $this->additional_expenses,
            ]);

            if ($printMode === 'print' || $printMode === 'a4' || $printMode === 'thermal') {
                return redirect()->route('invoices.print.a4', $invoice->id);
            }

            session()->flash('success', "تم تحديث واعتماد فاتورة المبيعات بنجاح برقم: {$invoice->invoice_number}");
            return redirect()->route('invoices.show', $invoice->id);
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
            $this->dispatch('swal:toast', ['icon' => 'error', 'title' => $e->getMessage()]);
        }
    }

    public function render()
    {
        $customers = Customer::active()->orderBy('name')->get();
        $selectedCustomer = Customer::find($this->customer_id);
        $stores = Store::active()->orderBy('is_main', 'desc')->get();

        $filteredCustomers = Customer::active()
            ->when(!empty($this->customerSearch), function ($q) {
                $term = trim($this->customerSearch);
                $q->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->take(10)
            ->get();

        $quickCatalog = Item::active()
            ->when($this->selectedCategory !== 'all', fn($q) => $q->where('category', $this->selectedCategory))
            ->when(strlen($this->searchQuery) >= 1, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('name', 'like', "%{$this->searchQuery}%")
                        ->orWhere('code', 'like', "%{$this->searchQuery}%");
                });
            })
            ->take(12)
            ->get();

        $categories = Item::active()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category');

        return view('livewire.invoice-edit', [
            'customers'         => $customers,
            'filteredCustomers' => $filteredCustomers,
            'selectedCustomer'  => $selectedCustomer,
            'stores'            => $stores,
            'categories'        => $categories,
            'quickCatalog'      => $quickCatalog,
            'currentStore'      => Store::find($this->store_id),
        ])->layout('components.layouts.app', ['title' => "تعديل فاتورة مبيعات [{$this->invoice_number}]"]);
    }
}
