<div class="space-y-4 pb-12 select-none" dir="rtl" x-data="{ 
    showNumpad: false, 
    numpadTarget: 'paid_amount', 
    numpadValue: '',
    pressNum(val) {
        if (val === 'C') {
            this.numpadValue = '';
        } else if (val === 'backspace') {
            this.numpadValue = this.numpadValue.slice(0, -1);
        } else {
            if (val === '.' && this.numpadValue.includes('.')) return;
            this.numpadValue += val;
        }
        if (this.numpadTarget === 'paid_amount') {
            $wire.set('paid_amount', this.numpadValue || '0.000');
        } else if (this.numpadTarget === 'discount_value') {
            $wire.set('discount_value', this.numpadValue || '0.000');
        }
    }
}">

    <!-- Top Command & Navigation Bar -->
    <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Invoice Info & Store -->
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-2xl font-bold border border-indigo-500/20 shrink-0 shadow-inner">
                ✏️
            </div>
            <div>
                <h1 class="text-lg md:text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>تعديل فاتورة مبيعات: <b class="font-mono text-amber-600 dark:text-amber-400">{{ $invoice_number }}</b></span>
                </h1>
                <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">الفرع / المخزن:</span>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20 text-xs font-bold font-mono">
                        @if($currentStore?->type === 'wholesale_van') 🚚 {{ $currentStore->name }} (عربية)
                        @elseif($currentStore?->type === 'main_warehouse') 🏢 {{ $currentStore->name }} (المخزن)
                        @else 🏬 {{ $currentStore?->name ?? 'الفرع الرئيسي' }}
                        @endif
                    </span>
                    <span class="text-slate-300 dark:text-slate-700">•</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">تاريخ الفاتورة: <b class="font-mono text-slate-700 dark:text-slate-300">{{ $invoice_date }}</b></span>
                </div>
            </div>
        </div>

        <!-- Top Right Actions -->
        <div class="flex items-center gap-2 flex-wrap">
            <button 
                type="button" 
                @click="showNumpad = !showNumpad" 
                :class="showNumpad ? 'bg-amber-600 text-white shadow-lg shadow-amber-600/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700'"
                class="px-3.5 py-2.5 rounded-xl text-xs font-bold flex items-center gap-2 transition-all cursor-pointer"
            >
                <span>🔢 لوحة أرقام اللمس</span>
            </button>

            <a 
                href="{{ route('invoices.show', $invoice_id) }}" 
                class="px-3.5 py-2.5 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition-all flex items-center gap-1.5"
            >
                <span>← إلغاء ورجوع للفاتورة</span>
            </a>
        </div>
    </div>

    <!-- Error Alerts -->
    @if($errorMessage)
    <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300 text-xs font-bold flex items-center gap-3 shadow-sm">
        <span class="text-xl">⚠️</span>
        <span>{{ $errorMessage }}</span>
    </div>
    @endif

    @if($errors->any())
    <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300 text-xs space-y-1.5 shadow-sm">
        <div class="font-black flex items-center gap-2 text-sm">
            <span>🚨</span>
            <span>يرجى مراجعة البيانات التالية:</span>
        </div>
        <ul class="list-disc list-inside pr-4 space-y-0.5 font-medium">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Main Grid: Catalog (Left) vs Cart & Checkout (Right) -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-5 items-start">
        
        <!-- ========================================== -->
        <!-- 📦 Left Catalog Column (7 Cols on XL)     -->
        <!-- ========================================== -->
        <div class="xl:col-span-7 space-y-4">
            
            <!-- Category Touch Filter Bar -->
            <div class="bg-white dark:bg-slate-900 p-3.5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
                <!-- Search & Quick Barcode Scanner -->
                <div class="relative">
                    <input 
                        type="text" 
                        wire:model.live.debounce.150ms="searchQuery" 
                        placeholder="🔍 ابحث بالاسم أو الباركود أو كود الصنف لإضافته للفاتورة..." 
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500"
                    >
                    @if($searchQuery)
                    <button wire:click="$set('searchQuery', '')" class="absolute left-3 top-1/2 -translate-y-1/2 w-7 h-7 rounded-lg bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center text-xs font-bold hover:bg-slate-300 cursor-pointer">✕</button>
                    @endif
                </div>

                <!-- Category Touch Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none text-xs">
                    <button 
                        type="button" 
                        wire:click="$set('selectedCategory', 'all')"
                        class="px-4 py-2 rounded-xl font-bold transition-all shrink-0 cursor-pointer flex items-center gap-1.5 {{ $selectedCategory === 'all' ? 'bg-amber-600 text-white shadow-md shadow-amber-600/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200' }}"
                    >
                        <span>📦 كل الأصناف</span>
                    </button>
                    @foreach($categories as $cat)
                    <button 
                        type="button" 
                        wire:click="$set('selectedCategory', '{{ $cat }}')"
                        class="px-4 py-2 rounded-xl font-bold transition-all shrink-0 cursor-pointer flex items-center gap-1.5 {{ $selectedCategory === $cat ? 'bg-amber-600 text-white shadow-md shadow-amber-600/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200' }}"
                    >
                        <span>🏷️ {{ $cat }}</span>
                    </button>
                    @endforeach
                </div>
            </div>

            <!-- Quick Products Touch Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-4 gap-3">
                @forelse($quickCatalog as $prod)
                @php
                    $prodStock = (string)$prod->getStockInStore($store_id);
                    $isAvailable = bccomp($prodStock, '0.000', 3) > 0;
                @endphp
                <div class="p-3 bg-white dark:bg-slate-900 rounded-2xl border transition-all duration-200 flex flex-col justify-between group shadow-sm hover:shadow-md {{ $isAvailable ? 'border-slate-200 dark:border-slate-800 hover:border-amber-500/60' : 'border-rose-300 dark:border-rose-900/50 bg-rose-500/[0.03] opacity-75' }}">
                    <div>
                        <!-- Header & Stock Badge -->
                        <div class="flex items-start justify-between gap-1 mb-1.5">
                            <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                                {{ $prod->code }}
                            </span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-md font-mono {{ $isAvailable ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20' }}">
                                @if($isAvailable)
                                    {{ number_format($prodStock, 2) }} {{ $prod->unit }}
                                @else
                                    نفدت الكمية
                                @endif
                            </span>
                        </div>

                        <!-- Product Name -->
                        <h3 class="font-black text-xs sm:text-sm text-slate-900 dark:text-white line-clamp-2 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors leading-snug">
                            {{ $prod->name }}
                        </h3>

                        <!-- Price Info -->
                        <div class="mt-2 flex items-baseline justify-between gap-1 flex-wrap">
                            <span class="text-sm font-black font-mono text-emerald-600 dark:text-emerald-400">
                                {{ number_format($prod->getEffectivePriceForStore($store_id), 2) }} <span class="text-[10px] font-normal">ج.م</span>
                            </span>
                            @if($prod->wholesale_price && bccomp((string)$prod->wholesale_price, '0.000', 2) > 0)
                            <span class="text-[10px] font-mono text-purple-600 dark:text-purple-400 font-bold" title="سعر الجملة">
                                جملة: {{ number_format($prod->wholesale_price, 2) }}
                            </span>
                            @endif
                        </div>
                    </div>

                    <!-- Touch Add Buttons -->
                    <div class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-800">
                        @if($prod->unit === 'كجم')
                            <div class="grid grid-cols-4 gap-1">
                                <button 
                                    type="button" 
                                    wire:click="addItem({{ $prod->id }}, '0.125')" 
                                    class="h-8 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-amber-600 hover:text-white text-slate-700 dark:text-slate-300 text-[10px] font-bold font-mono transition-all active:scale-90 cursor-pointer flex items-center justify-center shadow-xs"
                                    title="ثمن كيلو (125 جم)"
                                >
                                    125g
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="addItem({{ $prod->id }}, '0.250')" 
                                    class="h-8 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-amber-600 hover:text-white text-slate-700 dark:text-slate-300 text-[10px] font-bold font-mono transition-all active:scale-90 cursor-pointer flex items-center justify-center shadow-xs"
                                    title="ربع كيلو (250 جم)"
                                >
                                    250g
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="addItem({{ $prod->id }}, '0.500')" 
                                    class="h-8 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-amber-600 hover:text-white text-slate-700 dark:text-slate-300 text-[10px] font-bold font-mono transition-all active:scale-90 cursor-pointer flex items-center justify-center shadow-xs"
                                    title="نصف كيلو (500 جم)"
                                >
                                    500g
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="addItem({{ $prod->id }}, '1.000')" 
                                    class="h-8 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-[10px] font-black font-mono transition-all active:scale-90 cursor-pointer flex items-center justify-center shadow-md shadow-emerald-600/20"
                                    title="كيلو كامل (1 كجم)"
                                >
                                    1kg
                                </button>
                            </div>
                        @else
                            <button 
                                type="button" 
                                wire:click="addItem({{ $prod->id }}, '1.000')" 
                                class="w-full h-9 rounded-xl bg-emerald-500/15 hover:bg-emerald-600 hover:text-white text-emerald-700 dark:text-emerald-300 text-xs font-bold transition-all active:scale-95 cursor-pointer shadow-sm flex items-center justify-center gap-1.5"
                            >
                                <span>➕ إضافة وحدة</span>
                            </button>
                        @endif
                    </div>
                </div>
                @empty
                <div class="col-span-full py-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 text-slate-400 text-sm">
                    لا توجد أصناف مطابقة لبيانات البحث
                </div>
                @endforelse
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 🛒 Right Cart Column (5 Cols on XL)       -->
        <!-- ========================================== -->
        <div class="xl:col-span-5 space-y-4">
            
            <!-- Store & Customer Card -->
            <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- Store Selector -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">🏬 منفذ البيع / الفرع:</label>
                        <select wire:model.live="store_id" class="w-full h-11 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500">
                            @foreach($stores as $st)
                            <option value="{{ $st->id }}">
                                @if($st->type === 'wholesale_van') 🚚 @elseif($st->type === 'main_warehouse') 🏢 @else 🏬 @endif
                                {{ $st->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Customer Selector & Quick Search -->
                    <div class="relative" x-data="{ open: false }">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">👤 العميل والحساب:</label>
                            @if(auth()->user()?->can('customers.manage') || auth()->user()?->can('pos.access') || auth()->user()?->can('invoices.create'))
                            <button 
                                type="button" 
                                wire:click="openNewCustomerModal" 
                                class="text-[11px] font-black text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300 flex items-center gap-1 cursor-pointer transition-colors"
                            >
                                <span>➕ عميل جديد</span>
                            </button>
                            @endif
                        </div>

                        <!-- Customer Search Input with Dropdown -->
                        <div class="relative">
                            <input 
                                type="text" 
                                wire:model.live.debounce.150ms="customerSearch" 
                                @focus="open = true" 
                                @click="open = true"
                                placeholder="🔍 ابحث بالاسم أو رقم الهاتف..." 
                                class="w-full h-11 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 pl-8 text-xs font-bold text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500"
                            >
                            @if($customerSearch)
                            <button 
                                type="button" 
                                @click="open = true" 
                                wire:click="$set('customerSearch', '')" 
                                class="absolute left-2.5 top-1/2 -translate-y-1/2 w-6 h-6 rounded-md bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center text-xs font-bold hover:bg-slate-300 cursor-pointer"
                            >
                                ✕
                            </button>
                            @endif
                        </div>

                        <!-- Dropdown List of Filtered Customers -->
                        <div 
                            x-show="open" 
                            @click.away="open = false" 
                            x-transition.duration.150ms 
                            class="absolute z-50 right-0 left-0 mt-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-2xl overflow-hidden max-h-60 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800"
                            style="display: none;"
                        >
                            @forelse($filteredCustomers as $fc)
                            <button 
                                type="button" 
                                wire:click="selectCustomer({{ $fc->id }})" 
                                @click="open = false"
                                class="w-full p-2.5 text-right hover:bg-amber-500/10 transition-colors flex items-center justify-between gap-2 cursor-pointer {{ $customer_id == $fc->id ? 'bg-amber-500/15' : '' }}"
                            >
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                        <span>👤 {{ $fc->name }}</span>
                                        @if($customer_id == $fc->id)
                                            <span class="text-[10px] bg-emerald-500/20 text-emerald-600 px-1.5 py-0.5 rounded font-black">✓ محدد</span>
                                        @endif
                                    </div>
                                    @if($fc->phone)
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5" dir="ltr">
                                        📱 {{ $fc->phone }}
                                    </div>
                                    @endif
                                </div>
                                <div class="text-left shrink-0">
                                    <span class="text-[10px] px-2 py-0.5 rounded-md font-mono font-bold {{ bccomp($fc->current_balance, '0.000', 3) > 0 ? 'bg-rose-500/10 text-rose-600' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                                        {{ number_format($fc->current_balance, 2) }} ج.م
                                    </span>
                                </div>
                            </button>
                            @empty
                            <div class="p-3 text-center text-xs text-slate-400">
                                لا يوجد عميل مطابق للبحث
                                @if(auth()->user()?->can('customers.manage') || auth()->user()?->can('pos.access') || auth()->user()?->can('invoices.create'))
                                <button type="button" wire:click="openNewCustomerModal" @click="open = false" class="block mx-auto mt-1 text-amber-600 font-bold hover:underline cursor-pointer">
                                    ➕ تسجيل عميل جديد الآن
                                </button>
                                @endif
                            </div>
                            @endforelse
                        </div>

                        <!-- Selected Customer Info Pill -->
                        @if($selectedCustomer)
                        <div class="mt-1.5 flex items-center justify-between text-[11px] px-2.5 py-1 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300">
                            <span class="font-bold truncate">العميل الحالي: <b class="text-amber-600 dark:text-amber-400">{{ $selectedCustomer->name }}</b></span>
                            <span class="font-mono font-bold {{ bccomp($selectedCustomer->current_balance, '0.000', 3) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500' }}">
                                الرصيد: {{ number_format($selectedCustomer->current_balance, 2) }} ج.م
                            </span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Active Cart Items -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden flex flex-col">
                <div class="p-3.5 bg-slate-50/80 dark:bg-slate-950/60 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <span class="text-xs sm:text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🛒 أصناف الفاتورة الجاري تعديلها</span>
                        <span class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-700 dark:text-amber-300 font-mono text-xs font-bold">
                            {{ count($items) }} بنود
                        </span>
                    </span>
                    @if(count($items) > 0)
                    <button wire:click="$set('items', [])" class="text-xs text-rose-500 hover:text-rose-600 font-bold cursor-pointer transition-colors">
                        إفراغ السلة 🗑️
                    </button>
                    @endif
                </div>

                <!-- Items List (Touch Friendly Cards with Direct Editable Price & Weight) -->
                <div class="divide-y divide-slate-100 dark:divide-slate-800/80 max-h-[420px] overflow-y-auto p-2 space-y-2">
                    @forelse($items as $idx => $line)
                    <div class="p-3 rounded-xl bg-slate-50/60 dark:bg-slate-950/40 border border-slate-200/80 dark:border-slate-800/80 space-y-2.5">
                        
                        <!-- Line Header: Name + Stock + Delete -->
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h4 class="font-black text-slate-900 dark:text-white text-xs sm:text-sm">{{ $line['name'] }}</h4>
                                <div class="text-[10px] text-slate-400 font-mono flex items-center gap-2 mt-0.5">
                                    <span>الكود: <b class="text-slate-600 dark:text-slate-300">{{ $line['code'] }}</b></span>
                                    <span>• الرصيد المحسوب: {{ number_format($line['current_stock'], 2) }} {{ $line['unit'] }}</span>
                                </div>
                            </div>
                            
                            <button 
                                type="button" 
                                wire:click="removeItem({{ $idx }})" 
                                class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-600 hover:text-white flex items-center justify-center transition-all cursor-pointer active:scale-90 shrink-0"
                                title="حذف الصنف من الفاتورة"
                            >
                                ✕
                            </button>
                        </div>

                        <!-- 🏷️ Quick Price Switches & Last Price Helper -->
                        <div class="flex items-center gap-1.5 flex-wrap">
                            @if(!empty($line['price_retail']))
                            <button 
                                type="button" 
                                wire:click="setLinePriceRetail({{ $idx }})"
                                class="px-2 py-0.5 rounded text-[10px] font-bold border transition cursor-pointer {{ bccomp((string)$line['unit_price'], (string)$line['price_retail'], 2) === 0 ? 'bg-emerald-500/20 border-emerald-500 text-emerald-700 dark:text-emerald-300 font-black' : 'bg-white dark:bg-slate-900 text-slate-500 border-slate-200 dark:border-slate-700 hover:border-emerald-400' }}"
                                title="تطبيق سعر القطاعي"
                            >
                                🏷️ قطاعي: {{ number_format($line['price_retail'], 2) }}
                            </button>
                            @endif

                            @if(!empty($line['price_wholesale']) && bccomp((string)$line['price_wholesale'], '0.000', 2) > 0)
                            <button 
                                type="button" 
                                wire:click="setLinePriceWholesale({{ $idx }})"
                                class="px-2 py-0.5 rounded text-[10px] font-bold border transition cursor-pointer {{ bccomp((string)$line['unit_price'], (string)$line['price_wholesale'], 2) === 0 ? 'bg-purple-500/20 border-purple-500 text-purple-700 dark:text-purple-300 font-black' : 'bg-white dark:bg-slate-900 text-slate-500 border-slate-200 dark:border-slate-700 hover:border-purple-400' }}"
                                title="تطبيق سعر الجملة"
                            >
                                🏪 جملة: {{ number_format($line['price_wholesale'], 2) }}
                            </button>
                            @endif

                            @if(!empty($line['last_customer_price']))
                            <button 
                                type="button" 
                                wire:click="applyCustomerLastPrice({{ $idx }})"
                                class="px-2 py-0.5 rounded text-[10px] font-bold border transition cursor-pointer {{ bccomp((string)$line['unit_price'], (string)$line['last_customer_price']['unit_price'], 2) === 0 ? 'bg-amber-500/20 border-amber-500 text-amber-700 dark:text-amber-300 font-black' : 'bg-white dark:bg-slate-900 text-slate-500 border-slate-200 dark:border-slate-700 hover:border-amber-400' }}"
                                title="تطبيق آخر سعر بيع لهذا العميل"
                            >
                                💡 آخر سعر: {{ number_format($line['last_customer_price']['unit_price'], 2) }}
                            </button>
                            @endif
                        </div>

                        <!-- 🎯 Interactive Direct Editable Inputs (Weight / Qty & Price) -->
                        <div class="grid grid-cols-12 gap-2 items-center pt-1 bg-white dark:bg-slate-900 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800">
                            
                            <!-- 1. Editable Weight / Quantity Input with Stepper -->
                            <div class="col-span-6 sm:col-span-5 space-y-1">
                                <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 block">
                                    الوزن / الكمية ({{ $line['unit'] }}):
                                </label>
                                <div class="flex items-center gap-1">
                                    <button 
                                        type="button" 
                                        wire:click="decrementLineQty({{ $idx }}, '{{ $line['unit'] === 'كجم' ? '0.125' : '1.000' }}')" 
                                        class="w-7 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-rose-500 hover:text-white text-slate-700 dark:text-slate-200 font-black text-sm flex items-center justify-center transition-all cursor-pointer shrink-0"
                                    >
                                        -
                                    </button>
                                    
                                    <input 
                                        type="number" 
                                        step="0.001" 
                                        min="0.001" 
                                        wire:model.live.debounce.250ms="items.{{ $idx }}.quantity" 
                                        class="w-full h-8 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg px-2 text-center text-xs font-mono font-black text-emerald-600 dark:text-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                    >

                                    <button 
                                        type="button" 
                                        wire:click="incrementLineQty({{ $idx }}, '{{ $line['unit'] === 'كجم' ? '0.125' : '1.000' }}')" 
                                        class="w-7 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-emerald-600 hover:text-white text-slate-700 dark:text-slate-200 font-black text-sm flex items-center justify-center transition-all cursor-pointer shrink-0"
                                    >
                                        +
                                    </button>
                                </div>
                            </div>

                            <!-- 2. Editable Unit Price Input -->
                            <div class="col-span-6 sm:col-span-4 space-y-1">
                                <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 block">
                                    سعر البيع (ج.م):
                                </label>
                                <div class="relative">
                                    <input 
                                        type="number" 
                                        step="0.01" 
                                        min="0" 
                                        wire:model.live.debounce.250ms="items.{{ $idx }}.unit_price" 
                                        class="w-full h-8 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg px-2 text-center text-xs font-mono font-black text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-amber-500"
                                    >
                                </div>
                            </div>

                            <!-- 3. Line Total Display -->
                            <div class="col-span-12 sm:col-span-3 text-left sm:text-left pt-1 sm:pt-0">
                                <span class="text-[10px] text-slate-400 block">الإجمالي</span>
                                <span class="text-sm font-black font-mono text-slate-900 dark:text-white block">
                                    {{ number_format($line['total_price'], 2) }} <span class="text-[9px] font-normal">ج.م</span>
                                </span>
                            </div>
                        </div>

                        <!-- Micro Weight Buttons for Kg items in cart -->
                        @if($line['unit'] === 'كجم')
                        <div class="flex items-center gap-1 pt-0.5">
                            <button type="button" wire:click="setLineWeightPreset({{ $idx }}, '0.125')" class="flex-1 py-1 bg-slate-200/70 dark:bg-slate-800 hover:bg-amber-600 hover:text-white rounded-lg text-[10px] font-mono text-slate-700 dark:text-slate-300 font-bold transition-colors cursor-pointer">125g</button>
                            <button type="button" wire:click="setLineWeightPreset({{ $idx }}, '0.250')" class="flex-1 py-1 bg-slate-200/70 dark:bg-slate-800 hover:bg-amber-600 hover:text-white rounded-lg text-[10px] font-mono text-slate-700 dark:text-slate-300 font-bold transition-colors cursor-pointer">250g</button>
                            <button type="button" wire:click="setLineWeightPreset({{ $idx }}, '0.500')" class="flex-1 py-1 bg-slate-200/70 dark:bg-slate-800 hover:bg-amber-600 hover:text-white rounded-lg text-[10px] font-mono text-slate-700 dark:text-slate-300 font-bold transition-colors cursor-pointer">500g</button>
                            <button type="button" wire:click="setLineWeightPreset({{ $idx }}, '1.000')" class="flex-1 py-1 bg-emerald-100 dark:bg-emerald-950 border border-emerald-300 dark:border-emerald-800 hover:bg-emerald-600 hover:text-white rounded-lg text-[10px] font-mono text-emerald-800 dark:text-emerald-300 font-black transition-colors cursor-pointer">1kg</button>
                        </div>
                        @endif

                    </div>
                    @empty
                    <div class="py-12 text-center text-slate-400 text-xs">
                        🛒 السلة فارغة. المس الأصناف على اليمين لإضافتها فوراً.
                    </div>
                    @endforelse
                </div>
            </div>

            <!-- Financial Summary & Payment Box -->
            <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                
                <!-- Payment Type Big Toggle Buttons -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">نوع الفاتورة والسداد:</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button 
                            type="button" 
                            wire:click="quickSetPaymentType('cash')" 
                            class="h-12 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 border transition-all cursor-pointer active:scale-95 {{ $payment_type === 'cash' ? 'bg-emerald-600 border-emerald-600 text-white shadow-lg shadow-emerald-600/30' : 'bg-slate-50 dark:bg-slate-950 border-slate-300 dark:border-slate-800 text-slate-700 dark:text-slate-300' }}"
                        >
                            <span>💵</span>
                            <span>كاش فوري</span>
                        </button>

                        <button 
                            type="button" 
                            wire:click="quickSetPaymentType('credit')" 
                            class="h-12 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 border transition-all cursor-pointer active:scale-95 {{ $payment_type === 'credit' ? 'bg-amber-600 border-amber-600 text-white shadow-lg shadow-amber-600/30' : 'bg-slate-50 dark:bg-slate-950 border-slate-300 dark:border-slate-800 text-slate-700 dark:text-slate-300' }}"
                        >
                            <span>💳</span>
                            <span>آجل (ذمم)</span>
                        </button>

                        <button 
                            type="button" 
                            wire:click="quickSetPaymentType('partial')" 
                            class="h-12 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 border transition-all cursor-pointer active:scale-95 {{ $payment_type === 'partial' ? 'bg-indigo-600 border-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'bg-slate-50 dark:bg-slate-950 border-slate-300 dark:border-slate-800 text-slate-700 dark:text-slate-300' }}"
                        >
                            <span>⏳</span>
                            <span>دفع جزئي</span>
                        </button>
                    </div>
                </div>

                <!-- Payment Method Quick Selection (Visible when paying cash or partial) -->
                @if($payment_type !== 'credit')
                <div class="p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800/80 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-[11px] font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1">
                            <span>💳</span>
                            <span>وسيلة التحصيل والدفع:</span>
                        </label>
                        <span class="text-[10px] text-slate-400">
                            @if($payment_method === 'cash')
                            (يدخل درج الكاشير)
                            @else
                            (تحصيل إلكتروني/بنكي)
                            @endif
                        </span>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <!-- كاش -->
                        <button 
                            type="button" 
                            wire:click="quickSetPaymentMethod('cash')" 
                            class="py-2.5 px-2 rounded-xl text-xs font-bold flex flex-col items-center justify-center gap-1 border transition-all cursor-pointer active:scale-95 {{ $payment_method === 'cash' ? 'bg-emerald-600 border-emerald-600 text-white shadow-md shadow-emerald-600/30' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500' }}"
                        >
                            <span class="text-base">💵</span>
                            <span>كاش نقدي</span>
                        </button>

                        <!-- إنستاباي -->
                        <button 
                            type="button" 
                            wire:click="quickSetPaymentMethod('instapay')" 
                            class="py-2.5 px-2 rounded-xl text-xs font-bold flex flex-col items-center justify-center gap-1 border transition-all cursor-pointer active:scale-95 {{ $payment_method === 'instapay' ? 'bg-purple-600 border-purple-600 text-white shadow-md shadow-purple-600/30' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:border-purple-500' }}"
                        >
                            <span class="text-base">⚡</span>
                            <span>إنستاباي</span>
                        </button>

                        <!-- محفظة -->
                        <button 
                            type="button" 
                            wire:click="quickSetPaymentMethod('e_wallet')" 
                            class="py-2.5 px-2 rounded-xl text-xs font-bold flex flex-col items-center justify-center gap-1 border transition-all cursor-pointer active:scale-95 {{ $payment_method === 'e_wallet' ? 'bg-rose-600 border-rose-600 text-white shadow-md shadow-rose-600/30' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:border-rose-500' }}"
                        >
                            <span class="text-base">📲</span>
                            <span>محفظة ذكية</span>
                        </button>
                    </div>
                </div>
                @endif

                <!-- Quick Cash Presets Bar -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">⚡ سداد نقدي سريع:</label>
                    <div class="grid grid-cols-4 gap-1.5">
                        <button type="button" wire:click="quickSetPaidExact" class="h-10 rounded-xl bg-emerald-500/15 hover:bg-emerald-600 hover:text-white border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-xs font-black transition-all active:scale-95 cursor-pointer flex items-center justify-center">
                            المبلغ بالضبط
                        </button>
                        <button type="button" wire:click="quickSetPaidAmount('50.000')" class="h-10 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-amber-600 hover:text-white text-slate-800 dark:text-slate-200 text-xs font-bold font-mono transition-all active:scale-95 cursor-pointer flex items-center justify-center">
                            50 ج.م
                        </button>
                        <button type="button" wire:click="quickSetPaidAmount('100.000')" class="h-10 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-amber-600 hover:text-white text-slate-800 dark:text-slate-200 text-xs font-bold font-mono transition-all active:scale-95 cursor-pointer flex items-center justify-center">
                            100 ج.م
                        </button>
                        <button type="button" wire:click="quickSetPaidAmount('200.000')" class="h-10 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-amber-600 hover:text-white text-slate-800 dark:text-slate-200 text-xs font-bold font-mono transition-all active:scale-95 cursor-pointer flex items-center justify-center">
                            200 ج.م
                        </button>
                    </div>
                </div>

                <!-- Quick Discount Buttons (if allowed) -->
                @can('invoices.discount')
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">🎁 خصم سريع:</label>
                    <div class="grid grid-cols-4 gap-1.5">
                        <button type="button" wire:click="quickSetDiscountPercent('0.000')" class="h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-xs font-bold hover:bg-slate-200 cursor-pointer">بدون خصم</button>
                        <button type="button" wire:click="quickSetDiscountPercent('5.000')" class="h-8 rounded-lg bg-amber-500/10 hover:bg-amber-500 text-amber-700 dark:text-amber-300 hover:text-white text-xs font-bold cursor-pointer">5%</button>
                        <button type="button" wire:click="quickSetDiscountPercent('10.000')" class="h-8 rounded-lg bg-amber-500/10 hover:bg-amber-500 text-amber-700 dark:text-amber-300 hover:text-white text-xs font-bold cursor-pointer">10%</button>
                        <button type="button" wire:click="quickSetDiscountPercent('15.000')" class="h-8 rounded-lg bg-amber-500/10 hover:bg-amber-500 text-amber-700 dark:text-amber-300 hover:text-white text-xs font-bold cursor-pointer">15%</button>
                    </div>
                </div>
                @endcan

                <!-- ========================================== -->
                <!-- 🚚 Multi-Expenses / Shipping Dynamic Box   -->
                <!-- ========================================== -->
                <div class="p-3 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800/80 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">🚚 مصاريف الشحن والخدمات الإضافية:</span>
                            @if(bccomp($additional_expenses_total, '0.000', 3) > 0)
                                <span class="text-[11px] font-mono font-black text-amber-600 dark:text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded-lg border border-amber-500/20">
                                    +{{ number_format($additional_expenses_total, 2) }} ج.م
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Quick Preset Buttons -->
                    <div class="flex flex-wrap gap-1.5">
                        <button 
                            type="button" 
                            wire:click="addExpenseRow('شحن وتوصيل', 'customer_account')" 
                            class="px-2 py-1 rounded-lg bg-slate-200 dark:bg-slate-800 hover:bg-amber-600 hover:text-white text-[10px] font-bold text-slate-700 dark:text-slate-300 transition-colors cursor-pointer flex items-center gap-1"
                        >
                            <span>🚚 + شحن</span>
                        </button>
                        <button 
                            type="button" 
                            wire:click="addExpenseRow('تغليف وكراتين', 'customer_account')" 
                            class="px-2 py-1 rounded-lg bg-slate-200 dark:bg-slate-800 hover:bg-amber-600 hover:text-white text-[10px] font-bold text-slate-700 dark:text-slate-300 transition-colors cursor-pointer flex items-center gap-1"
                        >
                            <span>📦 + تغليف</span>
                        </button>
                        <button 
                            type="button" 
                            wire:click="addExpenseRow('إكرامية طيار الدليفري', 'treasury_cash')" 
                            class="px-2 py-1 rounded-lg bg-slate-200 dark:bg-slate-800 hover:bg-amber-600 hover:text-white text-[10px] font-bold text-slate-700 dark:text-slate-300 transition-colors cursor-pointer flex items-center gap-1"
                        >
                            <span>🎁 + إكرامية</span>
                        </button>
                        <button 
                            type="button" 
                            wire:click="addExpenseRow('مصروف إضافي', 'customer_account')" 
                            class="px-2 py-1 rounded-lg bg-amber-500/10 hover:bg-amber-600 hover:text-white text-[10px] font-bold text-amber-700 dark:text-amber-400 border border-amber-500/20 transition-colors cursor-pointer flex items-center gap-1"
                        >
                            <span>➕ بند مخصص</span>
                        </button>
                    </div>

                    <!-- Dynamic Expense Rows -->
                    @if(!empty($additional_expenses) && count($additional_expenses) > 0)
                    <div class="space-y-2 pt-1">
                        @foreach($additional_expenses as $eIdx => $exp)
                        <div class="p-2.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 space-y-1.5 text-xs">
                            <div class="grid grid-cols-12 gap-2 items-center">
                                <div class="col-span-6">
                                    <input 
                                        type="text" 
                                        wire:model.live.debounce.250ms="additional_expenses.{{ $eIdx }}.title" 
                                        placeholder="اسم المصروف / الخدمة" 
                                        class="w-full h-8 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg px-2 text-[11px] font-bold text-slate-800 dark:text-slate-200 focus:ring-1 focus:ring-amber-500"
                                    >
                                </div>
                                <div class="col-span-5 relative">
                                    <input 
                                        type="number" 
                                        step="0.01" 
                                        min="0" 
                                        wire:model.live.debounce.250ms="additional_expenses.{{ $eIdx }}.amount" 
                                        placeholder="المبلغ ج.م" 
                                        class="w-full h-8 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg px-2 text-[11px] font-mono font-bold text-amber-600 dark:text-amber-400 focus:ring-1 focus:ring-amber-500"
                                    >
                                </div>
                                <div class="col-span-1 text-center">
                                    <button 
                                        type="button" 
                                        wire:click="removeExpenseRow({{ $eIdx }})" 
                                        class="w-6 h-6 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-600 hover:text-white flex items-center justify-center text-[10px] transition-colors cursor-pointer"
                                        title="حذف المصروف"
                                    >
                                        ✕
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-12 gap-2 items-center pt-1 border-t border-slate-100 dark:border-slate-800 text-[10px]">
                                <div class="col-span-5 text-slate-500 font-bold">جهة التحمل / السداد:</div>
                                <div class="col-span-7">
                                    <select 
                                        wire:model.live="additional_expenses.{{ $eIdx }}.paid_by" 
                                        class="w-full h-7 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg px-1 text-[10px] font-bold text-slate-700 dark:text-slate-300"
                                    >
                                        <option value="customer_account">مضاف على حساب العميل بالفاتورة (الزبون هيدفعه)</option>
                                        <option value="treasury_cash">مدفوع كاش نقدًا من الخزينة (المحل اللي هيشيله - سند صرف)</option>
                                        <option value="treasury_instapay">مدفوع عبر إنستاباي من الحساب (سند صرف)</option>
                                        <option value="treasury_e_wallet">مدفوع من المحفظة الذكية (سند صرف)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>

                <!-- Interactive Touch Numpad (Collapsible) -->
                <div x-show="showNumpad" x-transition.duration.200ms class="p-3 bg-slate-100 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-600 dark:text-slate-400">لوحة الأرقام باللمس:</span>
                        <div class="flex items-center gap-1">
                            <button type="button" @click="numpadTarget = 'paid_amount'" :class="numpadTarget === 'paid_amount' ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-700'" class="px-2 py-0.5 rounded text-[10px] font-bold">المدفوع</button>
                            <button type="button" @click="numpadTarget = 'discount_value'" :class="numpadTarget === 'discount_value' ? 'bg-amber-600 text-white' : 'bg-slate-200 text-slate-700'" class="px-2 py-0.5 rounded text-[10px] font-bold">الخصم</button>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-2">
                        <template x-for="btn in ['1','2','3','4','5','6','7','8','9','.','0','backspace']">
                            <button 
                                type="button" 
                                @click="pressNum(btn)"
                                class="h-12 rounded-xl bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 font-mono font-black text-base text-slate-900 dark:text-white flex items-center justify-center active:scale-95 shadow-sm hover:bg-amber-500 hover:text-white transition-all cursor-pointer"
                                x-text="btn === 'backspace' ? '⌫' : btn"
                            ></button>
                        </template>
                    </div>
                </div>

                <!-- Final Calculations (Big Numbers) -->
                <div class="pt-3 border-t border-slate-200 dark:border-slate-800 space-y-2">
                    <div class="flex items-center justify-between text-xs text-slate-600 dark:text-slate-400">
                        <span>إجمالي الأصناف:</span>
                        <span class="font-mono font-bold">{{ number_format($subtotal, 2) }} ج.م</span>
                    </div>

                    @if(bccomp($discount_amount, '0.000', 3) > 0)
                    <div class="flex items-center justify-between text-xs text-rose-600 dark:text-rose-400 font-bold">
                        <span>الخصم الممنوح:</span>
                        <span class="font-mono">- {{ number_format($discount_amount, 2) }} ج.م</span>
                    </div>
                    @endif

                    @if(bccomp($shipping_cost, '0.000', 3) > 0)
                    <div class="flex items-center justify-between text-xs text-amber-600 dark:text-amber-400 font-bold">
                        <span>مصاريف الشحن / التوصيل:</span>
                        <span class="font-mono">+ {{ number_format($shipping_cost, 2) }} ج.م</span>
                    </div>
                    @endif

                    <!-- NET TOTAL HIGHLIGHT -->
                    <div class="p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-between">
                        <span class="text-sm font-black text-slate-900 dark:text-white">الصافي بعد التعديل:</span>
                        <span class="text-xl sm:text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400">
                            {{ number_format($net_total, 2) }} <span class="text-xs font-normal">ج.م</span>
                        </span>
                    </div>

                    @if($payment_type === 'partial')
                    <div class="flex items-center justify-between text-xs text-amber-700 dark:text-amber-400 font-bold">
                        <span>المتبقي ذمم:</span>
                        <span class="font-mono font-black text-sm">{{ number_format($remaining_amount, 2) }} ج.م</span>
                    </div>
                    @endif
                </div>

                <!-- Large Checkout Touch Buttons -->
                <div class="grid grid-cols-2 gap-3 pt-2">
                    <button 
                        type="button" 
                        wire:click="saveInvoice" 
                        wire:loading.attr="disabled"
                        class="h-14 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-black shadow-xl shadow-emerald-600/30 transition-all active:scale-95 cursor-pointer flex items-center justify-center gap-2"
                    >
                        <span wire:loading.remove wire:target="saveInvoice">💾 حفظ وتحديث الفاتورة</span>
                        <span wire:loading wire:target="saveInvoice">جاري الحفظ...</span>
                    </button>

                    <button 
                        type="button" 
                        wire:click="saveInvoice('print')" 
                        wire:loading.attr="disabled"
                        class="h-14 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-black shadow-xl shadow-indigo-600/30 transition-all active:scale-95 cursor-pointer flex items-center justify-center gap-2"
                    >
                        <span wire:loading.remove wire:target="saveInvoice('print')">🖨️ حفظ وطباعة</span>
                        <span wire:loading wire:target="saveInvoice('print')">جاري التجهيز...</span>
                    </button>
                </div>

            </div>

        </div>

    </div>

    <!-- Quick Add Customer Modal -->
    @if($showNewCustomerModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 backdrop-blur-xs p-4 overflow-y-auto" dir="rtl">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 w-full max-w-md shadow-2xl space-y-4 animate-in fade-in zoom-in duration-150">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-lg font-bold">
                        👤
                    </div>
                    <div>
                        <h3 class="font-black text-sm text-slate-900 dark:text-white">تسجيل عميل جديد فورياً</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">سيتم حفظ العميل وتحديده للفاتورة الحالية مباشرة</p>
                    </div>
                </div>
                <button 
                    type="button" 
                    wire:click="closeNewCustomerModal" 
                    class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-rose-500 hover:text-white text-slate-500 flex items-center justify-center transition-colors cursor-pointer text-xs font-bold"
                >
                    ✕
                </button>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        اسم العميل <span class="text-rose-500">*</span>:
                    </label>
                    <input 
                        type="text" 
                        wire:model="newCustomerName" 
                        placeholder="مثال: مطعم الفيروز / كافيه السعادة..." 
                        class="w-full h-10 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 text-xs font-bold text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500"
                        autofocus
                    >
                    @error('newCustomerName')
                        <span class="text-[11px] text-rose-500 font-bold block mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">رقم الهاتف / الموبايل:</label>
                    <input 
                        type="text" 
                        wire:model="newCustomerPhone" 
                        placeholder="010xxxxxxxx" 
                        class="w-full h-10 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 text-xs font-mono font-bold text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500"
                        dir="ltr"
                    >
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">العنوان أو المنطقة:</label>
                    <input 
                        type="text" 
                        wire:model="newCustomerAddress" 
                        placeholder="المنطقة أو تفاصيل التوصيل..." 
                        class="w-full h-10 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 text-xs font-bold text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500"
                    >
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">ملاحظات إضافية:</label>
                    <textarea 
                        wire:model="newCustomerNotes" 
                        rows="2" 
                        placeholder="أي تفاصيل خاصة بالحساب أو التوصيل..." 
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500"
                    ></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                <button 
                    type="button" 
                    wire:click="closeNewCustomerModal" 
                    class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold transition-colors cursor-pointer"
                >
                    إلغاء
                </button>
                <button 
                    type="button" 
                    wire:click="quickCreateCustomer" 
                    class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black shadow-lg shadow-emerald-600/30 transition-all active:scale-95 cursor-pointer flex items-center gap-1.5"
                >
                    <span>✓ تسجيل وتطبيق فوراً</span>
                </button>
            </div>
        </div>
    </div>
    @endif

</div>
