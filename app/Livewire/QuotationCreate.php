<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Item;
use App\Models\Customer;
use App\Models\Store;
use App\Services\QuotationService;
use App\Services\CustomerPricingHelper;
use App\Livewire\Traits\RequiresAuth;
use Exception;

class QuotationCreate extends Component
{
    use RequiresAuth;

    public $pricing_tier = 'wholesale'; // wholesale, retail
    public $customer_id;
    public $customer_name = '';
    public $customer_phone = '';
    public $store_id;
    public $quotation_date;
    public $valid_until;
    public $validity_days = 7; // 3, 7, 15, 30

    public $discount_type = 'fixed'; // fixed, percentage
    public $discount_value = '0.000';
    public $shipping_cost = '0.000';
    public $notes = '';
    public $terms_conditions = '';

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
    public $net_total = '0.000';

    public $errorMessage = '';

    protected $rules = [
        'store_id'          => 'required|exists:stores,id',
        'quotation_date'    => 'required|date',
        'valid_until'       => 'nullable|date|after_or_equal:quotation_date',
        'pricing_tier'      => 'required|in:wholesale,retail',
        'items'             => 'required|array|min:1',
        'items.*.item_id'   => 'required|exists:items,id',
        'items.*.quantity'  => 'required|numeric|min:0.001',
        'items.*.unit_price'=> 'required|numeric|min:0',
    ];

    public function mount()
    {
        abort_if(!auth()->user()?->can('invoices.view') && !auth()->user()?->can('pos.access'), 403, 'غير مصرح لك بإنشاء عروض الأسعار');

        $this->quotation_date = now()->toDateString();
        $this->valid_until = now()->addDays($this->validity_days)->toDateString();
        
        $this->store_id = session('current_store_id') 
            ?? auth()->user()?->getCurrentStore()?->id 
            ?? Store::getMainStore()?->id;

        $this->terms_conditions = "• الأسعار الموضحة بالعرض سارية حتى تاريخ انتهاء الصلاحية الموضح أعلاه.\n• التسليم من مخازن ومقرات سرور كوفي ما لم يُتفق على الشحن.\n• السداد نقدًا أو تحويل إلكتروني معتمد عند التوريد.";

        $firstCustomer = Customer::active()->first();
        if ($firstCustomer) {
            $this->customer_id = $firstCustomer->id;
            $this->customer_name = $firstCustomer->name;
            $this->customer_phone = $firstCustomer->phone ?? '';
            $this->customerSearch = $firstCustomer->name . ($firstCustomer->phone ? " ({$firstCustomer->phone})" : '');
        }
    }

    public function setPricingTier($tier)
    {
        $this->pricing_tier = $tier;
        // Batch convert existing items in quotation
        foreach ($this->items as $idx => $line) {
            $item = Item::find($line['item_id']);
            if ($item) {
                if ($tier === 'wholesale') {
                    $newPrice = (string)($item->wholesale_price && bccomp((string)$item->wholesale_price, '0.000', 2) > 0 
                        ? $item->wholesale_price 
                        : $item->selling_price);
                } else {
                    $newPrice = (string)($item->selling_price ?: $item->getEffectivePriceForStore($this->store_id));
                }
                $this->items[$idx]['unit_price'] = $newPrice;
                $this->items[$idx]['price_tier'] = $tier;
            }
        }
        $this->calculateTotals();

        $tierLabel = $tier === 'wholesale' ? 'أسعار الجملة' : 'أسعار القطاعي';
        $this->dispatch('swal:toast', [
            'icon'  => 'info',
            'title' => "تم تبديل تسعير العرض إلى: {$tierLabel} وتحديث جميع البنود ✅",
        ]);
    }

    public function setValidityDays($days)
    {
        $this->validity_days = $days;
        $this->valid_until = now()->addDays($days)->toDateString();
    }

    public function selectCustomer($customerId)
    {
        $customer = Customer::find($customerId);
        if ($customer) {
            $this->customer_id = $customer->id;
            $this->customer_name = $customer->name;
            $this->customer_phone = $customer->phone ?? '';
            $this->customerSearch = $customer->name . ($customer->phone ? " ({$customer->phone})" : '');
            $this->showCustomerDropdown = false;
            $this->updatedCustomerId();
        }
    }

    public function clearCustomer()
    {
        $this->customer_id = null;
        $this->customer_name = '';
        $this->customer_phone = '';
        $this->customerSearch = '';
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
        $this->customer_name = $customer->name;
        $this->customer_phone = $customer->phone ?? '';
        $this->customerSearch = $customer->name . ($customer->phone ? " ({$customer->phone})" : '');
        $this->showNewCustomerModal = false;
        $this->showCustomerDropdown = false;
        $this->updatedCustomerId();

        $this->dispatch('swal:toast', [
            'icon'  => 'success',
            'title' => "تم تسجيل العميل ({$customer->name}) وتحديده لعرض السعر فوراً ✅",
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

    public function addItem($itemId, $quantity = '1.000')
    {
        $item = Item::active()->find($itemId);
        if (!$item) return;

        $qtyToAdd = (string) $quantity;
        $currentStock = (string) $item->getStockInStore($this->store_id);

        // Check if item already in lines
        foreach ($this->items as $index => $line) {
            if ($line['item_id'] == $item->id) {
                $newQty = bcadd($line['quantity'], $qtyToAdd, 3);
                $this->items[$index]['quantity'] = $newQty;
                $this->calculateTotals();
                $this->searchQuery = '';
                return;
            }
        }

        $retailPrice = (string)($item->selling_price ?: $item->getEffectivePriceForStore($this->store_id));
        $wholesalePrice = (string)($item->wholesale_price && bccomp((string)$item->wholesale_price, '0.000', 2) > 0 
            ? $item->wholesale_price 
            : $item->selling_price);

        // Pick initial price according to master pricing tier
        $initialPrice = ($this->pricing_tier === 'wholesale') ? $wholesalePrice : $retailPrice;

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
            'unit_price'          => $initialPrice,
            'price_retail'        => $retailPrice,
            'price_wholesale'     => $wholesalePrice,
            'price_tier'          => $this->pricing_tier,
            'discount_amount'     => '0.000',
            'total_price'         => bcmul($qtyToAdd, $initialPrice, 3),
            'last_customer_price' => $lastCustomerPrice,
        ];

        $this->calculateTotals();
        $this->searchQuery = '';
    }

    public function applyCustomerLastPrice($index)
    {
        if (isset($this->items[$index]) && !empty($this->items[$index]['last_customer_price'])) {
            $this->updateLinePrice($index, $this->items[$index]['last_customer_price']['unit_price']);
            $this->items[$index]['price_tier'] = 'custom';
        }
    }

    public function setLinePriceRetail($index)
    {
        if (isset($this->items[$index])) {
            $price = $this->items[$index]['price_retail'] ?? $this->items[$index]['unit_price'];
            $this->updateLinePrice($index, $price);
            $this->items[$index]['price_tier'] = 'retail';
        }
    }

    public function setLinePriceWholesale($index)
    {
        if (isset($this->items[$index])) {
            $price = $this->items[$index]['price_wholesale'] ?? $this->items[$index]['unit_price'];
            $this->updateLinePrice($index, $price);
            $this->items[$index]['price_tier'] = 'wholesale';
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

    public function quickSetDiscountPercent($percent)
    {
        $this->discount_type = 'percentage';
        $this->discount_value = (string) $percent;
        $this->calculateTotals();
    }

    public function updatedItems($value, $key)
    {
        $parts = explode('.', $key);
        $idx = $parts[0] ?? null;

        if ($idx !== null && isset($this->items[$idx])) {
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

    public function updatedShippingCost()
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
        $shipping = (string)($this->shipping_cost ?: '0.000');
        $net = bcadd($afterDiscount, $shipping, 3);
        $this->net_total = bccomp($net, '0.000', 3) > 0 ? $net : '0.000';
    }

    public function saveQuotation(QuotationService $quotationService, $action = 'save')
    {
        $this->errorMessage = '';
        $this->validate();

        try {
            $quotation = $quotationService->createQuotation([
                'pricing_tier'      => $this->pricing_tier,
                'customer_id'       => $this->customer_id,
                'customer_name'     => $this->customer_name ?: ($this->customerSearch ?: 'عميل محتمل'),
                'customer_phone'    => $this->customer_phone,
                'store_id'          => $this->store_id,
                'quotation_date'    => $this->quotation_date,
                'valid_until'       => $this->valid_until,
                'status'            => ($action === 'whatsapp') ? 'sent' : 'draft',
                'discount_type'     => $this->discount_type,
                'discount_value'    => $this->discount_value,
                'shipping_cost'     => $this->shipping_cost,
                'notes'             => $this->notes,
                'terms_conditions'  => $this->terms_conditions,
                'items'             => $this->items,
            ]);

            if ($action === 'print') {
                return redirect()->route('quotations.print', $quotation->id);
            }

            if ($action === 'whatsapp') {
                $rawMsg = $quotationService->formatWhatsAppMessage($quotation);
                $encoded = urlencode($rawMsg);
                $phone = preg_replace('/[^0-9]/', '', $quotation->target_customer_phone ?? '');
                if (str_starts_with($phone, '01')) {
                    $phone = '2' . $phone; // Egypt country code
                }
                $waUrl = $phone 
                    ? "https://api.whatsapp.com/send?phone={$phone}&text={$encoded}"
                    : "https://api.whatsapp.com/send?text={$encoded}";

                session()->flash('success', "تم حفظ عرض السعر برقم {$quotation->quotation_number} وجاري فتح واتساب للمشاركة.");
                $this->dispatch('open-window', ['url' => $waUrl]);
                return redirect()->route('quotations.index');
            }

            session()->flash('success', "تم حفظ عرض السعر بنجاح برقم: {$quotation->quotation_number}");
            return redirect()->route('quotations.index');
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

        return view('livewire.quotation-create', [
            'customers'         => $customers,
            'filteredCustomers' => $filteredCustomers,
            'selectedCustomer'  => $selectedCustomer,
            'stores'            => $stores,
            'categories'        => $categories,
            'quickCatalog'      => $quickCatalog,
            'currentStore'      => Store::find($this->store_id),
        ])->layout('components.layouts.app', ['title' => 'إنشاء عرض أسعار جديد (Price Quotation)']);
    }
}
