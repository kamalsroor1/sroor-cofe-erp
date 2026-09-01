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

    <!-- Top Touch Command Bar -->
    <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Store & Cashier Info -->
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-2xl font-bold border border-amber-500/20 shrink-0 shadow-inner">
                🛒
            </div>
            <div>
                <h1 class="text-lg md:text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🛒 نقطة البيع والكاشير السريع (Touch POS)</span>
                </h1>
                <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">الفرع الحالي:</span>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20 text-xs font-bold font-mono">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentStore?->type === 'wholesale_van'): ?> 🚚 <?php echo e($currentStore->name); ?> (عربية)
                        <?php elseif($currentStore?->type === 'main_warehouse'): ?> 🏢 <?php echo e($currentStore->name); ?> (المخزن)
                        <?php else: ?> 🏬 <?php echo e($currentStore?->name ?? 'الفرع الرئيسي'); ?>

                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </span>
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
        </div>
    </div>

    <!-- Error Alerts -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errorMessage): ?>
    <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300 text-xs font-bold flex items-center gap-3 shadow-sm">
        <span class="text-xl">⚠️</span>
        <span><?php echo e($errorMessage); ?></span>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
    <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300 text-xs space-y-1.5 shadow-sm">
        <div class="font-black flex items-center gap-2 text-sm">
            <span>🚨</span>
            <span>يرجى مراجعة البيانات التالية:</span>
        </div>
        <ul class="list-disc list-inside pr-4 space-y-0.5 font-medium">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $err): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <li><?php echo e($err); ?></li>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </ul>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

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
                        placeholder="🔍 ابحث بالاسم أو الباركود أو كود الصنف..." 
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500"
                        autofocus
                    >
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($searchQuery): ?>
                    <button wire:click="$set('searchQuery', '')" class="absolute left-3 top-1/2 -translate-y-1/2 w-7 h-7 rounded-lg bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center text-xs font-bold hover:bg-slate-300 cursor-pointer">✕</button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <!-- Big Touch Category Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none pt-1">
                    <button 
                        type="button"
                        wire:click="$set('selectedCategory', 'all')" 
                        class="px-4 py-2.5 rounded-xl font-bold text-xs shrink-0 flex items-center justify-center gap-1.5 transition-all cursor-pointer shadow-sm active:scale-95 <?php echo e($selectedCategory === 'all' ? 'bg-amber-600 text-white shadow-amber-600/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'); ?>"
                    >
                        <span>📦</span>
                        <span>كل الأصناف</span>
                    </button>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <button 
                        type="button"
                        wire:click="$set('selectedCategory', '<?php echo e($cat); ?>')" 
                        class="px-4 py-2.5 rounded-xl font-bold text-xs shrink-0 flex items-center justify-center gap-1.5 transition-all cursor-pointer shadow-sm active:scale-95 <?php echo e($selectedCategory === $cat ? 'bg-amber-600 text-white shadow-amber-600/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'); ?>"
                    >
                        <span>🏷️</span>
                        <span><?php echo e($cat); ?></span>
                    </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>

            <!-- Touch Product Cards Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $quickCatalog; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $prod): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $storeStockQty = $prod->getStockInStore($store_id);
                    $storePrice = $prod->getEffectivePriceForStore($store_id);
                    $hasStock = bccomp($storeStockQty, '0.000', 3) > 0;
                ?>
                <div class="rounded-2xl bg-white dark:bg-slate-900 border <?php echo e($hasStock ? 'border-slate-200 dark:border-slate-800 hover:border-amber-500/50' : 'border-rose-200 dark:border-rose-900/50 opacity-75'); ?> shadow-sm transition-all duration-200 flex flex-col justify-between overflow-hidden group">
                    
                    <!-- Tap to Add 1 Unit Top Area -->
                    <button 
                        type="button"
                        wire:click="addItem(<?php echo e($prod->id); ?>, '1.000')" 
                        class="p-3.5 text-right w-full cursor-pointer transition-colors active:bg-amber-500/10 flex-1 flex flex-col justify-between"
                        title="المس للإضافة السريعة (+1 <?php echo e($prod->unit); ?>)"
                    >
                        <div>
                            <div class="flex items-start justify-between gap-1">
                                <h3 class="font-extrabold text-slate-900 dark:text-white text-xs sm:text-sm line-clamp-2 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors">
                                    <?php echo e($prod->name); ?>

                                </h3>
                            </div>
                            <p class="text-[10px] text-slate-400 font-mono mt-0.5">كود: <?php echo e($prod->code); ?></p>
                        </div>

                        <div class="mt-3 flex items-center justify-between gap-2">
                            <span class="text-xs sm:text-sm font-black font-mono text-emerald-600 dark:text-emerald-400">
                                <?php echo e(number_format($storePrice, 2)); ?> <span class="text-[10px] font-normal">ج.م</span>
                            </span>
                            
                            <span class="text-[10px] px-2 py-0.5 rounded-lg font-mono font-bold <?php echo e($hasStock ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-600 border border-rose-500/20'); ?>">
                                <?php echo e(number_format($storeStockQty, 1)); ?> <?php echo e($prod->unit); ?>

                            </span>
                        </div>
                    </button>

                    <!-- Bottom Quick Weight Steppers (For Kg products) -->
                    <div class="p-2 bg-slate-50/80 dark:bg-slate-950/60 border-t border-slate-100 dark:border-slate-800/80 flex items-center gap-1">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($prod->unit === 'كجم'): ?>
                            <button 
                                type="button" 
                                wire:click="addItem(<?php echo e($prod->id); ?>, '0.125')" 
                                class="flex-1 h-9 rounded-xl bg-slate-200/80 dark:bg-slate-800 hover:bg-amber-600 hover:text-white text-[11px] font-bold text-slate-700 dark:text-slate-200 transition-all active:scale-90 cursor-pointer shadow-sm flex items-center justify-center"
                                title="ثمن كيلو (125 جم)"
                            >
                                1/8
                            </button>
                            <button 
                                type="button" 
                                wire:click="addItem(<?php echo e($prod->id); ?>, '0.250')" 
                                class="flex-1 h-9 rounded-xl bg-slate-200/80 dark:bg-slate-800 hover:bg-amber-600 hover:text-white text-[11px] font-bold text-slate-700 dark:text-slate-200 transition-all active:scale-90 cursor-pointer shadow-sm flex items-center justify-center"
                                title="ربع كيلو (250 جم)"
                            >
                                1/4
                            </button>
                            <button 
                                type="button" 
                                wire:click="addItem(<?php echo e($prod->id); ?>, '0.500')" 
                                class="flex-1 h-9 rounded-xl bg-slate-200/80 dark:bg-slate-800 hover:bg-amber-600 hover:text-white text-[11px] font-bold text-slate-700 dark:text-slate-200 transition-all active:scale-90 cursor-pointer shadow-sm flex items-center justify-center"
                                title="نصف كيلو (500 جم)"
                            >
                                1/2
                            </button>
                            <button 
                                type="button" 
                                wire:click="addItem(<?php echo e($prod->id); ?>, '1.000')" 
                                class="flex-1 h-9 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-[11px] font-black text-white transition-all active:scale-90 cursor-pointer shadow-sm flex items-center justify-center"
                                title="كيلو كامل"
                            >
                                1ك
                            </button>
                        <?php else: ?>
                            <button 
                                type="button" 
                                wire:click="addItem(<?php echo e($prod->id); ?>, '1.000')" 
                                class="w-full h-9 rounded-xl bg-emerald-500/15 hover:bg-emerald-600 hover:text-white text-emerald-700 dark:text-emerald-300 text-xs font-bold transition-all active:scale-95 cursor-pointer shadow-sm flex items-center justify-center gap-1.5"
                            >
                                <span>➕ إضافة وحدة</span>
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <div class="col-span-full py-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 text-slate-400 text-sm">
                    لا توجد أصناف مطابقة لبيانات البحث
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $stores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $st): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <option value="<?php echo e($st->id); ?>">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($st->type === 'wholesale_van'): ?> 🚚 <?php elseif($st->type === 'main_warehouse'): ?> 🏢 <?php else: ?> 🏬 <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php echo e($st->name); ?>

                            </option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                    </div>

                    <!-- Customer Selector & Quick Search -->
                    <div class="relative" x-data="{ open: false }">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">👤 العميل والحساب:</label>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()?->can('customers.manage') || auth()->user()?->can('pos.access') || auth()->user()?->can('invoices.create')): ?>
                            <button 
                                type="button" 
                                wire:click="openNewCustomerModal" 
                                class="text-[11px] font-black text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300 flex items-center gap-1 cursor-pointer transition-colors"
                            >
                                <span>➕ عميل جديد</span>
                            </button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($customerSearch): ?>
                            <button 
                                type="button" 
                                @click="open = true"
                                wire:click="$set('customerSearch', '')" 
                                class="absolute left-2.5 top-1/2 -translate-y-1/2 w-6 h-6 rounded-md bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center text-xs font-bold hover:bg-slate-300 cursor-pointer"
                            >
                                ✕
                            </button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <!-- Dropdown List of Filtered Customers -->
                        <div 
                            x-show="open" 
                            @click.away="open = false" 
                            x-transition.duration.150ms 
                            class="absolute z-50 right-0 left-0 mt-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-2xl overflow-hidden max-h-60 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800"
                            style="display: none;"
                        >
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $filteredCustomers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <button 
                                type="button" 
                                wire:click="selectCustomer(<?php echo e($fc->id); ?>)" 
                                @click="open = false"
                                class="w-full p-2.5 text-right hover:bg-amber-500/10 transition-colors flex items-center justify-between gap-2 cursor-pointer <?php echo e($customer_id == $fc->id ? 'bg-amber-500/15' : ''); ?>"
                            >
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                        <span>👤 <?php echo e($fc->name); ?></span>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($customer_id == $fc->id): ?>
                                            <span class="text-[10px] bg-emerald-500/20 text-emerald-600 px-1.5 py-0.5 rounded font-black">✓ محدد</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fc->phone): ?>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5" dir="ltr">
                                        📱 <?php echo e($fc->phone); ?>

                                    </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <div class="text-left shrink-0">
                                    <span class="text-[10px] px-2 py-0.5 rounded-md font-mono font-bold <?php echo e(bccomp($fc->current_balance, '0.000', 3) > 0 ? 'bg-rose-500/10 text-rose-600' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'); ?>">
                                        <?php echo e(number_format($fc->current_balance, 2)); ?> ج.م
                                    </span>
                                </div>
                            </button>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <div class="p-3 text-center text-xs text-slate-400">
                                لا يوجد عميل مطابق للبحث
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()?->can('customers.manage') || auth()->user()?->can('pos.access') || auth()->user()?->can('invoices.create')): ?>
                                <button type="button" wire:click="openNewCustomerModal" @click="open = false" class="block mx-auto mt-1 text-amber-600 font-bold hover:underline cursor-pointer">
                                    ➕ تسجيل عميل جديد الآن
                                </button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <!-- Selected Customer Info Pill -->
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedCustomer): ?>
                        <div class="mt-1.5 flex items-center justify-between text-[11px] px-2.5 py-1 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300">
                            <span class="font-bold truncate">العميل الحالي: <b class="text-amber-600 dark:text-amber-400"><?php echo e($selectedCustomer->name); ?></b></span>
                            <span class="font-mono font-bold <?php echo e(bccomp($selectedCustomer->current_balance, '0.000', 3) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500'); ?>">
                                الرصيد: <?php echo e(number_format($selectedCustomer->current_balance, 2)); ?> ج.م
                            </span>
                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Active Cart Items -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden flex flex-col">
                <div class="p-3.5 bg-slate-50/80 dark:bg-slate-950/60 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <span class="text-xs sm:text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🛒 سلة الفاتورة</span>
                        <span class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-700 dark:text-amber-300 font-mono text-xs font-bold">
                            <?php echo e(count($items)); ?> بنود
                        </span>
                    </span>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($items) > 0): ?>
                    <button wire:click="$set('items', [])" class="text-xs text-rose-500 hover:text-rose-600 font-bold cursor-pointer transition-colors">
                        إفراغ السلة 🗑️
                    </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <!-- Items List (Touch Friendly Cards) -->
                <div class="divide-y divide-slate-100 dark:divide-slate-800/80 max-h-[380px] overflow-y-auto p-2 space-y-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="p-3 rounded-xl bg-slate-50/60 dark:bg-slate-950/40 border border-slate-200/80 dark:border-slate-800/80 space-y-2.5">
                        
                        <!-- Line Header: Name + Price + Delete -->
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h4 class="font-black text-slate-900 dark:text-white text-xs sm:text-sm"><?php echo e($line['name']); ?></h4>
                                <div class="text-[10px] text-slate-400 font-mono flex items-center gap-2 mt-0.5">
                                    <span>سعر الوحدة: <b class="text-slate-700 dark:text-slate-300"><?php echo e(number_format($line['unit_price'], 2)); ?> ج.م</b></span>
                                    <span>• المتاح: <?php echo e(number_format($line['current_stock'], 2)); ?> <?php echo e($line['unit']); ?></span>
                                </div>
                            </div>
                            
                            <button 
                                type="button" 
                                wire:click="removeItem(<?php echo e($idx); ?>)" 
                                class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-600 hover:text-white flex items-center justify-center transition-all cursor-pointer active:scale-90 shrink-0"
                                title="حذف الصنف من السلة"
                            >
                                ✕
                            </button>
                        </div>

                        <!-- 💡 Last Customer Price Pill -->
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($line['last_customer_price'])): ?>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-500/10 border border-amber-500/30 text-[10px] font-bold text-amber-700 dark:text-amber-300">
                                <span>💡 آخر سعر:</span>
                                <span class="font-mono font-black"><?php echo e(number_format($line['last_customer_price']['unit_price'], 2)); ?> ج.م</span>
                            </span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(bccomp((string)$line['unit_price'], (string)$line['last_customer_price']['unit_price'], 2) !== 0): ?>
                            <button 
                                type="button" 
                                wire:click="applyCustomerLastPrice(<?php echo e($idx); ?>)" 
                                class="px-2 py-0.5 rounded-md bg-amber-500 hover:bg-amber-600 text-white text-[10px] font-black cursor-pointer shadow-sm active:scale-95"
                            >
                                ⚡ تطبيق السعر
                            </button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <!-- Stepper & Line Total -->
                        <div class="flex items-center justify-between gap-3 pt-1">
                            <!-- Large Touch Stepper -->
                            <div class="flex items-center gap-1">
                                <button 
                                    type="button" 
                                    wire:click="decrementLineQty(<?php echo e($idx); ?>, '<?php echo e($line['unit'] === 'كجم' ? '0.125' : '1.000'); ?>')" 
                                    class="w-10 h-10 rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:bg-rose-500 hover:text-white text-base font-black flex items-center justify-center active:scale-90 transition-all cursor-pointer shadow-sm"
                                >
                                    -
                                </button>
                                
                                <div class="px-3 h-10 min-w-[70px] bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl flex items-center justify-center font-mono font-black text-sm text-emerald-600 dark:text-emerald-400">
                                    <?php echo e($line['quantity']); ?> <span class="text-[10px] text-slate-400 mr-1"><?php echo e($line['unit']); ?></span>
                                </div>

                                <button 
                                    type="button" 
                                    wire:click="incrementLineQty(<?php echo e($idx); ?>, '<?php echo e($line['unit'] === 'كجم' ? '0.125' : '1.000'); ?>')" 
                                    class="w-10 h-10 rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:bg-emerald-600 hover:text-white text-base font-black flex items-center justify-center active:scale-90 transition-all cursor-pointer shadow-sm"
                                >
                                    +
                                </button>
                            </div>

                            <!-- Line Total Price -->
                            <div class="text-left">
                                <span class="text-xs text-slate-400 block">الإجمالي</span>
                                <span class="text-base font-black font-mono text-slate-900 dark:text-white">
                                    <?php echo e(number_format($line['total_price'], 2)); ?> <span class="text-[10px] font-normal">ج.م</span>
                                </span>
                            </div>
                        </div>

                        <!-- Micro Weight Buttons for Kg items in cart -->
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($line['unit'] === 'كجم'): ?>
                        <div class="flex items-center gap-1 pt-1">
                            <button type="button" wire:click="setLineWeightPreset(<?php echo e($idx); ?>, '0.125')" class="flex-1 py-1 bg-slate-200/70 dark:bg-slate-800 hover:bg-amber-600 hover:text-white rounded-lg text-[10px] font-mono text-slate-700 dark:text-slate-300 font-bold transition-colors cursor-pointer">125g</button>
                            <button type="button" wire:click="setLineWeightPreset(<?php echo e($idx); ?>, '0.250')" class="flex-1 py-1 bg-slate-200/70 dark:bg-slate-800 hover:bg-amber-600 hover:text-white rounded-lg text-[10px] font-mono text-slate-700 dark:text-slate-300 font-bold transition-colors cursor-pointer">250g</button>
                            <button type="button" wire:click="setLineWeightPreset(<?php echo e($idx); ?>, '0.500')" class="flex-1 py-1 bg-slate-200/70 dark:bg-slate-800 hover:bg-amber-600 hover:text-white rounded-lg text-[10px] font-mono text-slate-700 dark:text-slate-300 font-bold transition-colors cursor-pointer">500g</button>
                            <button type="button" wire:click="setLineWeightPreset(<?php echo e($idx); ?>, '1.000')" class="flex-1 py-1 bg-emerald-100 dark:bg-emerald-950 border border-emerald-300 dark:border-emerald-800 hover:bg-emerald-600 hover:text-white rounded-lg text-[10px] font-mono text-emerald-800 dark:text-emerald-300 font-black transition-colors cursor-pointer">1kg</button>
                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="py-12 text-center text-slate-400 text-xs">
                        🛒 السلة فارغة. المس الأصناف على اليمين لإضافتها فوراً.
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
                            class="h-12 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 border transition-all cursor-pointer active:scale-95 <?php echo e($payment_type === 'cash' ? 'bg-emerald-600 border-emerald-600 text-white shadow-lg shadow-emerald-600/30' : 'bg-slate-50 dark:bg-slate-950 border-slate-300 dark:border-slate-800 text-slate-700 dark:text-slate-300'); ?>"
                        >
                            <span>💵</span>
                            <span>كاش فوري</span>
                        </button>

                        <button 
                            type="button" 
                            wire:click="quickSetPaymentType('credit')" 
                            class="h-12 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 border transition-all cursor-pointer active:scale-95 <?php echo e($payment_type === 'credit' ? 'bg-amber-600 border-amber-600 text-white shadow-lg shadow-amber-600/30' : 'bg-slate-50 dark:bg-slate-950 border-slate-300 dark:border-slate-800 text-slate-700 dark:text-slate-300'); ?>"
                        >
                            <span>💳</span>
                            <span>آجل (ذمم)</span>
                        </button>

                        <button 
                            type="button" 
                            wire:click="quickSetPaymentType('partial')" 
                            class="h-12 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 border transition-all cursor-pointer active:scale-95 <?php echo e($payment_type === 'partial' ? 'bg-indigo-600 border-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'bg-slate-50 dark:bg-slate-950 border-slate-300 dark:border-slate-800 text-slate-700 dark:text-slate-300'); ?>"
                        >
                            <span>⏳</span>
                            <span>دفع جزئي</span>
                        </button>
                    </div>
                </div>

                <!-- Payment Method Quick Selection (Visible when paying cash or partial) -->
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment_type !== 'credit'): ?>
                <div class="p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800/80 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-[11px] font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1">
                            <span>💳</span>
                            <span>وسيلة التحصيل والدفع:</span>
                        </label>
                        <span class="text-[10px] text-slate-400">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment_method === 'cash'): ?>
                            (يدخل درج الكاشير)
                            <?php else: ?>
                            (تحصيل إلكتروني/بنكي)
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </span>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <!-- كاش -->
                        <button 
                            type="button" 
                            wire:click="quickSetPaymentMethod('cash')" 
                            class="py-2.5 px-2 rounded-xl text-xs font-bold flex flex-col items-center justify-center gap-1 border transition-all cursor-pointer active:scale-95 <?php echo e($payment_method === 'cash' ? 'bg-emerald-600 border-emerald-600 text-white shadow-md shadow-emerald-600/30' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500'); ?>"
                        >
                            <span class="text-base">💵</span>
                            <span>كاش نقدي</span>
                        </button>

                        <!-- إنستاباي -->
                        <button 
                            type="button" 
                            wire:click="quickSetPaymentMethod('instapay')" 
                            class="py-2.5 px-2 rounded-xl text-xs font-bold flex flex-col items-center justify-center gap-1 border transition-all cursor-pointer active:scale-95 <?php echo e($payment_method === 'instapay' ? 'bg-purple-600 border-purple-600 text-white shadow-md shadow-purple-600/30' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:border-purple-500'); ?>"
                        >
                            <span class="text-base">⚡</span>
                            <span>إنستاباي</span>
                        </button>

                        <!-- محفظة -->
                        <button 
                            type="button" 
                            wire:click="quickSetPaymentMethod('e_wallet')" 
                            class="py-2.5 px-2 rounded-xl text-xs font-bold flex flex-col items-center justify-center gap-1 border transition-all cursor-pointer active:scale-95 <?php echo e($payment_method === 'e_wallet' ? 'bg-rose-600 border-rose-600 text-white shadow-md shadow-rose-600/30' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:border-rose-500'); ?>"
                        >
                            <span class="text-base">📲</span>
                            <span>محفظة ذكية</span>
                        </button>
                    </div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

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
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('invoices.discount')): ?>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">🎁 خصم سريع:</label>
                    <div class="grid grid-cols-4 gap-1.5">
                        <button type="button" wire:click="quickSetDiscountPercent('0.000')" class="h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-xs font-bold hover:bg-slate-200 cursor-pointer">بدون خصم</button>
                        <button type="button" wire:click="quickSetDiscountPercent('5.000')" class="h-8 rounded-lg bg-amber-500/10 hover:bg-amber-500 text-amber-700 dark:text-amber-300 hover:text-white text-xs font-bold cursor-pointer">5%</button>
                        <button type="button" wire:click="quickSetDiscountPercent('10.000')" class="h-8 rounded-lg bg-amber-500/10 hover:bg-amber-500 text-amber-700 dark:text-amber-300 hover:text-white text-xs font-bold cursor-pointer">10%</button>
                        <button type="button" wire:click="quickSetDiscountPercent('15.000')" class="h-8 rounded-lg bg-amber-500/10 hover:bg-amber-500 text-amber-700 dark:text-amber-300 hover:text-white text-xs font-bold cursor-pointer">15%</button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ========================================== -->
                <!-- 🚚 Multi-Expenses / Shipping Dynamic Box   -->
                <!-- ========================================== -->
                <div class="p-3 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800/80 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">🚚 مصاريف الشحن والخدمات الإضافية:</span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(bccomp($additional_expenses_total, '0.000', 3) > 0): ?>
                                <span class="text-[11px] font-mono font-black text-amber-600 dark:text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded-lg border border-amber-500/20">
                                    +<?php echo e(number_format($additional_expenses_total, 2)); ?> ج.م
                                </span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($additional_expenses) && count($additional_expenses) > 0): ?>
                    <div class="space-y-2 pt-1">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $additional_expenses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eIdx => $exp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="p-2.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 space-y-1.5 text-xs">
                            <div class="grid grid-cols-12 gap-2 items-center">
                                <div class="col-span-6">
                                    <input 
                                        type="text" 
                                        wire:model.live.debounce.250ms="additional_expenses.<?php echo e($eIdx); ?>.title" 
                                        placeholder="اسم المصروف / الخدمة" 
                                        class="w-full h-8 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg px-2 text-[11px] font-bold text-slate-800 dark:text-slate-200 focus:ring-1 focus:ring-amber-500"
                                    >
                                </div>
                                <div class="col-span-5 relative">
                                    <input 
                                        type="number" 
                                        step="0.01" 
                                        min="0" 
                                        wire:model.live.debounce.250ms="additional_expenses.<?php echo e($eIdx); ?>.amount" 
                                        placeholder="المبلغ ج.م" 
                                        class="w-full h-8 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg px-2 text-[11px] font-mono font-bold text-amber-600 dark:text-amber-400 focus:ring-1 focus:ring-amber-500"
                                    >
                                </div>
                                <div class="col-span-1 text-center">
                                    <button 
                                        type="button" 
                                        wire:click="removeExpenseRow(<?php echo e($eIdx); ?>)" 
                                        class="w-6 h-6 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-600 hover:text-white flex items-center justify-center text-[10px] transition-colors cursor-pointer"
                                        title="حذف المصروف"
                                    >
                                        ✕
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-12 gap-2 items-center pt-1 border-t border-slate-100 dark:border-slate-800 text-[10px]">
                                <div class="col-span-5 text-slate-500 font-bold">مين اللي هيتحمل / هيدفع؟</div>
                                <div class="col-span-7">
                                    <select 
                                        wire:model.live="additional_expenses.<?php echo e($eIdx); ?>.paid_by" 
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
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
                        <span class="font-mono font-bold"><?php echo e(number_format($subtotal, 2)); ?> ج.م</span>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(bccomp($discount_amount, '0.000', 3) > 0): ?>
                    <div class="flex items-center justify-between text-xs text-rose-600 dark:text-rose-400 font-bold">
                        <span>الخصم الممنوح:</span>
                        <span class="font-mono">- <?php echo e(number_format($discount_amount, 2)); ?> ج.م</span>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(bccomp($shipping_cost, '0.000', 3) > 0): ?>
                    <div class="flex items-center justify-between text-xs text-amber-600 dark:text-amber-400 font-bold">
                        <span>مصاريف الشحن / التوصيل:</span>
                        <span class="font-mono">+ <?php echo e(number_format($shipping_cost, 2)); ?> ج.م</span>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <!-- NET TOTAL HIGHLIGHT -->
                    <div class="p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-between">
                        <span class="text-sm font-black text-slate-900 dark:text-white">الصافي المطلوب:</span>
                        <span class="text-xl sm:text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400">
                            <?php echo e(number_format($net_total, 2)); ?> <span class="text-xs font-normal">ج.م</span>
                        </span>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment_type === 'partial'): ?>
                    <div class="flex items-center justify-between text-xs text-amber-700 dark:text-amber-400 font-bold">
                        <span>المتبقي ذمم:</span>
                        <span class="font-mono font-black text-sm"><?php echo e(number_format($remaining_amount, 2)); ?> ج.م</span>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <!-- Large Checkout Touch Buttons -->
                <div class="grid grid-cols-2 gap-3 pt-2">
                    <button 
                        type="button" 
                        wire:click="saveInvoice" 
                        wire:loading.attr="disabled"
                        class="h-14 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-black shadow-xl shadow-emerald-600/30 transition-all active:scale-95 cursor-pointer flex items-center justify-center gap-2"
                    >
                        <span wire:loading.remove wire:target="saveInvoice">💾 حفظ واعتماد (Enter)</span>
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

    <!-- ➕ Quick Add Customer Modal -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showNewCustomerModal): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" x-data>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl w-full max-w-md p-6 space-y-4 shadow-2xl relative">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>👤 تسجيل عميل جديد فوراً (POS)</span>
                </h3>
                <button type="button" wire:click="closeNewCustomerModal" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-xl font-bold p-1 cursor-pointer">✕</button>
            </div>

            <form wire:submit="quickCreateCustomer" class="space-y-3.5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">اسم العميل: <span class="text-rose-500">*</span></label>
                    <input 
                        type="text" 
                        wire:model="newCustomerName" 
                        placeholder="مثال: سوبر ماركت الأمانة / أحمد محمد" 
                        class="w-full h-11 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500"
                        autofocus
                        required
                    >
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newCustomerName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 text-[11px] font-bold"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">رقم الهاتف:</label>
                    <input 
                        type="tel" 
                        wire:model="newCustomerPhone" 
                        placeholder="مثال: 01012345678" 
                        class="w-full h-11 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 text-sm font-mono text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500"
                        dir="ltr"
                    >
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newCustomerPhone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 text-[11px] font-bold"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">العنوان أو المنطقة (اختياري):</label>
                    <input 
                        type="text" 
                        wire:model="newCustomerAddress" 
                        placeholder="مثال: شارع التحرير - الدقي" 
                        class="w-full h-11 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500"
                    >
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">ملاحظات (اختياري):</label>
                    <input 
                        type="text" 
                        wire:model="newCustomerNotes" 
                        placeholder="أي ملاحظات حول العميل" 
                        class="w-full h-11 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500"
                    >
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button 
                        type="button" 
                        wire:click="closeNewCustomerModal" 
                        class="px-4 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold rounded-xl text-xs hover:bg-slate-200 cursor-pointer"
                    >
                        إلغاء
                    </button>
                    <button 
                        type="submit" 
                        wire:loading.attr="disabled"
                        class="px-5 py-2.5 bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-500 hover:to-amber-400 text-white font-bold rounded-xl text-xs shadow-lg shadow-amber-500/20 cursor-pointer flex items-center gap-1.5"
                    >
                        <span wire:loading.remove wire:target="quickCreateCustomer">💾 حفظ وتحديد العميل للفاتورة</span>
                        <span wire:loading wire:target="quickCreateCustomer">جاري الحفظ...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH I:\projects\erp-2026\backend\resources\views/livewire/invoice-create.blade.php ENDPATH**/ ?>