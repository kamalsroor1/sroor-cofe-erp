<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Item;
use App\Models\Customer;
use App\Models\Store;
use App\Services\InvoiceService;
use App\Services\CustomerPricingHelper;
use App\Livewire\Traits\RequiresAuth;
use Exception;

class InvoiceCreate extends Component
{
    use RequiresAuth;

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

    public function mount()
    {
        abort_if(!auth()->user()?->can('pos.access'), 403, 'غير مصرح لك بدخول شاشة نقطة البيع (POS)');

        $this->invoice_date = now()->toDateString();
        
        $this->store_id = session('current_store_id') 
            ?? auth()->user()?->getCurrentStore()?->id 
            ?? Store::getMainStore()?->id;

        $firstCustomer = Customer::active()->first();
        if ($firstCustomer) {
            $this->customer_id = $firstCustomer->id;
            $this->customerSearch = $firstCustomer->name . ($firstCustomer->phone ? " ({$firstCustomer->phone})" : '');
        }
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
        // Re-evaluate stock and custom prices for the selected store
        foreach ($this->items as $idx => $line) {
            $item = Item::find($line['item_id']);
            if ($item) {
                $this->items[$idx]['current_stock'] = $item->getStockInStore($this->store_id);
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

        // 🛑 Check 1: Stock is zero or negative
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

        // Check if item already in lines
        foreach ($this->items as $index => $line) {
            if ($line['item_id'] == $item->id) {
                $newQty = bcadd($line['quantity'], $qtyToAdd, 3);
                // 🛑 Check 2: Total requested quantity exceeds available stock
                if (bccomp($newQty, $currentStock, 3) > 0) {
                    $msg = "عفواً، الكمية الإجمالية المطلوب بيعها ({$newQty}) تتجاوز الرصيد المتاح بالمخزن ({$currentStock}) للصنف: {$item->name}";
                    $this->errorMessage = $msg;
                    $this->dispatch('swal:toast', [
                        'icon'  => 'error',
                        'title' => $msg,
                    ]);
                    $this->dispatch('swal:alert', [
                        'icon'    => 'error',
                        'title'   => 'تجاوز رصيد المخزن!',
                        'message' => $msg,
                    ]);
                    return;
                }
                $this->items[$index]['quantity'] = $newQty;
                $this->items[$index]['total_price'] = bcmul($newQty, $this->items[$index]['unit_price'], 3);
                $this->calculateTotals();
                $this->searchQuery = '';
                return;
            }
        }

        // 🛑 Check 3: Initial requested quantity exceeds current stock
        if (bccomp($qtyToAdd, $currentStock, 3) > 0) {
            $msg = "عفواً، الكمية المطلوبة ({$qtyToAdd}) تتجاوز الرصيد المتاح بالمخزن ({$currentStock}) للصنف: {$item->name}";
            $this->errorMessage = $msg;
            $this->dispatch('swal:toast', [
                'icon'  => 'error',
                'title' => $msg,
            ]);
            $this->dispatch('swal:alert', [
                'icon'    => 'error',
                'title'   => 'تجاوز رصيد المخزن!',
                'message' => $msg,
            ]);
            return;
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
            $line = $this->items[$index];
            $item = Item::find($line['item_id']);
            $currentStock = $item ? (string)$item->getStockInStore($this->store_id) : '0.000';
            $reqWeight = (string) $weight;

            if (bccomp($reqWeight, $currentStock, 3) > 0) {
                $msg = "عفواً، الوزن المطلوب ({$reqWeight}) يتجاوز الرصيد المتاح بالمخزن ({$currentStock}) للصنف: {$line['name']}";
                $this->errorMessage = $msg;
                $this->dispatch('swal:toast', ['icon' => 'error', 'title' => $msg]);
                $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'تجاوز رصيد المخزن!', 'message' => $msg]);
                return;
            }

            $this->updateLineQty($index, $reqWeight);
        }
    }

    public function setLineGrams($index, $grams)
    {
        if (isset($this->items[$index])) {
            $line = $this->items[$index];
            $item = Item::find($line['item_id']);
            $currentStock = $item ? (string)$item->getStockInStore($this->store_id) : '0.000';
            $kg = bcdiv((string)$grams, '1000', 4);

            if (bccomp($kg, $currentStock, 3) > 0) {
                $msg = "عفواً، الوزن المطلوب ({$kg} كجم) يتجاوز الرصيد المتاح بالمخزن ({$currentStock}) للصنف: {$line['name']}";
                $this->errorMessage = $msg;
                $this->dispatch('swal:toast', ['icon' => 'error', 'title' => $msg]);
                $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'تجاوز رصيد المخزن!', 'message' => $msg]);
                return;
            }

            $this->updateLineQty($index, $kg);
        }
    }

    public function addLineWeightPreset($index, $weightToAdd)
    {
        if (isset($this->items[$index])) {
            $line = $this->items[$index];
            $item = Item::find($line['item_id']);
            $currentStock = $item ? (string)$item->getStockInStore($this->store_id) : '0.000';
            $newQty = bcadd($line['quantity'], (string)$weightToAdd, 3);

            if (bccomp($newQty, $currentStock, 3) > 0) {
                $msg = "عفواً، الوزن المطلوب ({$newQty}) يتجاوز الرصيد المتاح بالمخزن ({$currentStock}) للصنف: {$line['name']}";
                $this->errorMessage = $msg;
                $this->dispatch('swal:toast', ['icon' => 'error', 'title' => $msg]);
                $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'تجاوز رصيد المخزن!', 'message' => $msg]);
                return;
            }

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
            $line = $this->items[$index];
            $item = Item::find($line['item_id']);
            $currentStock = $item ? (string)$item->getStockInStore($this->store_id) : '0.000';
            $newQty = bcadd($line['quantity'], (string)$step, 3);

            if (bccomp($newQty, $currentStock, 3) > 0) {
                $msg = "عفواً، الكمية المطلوبة ({$newQty}) تتجاوز الرصيد المتاح بالمخزن ({$currentStock}) للصنف: {$line['name']}";
                $this->errorMessage = $msg;
                $this->dispatch('swal:toast', ['icon' => 'error', 'title' => $msg]);
                return;
            }

            $this->updateLineQty($index, $newQty);
        }
    }

    public function decrementLineQty($index, $step = '1.000')
    {
        if (isset($this->items[$index])) {
            $line = $this->items[$index];
            $minQty = ($line['unit'] === 'كجم') ? '0.125' : '1.000';
            $newQty = bcsub($line['quantity'], (string)$step, 3);

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
        $field = $parts[1] ?? null;

        if ($idx !== null && isset($this->items[$idx])) {
            if ($field === 'quantity') {
                $item = Item::find($this->items[$idx]['item_id']);
                $currentStock = $item ? (string)$item->getStockInStore($this->store_id) : '0.000';
                $requestedQty = (string)($this->items[$idx]['quantity'] ?: '0');

                if (bccomp($requestedQty, $currentStock, 3) > 0) {
                    $msg = "عفواً، الكمية المطلوبة ({$requestedQty}) تتجاوز الرصيد المتاح بالمخزن ({$currentStock}) للصنف: {$this->items[$idx]['name']}";
                    $this->errorMessage = $msg;
                    $this->dispatch('swal:toast', ['icon' => 'error', 'title' => $msg]);
                    $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'تجاوز رصيد المخزن!', 'message' => $msg]);
                    $this->items[$idx]['quantity'] = $currentStock;
                }
            }

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

        // Support simple shipping_cost field if entered directly without rows
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
        abort_if(!auth()->user()?->can('invoices.create'), 403, 'غير مصرح لك بإنشاء واعتماد فواتير المبيعات');

        if (bccomp((string)($this->discount_value ?: '0.000'), '0.000', 3) > 0) {
            abort_if(!auth()->user()?->can('invoices.discount'), 403, 'غير مصرح لك بمنح خصومات على الفواتير');
        }

        $this->errorMessage = '';
        $this->validate();

        // 🛑 Strict validation before saving
        foreach ($this->items as $line) {
            $item = Item::find($line['item_id']);
            if ($item) {
                $avail = (string)$item->getStockInStore($this->store_id);
                if (bccomp($line['quantity'], $avail, 3) > 0) {
                    $msg = "عفواً، رصيد الصنف ({$item->name}) غير كافٍ لإنهاء الفاتورة (المطلوب: {$line['quantity']} - المتاح: {$avail}).";
                    $this->errorMessage = $msg;
                    $this->dispatch('swal:toast', ['icon' => 'error', 'title' => $msg]);
                    $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'فشل حفظ الفاتورة!', 'message' => $msg]);
                    return;
                }
            }
        }

        try {
            $invoice = $invoiceService->confirmInvoice([
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

            session()->flash('success', "تم حفظ واعتماد فاتورة المبيعات بنجاح برقم: {$invoice->invoice_number}");
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

        return view('livewire.invoice-create', [
            'customers'         => $customers,
            'filteredCustomers' => $filteredCustomers,
            'selectedCustomer'  => $selectedCustomer,
            'stores'            => $stores,
            'categories'        => $categories,
            'quickCatalog'      => $quickCatalog,
            'currentStore'      => Store::find($this->store_id),
        ])->layout('components.layouts.app', ['title' => 'نقطة البيع والكاشير السريع (POS)']);
    }
}
