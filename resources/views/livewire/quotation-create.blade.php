<div class="space-y-4 pb-20 select-none" dir="rtl" x-data="{ mobileTab: 'catalog' }">

    <!-- Top Command & Pricing Tier Bar -->
    <div class="bg-white dark:bg-slate-900 p-3.5 sm:p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-3 sm:gap-4">
        <!-- Title & Store Info -->
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl sm:text-2xl font-bold border border-amber-500/20 shrink-0 shadow-inner">
                📋
            </div>
            <div>
                <h1 class="text-base sm:text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>إنشاء عرض أسعار (Price Quotation)</span>
                </h1>
                <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                    <span class="text-[11px] sm:text-xs font-bold text-slate-500 dark:text-slate-400">الفرع:</span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[11px] sm:text-xs font-bold font-mono">
                        🏬 {{ $currentStore?->name ?? 'الفرع الرئيسي' }}
                    </span>
                    <span class="text-slate-300 dark:text-slate-700">•</span>
                    <span class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400">تاريخ العرض: <b class="font-mono text-slate-700 dark:text-slate-300">{{ $quotation_date }}</b></span>
                </div>
            </div>
        </div>

        <!-- 🎛️ Master Pricing Tier Selector (Wholesale vs Retail) -->
        <div class="flex items-center gap-2 flex-wrap justify-between sm:justify-end">
            <div class="w-full sm:w-auto p-1 bg-slate-100 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 flex items-center gap-1">
                <button 
                    type="button" 
                    wire:click="setPricingTier('wholesale')" 
                    class="flex-1 sm:flex-initial px-3 sm:px-3.5 py-2.5 sm:py-2 rounded-xl text-xs font-black flex items-center justify-center gap-1.5 transition-all cursor-pointer {{ $pricing_tier === 'wholesale' ? 'bg-purple-600 text-white shadow-md shadow-purple-600/30' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}"
                >
                    <span>🏪 أسعار جملة (تجارية)</span>
                </button>

                <button 
                    type="button" 
                    wire:click="setPricingTier('retail')" 
                    class="flex-1 sm:flex-initial px-3 sm:px-3.5 py-2.5 sm:py-2 rounded-xl text-xs font-black flex items-center justify-center gap-1.5 transition-all cursor-pointer {{ $pricing_tier === 'retail' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/30' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}"
                >
                    <span>🏷️ أسعار قطاعي</span>
                </button>
            </div>

            <a 
                href="{{ route('quotations.index') }}" 
                class="px-3 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition-all flex items-center gap-1 shrink-0"
            >
                <span>← عروض الأسعار</span>
            </a>
        </div>
    </div>

    <!-- 📱 Mobile Tab Switcher (Visible on Mobile / Tablet < XL) -->
    <div class="xl:hidden sticky top-2 z-30 flex items-center p-1 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md rounded-2xl border border-slate-200 dark:border-slate-800 shadow-lg text-xs font-black">
        <button 
            type="button" 
            @click="mobileTab = 'catalog'" 
            :class="mobileTab === 'catalog' ? 'bg-amber-600 text-white shadow-md shadow-amber-600/30' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
            class="flex-1 py-3 rounded-xl transition-all flex items-center justify-center gap-1.5 cursor-pointer"
        >
            <span class="text-base">📦</span>
            <span>اختيار الأصناف</span>
        </button>

        <button 
            type="button" 
            @click="mobileTab = 'cart'" 
            :class="mobileTab === 'cart' ? 'bg-amber-600 text-white shadow-md shadow-amber-600/30' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
            class="flex-1 py-3 rounded-xl transition-all flex items-center justify-center gap-1.5 cursor-pointer relative"
        >
            <span class="text-base">📋</span>
            <span>تفاصيل العرض ({{ count($items) }})</span>
            @if(count($items) > 0)
                <span class="px-2 py-0.5 rounded-full bg-emerald-500 text-white font-mono text-[10px] font-black">
                    {{ number_format($net_total, 0) }}ج
                </span>
            @endif
        </button>
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

    <!-- Main Grid: Catalog (Left) vs Quotation Summary (Right) -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-5 items-start">
        
        <!-- ========================================== -->
        <!-- 📦 Left Catalog Column (7 Cols on XL)     -->
        <!-- ========================================== -->
        <div 
            :class="mobileTab === 'catalog' ? 'block' : 'hidden xl:block'"
            class="xl:col-span-7 space-y-4"
        >
            
            <!-- Category Touch Filter Bar -->
            <div class="bg-white dark:bg-slate-900 p-3.5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
                <!-- Search & Quick Barcode Scanner -->
                <div class="relative">
                    <input 
                        type="text" 
                        wire:model.live.debounce.150ms="searchQuery" 
                        placeholder="🔍 ابحث بالاسم أو الباركود أو كود الصنف لإضافته لعرض السعر..." 
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500"
                        autofocus
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
                    $wholesaleP = (string)($prod->wholesale_price && bccomp((string)$prod->wholesale_price, '0.000', 2) > 0 ? $prod->wholesale_price : $prod->selling_price);
                    $retailP = (string)($prod->selling_price ?: $prod->getEffectivePriceForStore($store_id));
                    $activeTierPrice = ($pricing_tier === 'wholesale') ? $wholesaleP : $retailP;
                @endphp
                <div class="p-3 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-amber-500/60 transition-all duration-200 flex flex-col justify-between group shadow-sm hover:shadow-md">
                    <div>
                        <!-- Header & Stock Badge -->
                        <div class="flex items-start justify-between gap-1 mb-1.5">
                            <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                                {{ $prod->code }}
                            </span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-md font-mono bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                رصيد: {{ number_format($prodStock, 2) }} {{ $prod->unit }}
                            </span>
                        </div>

                        <!-- Product Name -->
                        <h3 class="font-black text-xs sm:text-sm text-slate-900 dark:text-white line-clamp-2 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors leading-snug">
                            {{ $prod->name }}
                        </h3>

                        <!-- Price Info According to Tier -->
                        <div class="mt-2 space-y-0.5">
                            <div class="flex items-baseline justify-between gap-1 flex-wrap">
                                <span class="text-sm font-black font-mono {{ $pricing_tier === 'wholesale' ? 'text-purple-600 dark:text-purple-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                    {{ number_format($activeTierPrice, 2) }} <span class="text-[10px] font-normal">ج.م</span>
                                </span>
                                <span class="text-[9px] px-1.5 py-0.5 rounded font-bold {{ $pricing_tier === 'wholesale' ? 'bg-purple-500/10 text-purple-600' : 'bg-emerald-500/10 text-emerald-600' }}">
                                    {{ $pricing_tier === 'wholesale' ? 'سعر الجملة' : 'سعر القطاعي' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-[10px] text-slate-400 font-mono">
                                <span>قطاعي: {{ number_format($retailP, 2) }}</span>
                                <span>جملة: {{ number_format($wholesaleP, 2) }}</span>
                            </div>
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
                                    class="h-8 rounded-lg {{ $pricing_tier === 'wholesale' ? 'bg-purple-600 hover:bg-purple-500' : 'bg-emerald-600 hover:bg-emerald-500' }} text-white text-[10px] font-black font-mono transition-all active:scale-90 cursor-pointer flex items-center justify-center shadow-md"
                                    title="كيلو كامل (1 كجم)"
                                >
                                    1kg
                                </button>
                            </div>
                        @else
                            <button 
                                type="button" 
                                wire:click="addItem({{ $prod->id }}, '1.000')" 
                                class="w-full h-9 rounded-xl {{ $pricing_tier === 'wholesale' ? 'bg-purple-500/15 text-purple-700 dark:text-purple-300 hover:bg-purple-600' : 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-600' }} hover:text-white text-xs font-bold transition-all active:scale-95 cursor-pointer shadow-sm flex items-center justify-center gap-1.5"
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
        <!-- 📋 Right Quotation Column (5 Cols on XL)   -->
        <!-- ========================================== -->
        <div 
            :class="mobileTab === 'cart' ? 'block' : 'hidden xl:block'"
            class="xl:col-span-5 space-y-4"
        >
            
            <!-- Customer & Validity Card -->
            <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
                <!-- Customer Selection -->
                <div class="relative" x-data="{ open: false }">
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">👤 العميل أو الجهة المستهدفة:</label>
                        <button 
                            type="button" 
                            wire:click="openNewCustomerModal" 
                            class="text-[11px] font-black text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300 flex items-center gap-1 cursor-pointer transition-colors"
                        >
                            <span>➕ عميل جديد</span>
                        </button>
                    </div>

                    <!-- Search Input with Dropdown -->
                    <div class="relative">
                        <input 
                            type="text" 
                            wire:model.live.debounce.150ms="customerSearch" 
                            @focus="open = true" 
                            @click="open = true"
                            placeholder="🔍 ابحث في العملاء أو اكتب اسم العميل مباشرة..." 
                            class="w-full h-11 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 pl-8 text-xs font-bold text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500"
                        >
                        @if($customerSearch)
                        <button 
                            type="button" 
                            @click="open = true" 
                            wire:click="clearCustomer" 
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
                        </button>
                        @empty
                        <div class="p-3 text-center text-xs text-slate-400">
                            لا يوجد عميل مسجل بهذا الاسم. سيتم استخدامه كاسم حر للعرض.
                        </div>
                        @endforelse
                    </div>

                    <!-- Prospective Customer Phone (if not in DB or needs phone) -->
                    <div class="grid grid-cols-2 gap-2 mt-2">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-0.5">اسم العميل المطبوع:</label>
                            <input 
                                type="text" 
                                wire:model="customer_name" 
                                placeholder="اسم العميل بالعرض" 
                                class="w-full h-9 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg px-2 text-xs font-bold text-slate-900 dark:text-white"
                            >
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-0.5">رقم واتساب العميل:</label>
                            <input 
                                type="text" 
                                wire:model="customer_phone" 
                                placeholder="010xxxxxxxx" 
                                class="w-full h-9 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg px-2 text-xs font-mono font-bold text-slate-900 dark:text-white"
                                dir="ltr"
                            >
                        </div>
                    </div>
                </div>

                <!-- Validity Period Quick Bar -->
                <div class="pt-2 border-t border-slate-100 dark:border-slate-800 space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">⏳ مدة سريان عرض السعر:</label>
                        <span class="text-xs font-mono font-bold text-amber-600 dark:text-amber-400">ينتهي: {{ $valid_until }}</span>
                    </div>

                    <div class="grid grid-cols-4 gap-1.5">
                        <button 
                            type="button" 
                            wire:click="setValidityDays(3)" 
                            class="py-1.5 rounded-lg text-xs font-bold border transition-colors cursor-pointer {{ $validity_days === 3 ? 'bg-amber-600 text-white border-amber-600' : 'bg-slate-50 dark:bg-slate-950 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300' }}"
                        >
                            3 أيام
                        </button>
                        <button 
                            type="button" 
                            wire:click="setValidityDays(7)" 
                            class="py-1.5 rounded-lg text-xs font-bold border transition-colors cursor-pointer {{ $validity_days === 7 ? 'bg-amber-600 text-white border-amber-600' : 'bg-slate-50 dark:bg-slate-950 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300' }}"
                        >
                            أسبوع (7)
                        </button>
                        <button 
                            type="button" 
                            wire:click="setValidityDays(15)" 
                            class="py-1.5 rounded-lg text-xs font-bold border transition-colors cursor-pointer {{ $validity_days === 15 ? 'bg-amber-600 text-white border-amber-600' : 'bg-slate-50 dark:bg-slate-950 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300' }}"
                        >
                            15 يوم
                        </button>
                        <button 
                            type="button" 
                            wire:click="setValidityDays(30)" 
                            class="py-1.5 rounded-lg text-xs font-bold border transition-colors cursor-pointer {{ $validity_days === 30 ? 'bg-amber-600 text-white border-amber-600' : 'bg-slate-50 dark:bg-slate-950 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300' }}"
                        >
                            شهر (30)
                        </button>
                    </div>
                </div>
            </div>

            <!-- Active Quotation Items -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden flex flex-col">
                <div class="p-3.5 bg-slate-50/80 dark:bg-slate-950/60 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <span class="text-xs sm:text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>📋 بنود عرض السعر</span>
                        <span class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-700 dark:text-amber-300 font-mono text-xs font-bold">
                            {{ count($items) }} أصناف
                        </span>
                    </span>
                    @if(count($items) > 0)
                    <button wire:click="$set('items', [])" class="text-xs text-rose-500 hover:text-rose-600 font-bold cursor-pointer transition-colors">
                        إفراغ القائمة 🗑️
                    </button>
                    @endif
                </div>

                <!-- Items List with Direct Editable Price & Weight Inputs -->
                <div class="divide-y divide-slate-100 dark:divide-slate-800/80 max-h-[420px] overflow-y-auto p-2 space-y-2">
                    @forelse($items as $idx => $line)
                    <div class="p-3 rounded-xl bg-slate-50/60 dark:bg-slate-950/40 border border-slate-200/80 dark:border-slate-800/80 space-y-2.5">
                        
                        <!-- Line Header: Name + Stock + Delete -->
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h4 class="font-black text-slate-900 dark:text-white text-xs sm:text-sm">{{ $line['name'] }}</h4>
                                <div class="text-[10px] text-slate-400 font-mono flex items-center gap-2 mt-0.5">
                                    <span>الكود: <b class="text-slate-600 dark:text-slate-300">{{ $line['code'] }}</b></span>
                                    <span>• الرصيد المتاح: {{ number_format($line['current_stock'], 2) }} {{ $line['unit'] }}</span>
                                </div>
                            </div>
                            
                            <button 
                                type="button" 
                                wire:click="removeItem({{ $idx }})" 
                                class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-600 hover:text-white flex items-center justify-center transition-all cursor-pointer active:scale-90 shrink-0"
                                title="حذف الصنف من العرض"
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
                                    سعر الوحدة (ج.م):
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
                        📋 لا توجد أصناف بالعرض بعد. اختر الأصناف من الكتالوج على اليمين لإضافتها فوراً.
                    </div>
                    @endforelse
                </div>
            </div>

            <!-- Financial Summary, Terms & Action Box -->
            <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                
                <!-- Quick Discount Bar -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">🎁 خصم خاص على العرض:</label>
                    <div class="grid grid-cols-4 gap-1.5">
                        <button type="button" wire:click="quickSetDiscountPercent('0.000')" class="h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-xs font-bold hover:bg-slate-200 cursor-pointer">بدون خصم</button>
                        <button type="button" wire:click="quickSetDiscountPercent('5.000')" class="h-8 rounded-lg bg-amber-500/10 hover:bg-amber-500 text-amber-700 dark:text-amber-300 hover:text-white text-xs font-bold cursor-pointer">5%</button>
                        <button type="button" wire:click="quickSetDiscountPercent('10.000')" class="h-8 rounded-lg bg-amber-500/10 hover:bg-amber-500 text-amber-700 dark:text-amber-300 hover:text-white text-xs font-bold cursor-pointer">10%</button>
                        <button type="button" wire:click="quickSetDiscountPercent('15.000')" class="h-8 rounded-lg bg-amber-500/10 hover:bg-amber-500 text-amber-700 dark:text-amber-300 hover:text-white text-xs font-bold cursor-pointer">15%</button>
                    </div>
                </div>

                <!-- Shipping & Notes Accordion -->
                <div class="space-y-2 pt-1 border-t border-slate-100 dark:border-slate-800">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-0.5">تكلفة الشحن التقديرية (ج.م):</label>
                            <input 
                                type="number" 
                                step="0.01" 
                                min="0" 
                                wire:model.live.debounce.250ms="shipping_cost" 
                                placeholder="0.00" 
                                class="w-full h-9 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg px-2 text-xs font-mono font-bold text-slate-900 dark:text-white"
                            >
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-0.5">ملاحظات العرض للعميل:</label>
                            <input 
                                type="text" 
                                wire:model="notes" 
                                placeholder="أي ملاحظة أو شروط خاصة..." 
                                class="w-full h-9 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg px-2 text-xs font-bold text-slate-900 dark:text-white"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-0.5">شروط وأحكام العرض (تطبع بأسفل الورقة):</label>
                        <textarea 
                            wire:model="terms_conditions" 
                            rows="2" 
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl p-2 text-xs text-slate-700 dark:text-slate-300"
                        ></textarea>
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

                    @if(bccomp((string)$shipping_cost, '0.000', 3) > 0)
                    <div class="flex items-center justify-between text-xs text-amber-600 dark:text-amber-400 font-bold">
                        <span>مصاريف الشحن التقديرية:</span>
                        <span class="font-mono">+ {{ number_format($shipping_cost, 2) }} ج.م</span>
                    </div>
                    @endif

                    <!-- NET TOTAL HIGHLIGHT -->
                    <div class="p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-between">
                        <div>
                            <span class="text-xs text-slate-500 dark:text-slate-400 block font-bold">إجمالي عرض السعر:</span>
                            <span class="text-sm font-black text-slate-900 dark:text-white">
                                {{ $pricing_tier === 'wholesale' ? '⚡ بتسعير الجملة' : '🏷️ بتسعير القطاعي' }}
                            </span>
                        </div>
                        <span class="text-xl sm:text-2xl font-black font-mono text-amber-600 dark:text-amber-400">
                            {{ number_format($net_total, 2) }} <span class="text-xs font-normal">ج.م</span>
                        </span>
                    </div>
                </div>

                <!-- Action Buttons: Save, Print A4, PDF, WhatsApp -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2">
                    <button 
                        type="button" 
                        wire:click="saveQuotation('save')" 
                        wire:loading.attr="disabled"
                        class="h-12 rounded-2xl bg-slate-800 hover:bg-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 text-white text-xs font-bold shadow-md transition-all active:scale-95 cursor-pointer flex items-center justify-center gap-1.5"
                    >
                        <span>💾 حفظ العرض</span>
                    </button>

                    <button 
                        type="button" 
                        wire:click="saveQuotation('pdf')" 
                        wire:loading.attr="disabled"
                        class="h-12 rounded-2xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-black shadow-lg shadow-rose-600/30 transition-all active:scale-95 cursor-pointer flex items-center justify-center gap-1.5"
                    >
                        <span>📥 تحميل PDF</span>
                    </button>

                    <button 
                        type="button" 
                        wire:click="saveQuotation('print')" 
                        wire:loading.attr="disabled"
                        class="h-12 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-black shadow-lg shadow-indigo-600/30 transition-all active:scale-95 cursor-pointer flex items-center justify-center gap-1.5"
                    >
                        <span>🖨️ طباعة A4</span>
                    </button>

                    <button 
                        type="button" 
                        wire:click="saveQuotation('whatsapp')" 
                        wire:loading.attr="disabled"
                        class="h-12 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black shadow-lg shadow-emerald-600/30 transition-all active:scale-95 cursor-pointer flex items-center justify-center gap-1.5"
                    >
                        <span>📲 واتساب</span>
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
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">سيتم حفظ العميل وتحديده لعرض السعر فوراً</p>
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
                        placeholder="مثال: كافيه الصفا / مطعم السلطان..." 
                        class="w-full h-10 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 text-xs font-bold text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500"
                        autofocus
                    >
                    @error('newCustomerName')
                        <span class="text-[11px] text-rose-500 font-bold block mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">رقم الهاتف / واتساب:</label>
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
    <!-- 📱 Sticky Mobile Bottom Floating Bar (When browsing catalog) -->
    <div 
        x-show="mobileTab === 'catalog' && {{ count($items) }} > 0" 
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-full opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-full opacity-0"
        class="xl:hidden fixed bottom-3 left-3 right-3 z-40 p-3 bg-slate-900/95 dark:bg-slate-950/95 text-white backdrop-blur-md rounded-2xl border border-slate-700 shadow-2xl flex items-center justify-between gap-3"
        style="display: none;"
    >
        <div class="flex items-center gap-2.5">
            <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center text-lg font-bold shrink-0">
                📋
            </div>
            <div>
                <div class="text-[11px] text-slate-300 font-bold">
                    {{ count($items) }} أصناف بالعرض
                </div>
                <div class="text-base font-black font-mono text-emerald-400 leading-tight">
                    {{ number_format($net_total, 2) }} <span class="text-[10px] font-normal text-white">ج.م</span>
                </div>
            </div>
        </div>

        <button 
            type="button" 
            @click="mobileTab = 'cart'; window.scrollTo({top: 0, behavior: 'smooth'})"
            class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-500 hover:to-amber-400 text-white text-xs font-black shadow-lg shadow-amber-600/40 flex items-center gap-1.5 cursor-pointer active:scale-95 shrink-0"
        >
            <span>متابعة وحفظ العرض ←</span>
        </button>
    </div>

</div>
