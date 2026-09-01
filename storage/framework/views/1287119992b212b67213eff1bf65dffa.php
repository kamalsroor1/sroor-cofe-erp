<div class="space-y-4">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white dark:bg-slate-900 p-4 sm:p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white flex items-center gap-2 font-tajawal">
                <span>📥 فاتورة شراء بضاعة وتوريد للمخزن</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">إضافة وتوريد الأصناف والبضاعة بالميزان أو القطعة وتحديث متوسط التكلفة وحسابات الموردين</p>
        </div>
        
        <div class="flex items-center gap-2">
            <a href="<?php echo e(route('purchases.index')); ?>" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 text-xs font-bold transition-all">
                سجل المشتريات ←
            </a>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errorMessage): ?>
    <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300 text-xs font-bold flex items-center gap-2">
        <span>⚠️</span>
        <span><?php echo e($errorMessage); ?></span>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
    <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300 text-xs space-y-1">
        <div class="font-bold flex items-center gap-1.5">
            <span>⚠️</span>
            <span>يرجى تصحيح الأخطاء التالية لحفظ التوريد:</span>
        </div>
        <ul class="list-disc list-inside pr-4 space-y-0.5 font-medium">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $err): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <li><?php echo e($err); ?></li>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </ul>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <!-- Main 2-Column Touch Layout -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
        
        <!-- ========================================== -->
        <!-- 📦 Left Column: Touch Item Catalog (7 Cols)-->
        <!-- ========================================== -->
        <div class="xl:col-span-7 space-y-4">
            
            <!-- Quick Search Bar -->
            <div class="bg-white dark:bg-slate-900 p-4 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="relative">
                    <input 
                        type="text" 
                        wire:model.live.debounce.150ms="searchQuery" 
                        placeholder="🔍 ابحث بالاسم أو الباركود أو الكود لإضافة صنف للتوريد..." 
                        class="w-full h-12 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-2xl px-4 pl-10 text-sm font-bold text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    >
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($searchQuery): ?>
                    <button 
                        type="button" 
                        wire:click="$set('searchQuery', '')" 
                        class="absolute left-3 top-1/2 -translate-y-1/2 w-7 h-7 rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center text-xs font-bold hover:bg-slate-300 cursor-pointer"
                    >
                        ✕
                    </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <!-- Touch Product Cards Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $quickCatalog; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $prod): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-emerald-500/50 shadow-sm transition-all duration-200 flex flex-col justify-between overflow-hidden group">
                    <!-- Tap to Add 1 Unit Top Area -->
                    <button 
                        type="button"
                        wire:click="addItem(<?php echo e($prod->id); ?>, '1.000')" 
                        class="p-3.5 text-right w-full cursor-pointer transition-colors active:bg-emerald-500/10 flex-1 flex flex-col justify-between"
                        title="المس للإضافة (+1 <?php echo e($prod->unit); ?>)"
                    >
                        <div>
                            <h3 class="font-extrabold text-slate-900 dark:text-white text-xs sm:text-sm line-clamp-2 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
                                <?php echo e($prod->name); ?>

                            </h3>
                            <p class="text-[10px] text-slate-400 font-mono mt-0.5">كود: <?php echo e($prod->code); ?></p>
                        </div>

                        <div class="mt-3 flex items-center justify-between gap-2">
                            <span class="text-xs sm:text-sm font-black font-mono text-emerald-600 dark:text-emerald-400">
                                <?php echo e(number_format($prod->cost_price, 2)); ?> <span class="text-[10px] font-normal">ج.م/<?php echo e($prod->unit); ?></span>
                            </span>
                            <span class="text-[10px] px-2 py-0.5 rounded-lg font-mono font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                المتاح: <?php echo e(number_format($prod->current_stock, 1)); ?>

                            </span>
                        </div>
                    </button>

                    <!-- Bottom Quick Bulk Presets (For Bulk Coffee / Tea) -->
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($prod->unit === 'كجم'): ?>
                    <div class="p-2 bg-slate-50 dark:bg-slate-950 border-t border-slate-100 dark:border-slate-800 flex items-center gap-1">
                        <button 
                            type="button" 
                            wire:click="addItem(<?php echo e($prod->id); ?>, '10.000')" 
                            class="flex-1 h-9 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-emerald-600 hover:text-white text-[10px] font-bold text-slate-700 dark:text-slate-300 transition-all active:scale-95 cursor-pointer shadow-sm flex items-center justify-center font-mono"
                        >
                            +10k
                        </button>
                        <button 
                            type="button" 
                            wire:click="addItem(<?php echo e($prod->id); ?>, '25.000')" 
                            class="flex-1 h-9 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-emerald-600 hover:text-white text-[10px] font-bold text-slate-700 dark:text-slate-300 transition-all active:scale-95 cursor-pointer shadow-sm flex items-center justify-center font-mono"
                        >
                            +25k
                        </button>
                        <button 
                            type="button" 
                            wire:click="addItem(<?php echo e($prod->id); ?>, '50.000')" 
                            title="شيكارة 50 كجم"
                            class="flex-1 h-9 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 hover:bg-emerald-600 hover:text-white text-[10px] font-bold transition-all active:scale-95 cursor-pointer shadow-sm flex items-center justify-center"
                        >
                            +50k شكارة
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="p-2 bg-slate-50 dark:bg-slate-950 border-t border-slate-100 dark:border-slate-800">
                        <button 
                            type="button" 
                            wire:click="addItem(<?php echo e($prod->id); ?>, '1.000')" 
                            class="w-full h-8 rounded-xl bg-emerald-600/10 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-600 hover:text-white text-xs font-bold transition-colors cursor-pointer flex items-center justify-center gap-1"
                        >
                            <span>➕ إضافة وحدة</span>
                        </button>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <div class="col-span-full py-12 text-center bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 text-slate-400 text-sm">
                    لا توجد أصناف مطابقة للبحث
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 🛒 Right Column: Cart & Checkout (5 Cols)  -->
        <!-- ========================================== -->
        <div class="xl:col-span-5 space-y-4">
            
            <!-- Supplier & Store Card -->
            <div class="bg-white dark:bg-slate-900 p-4 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">📅 تاريخ الفاتورة:</label>
                        <?php if (isset($component)) { $__componentOriginal2686ed4927c64f67d2844e9b73af898c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2686ed4927c64f67d2844e9b73af898c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.datepicker','data' => ['wire:model' => 'purchase_date','class' => '!h-11','placeholder' => 'تاريخ الفاتورة']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('datepicker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model' => 'purchase_date','class' => '!h-11','placeholder' => 'تاريخ الفاتورة']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2686ed4927c64f67d2844e9b73af898c)): ?>
<?php $attributes = $__attributesOriginal2686ed4927c64f67d2844e9b73af898c; ?>
<?php unset($__attributesOriginal2686ed4927c64f67d2844e9b73af898c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2686ed4927c64f67d2844e9b73af898c)): ?>
<?php $component = $__componentOriginal2686ed4927c64f67d2844e9b73af898c; ?>
<?php unset($__componentOriginal2686ed4927c64f67d2844e9b73af898c); ?>
<?php endif; ?>
                    </div>

                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">🏢 المورد:</label>
                        <select wire:model.live="supplier_id" class="w-full h-11 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-2.5 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 [&>option]:bg-white [&>option]:text-slate-900 dark:[&>option]:bg-slate-900 dark:[&>option]:text-slate-100">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $suppliers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sup): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <option class="bg-white dark:bg-slate-900 text-slate-900 dark:text-white" value="<?php echo e($sup->id); ?>"><?php echo e($sup->name); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                    </div>

                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">🏬 المخزن المستلم:</label>
                        <select wire:model.live="store_id" class="w-full h-11 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-2.5 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 [&>option]:bg-white [&>option]:text-slate-900 dark:[&>option]:bg-slate-900 dark:[&>option]:text-slate-100">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $stores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $st): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <option class="bg-white dark:bg-slate-900 text-slate-900 dark:text-white" value="<?php echo e($st->id); ?>">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($st->type === 'wholesale_van'): ?> 🚚 <?php elseif($st->type === 'main_warehouse'): ?> 🏢 <?php else: ?> 🏬 <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php echo e($st->name); ?>

                            </option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Active Purchase Items List -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden flex flex-col">
                <div class="p-4 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <span class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>📥 بنود فاتورة الشراء</span>
                        <span class="px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 font-mono text-xs font-bold">
                            <?php echo e(count($items ?? [])); ?> بنود
                        </span>
                    </span>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($items) && count($items) > 0): ?>
                    <button wire:click="$set('items', [])" class="text-xs text-rose-500 hover:text-rose-600 font-bold cursor-pointer">
                        إفراغ السلة 🗑️
                    </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="max-h-[380px] overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800 p-2 space-y-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $landed = $this->landedCostPreview[$idx] ?? null;
                    ?>
                    <div class="p-3 bg-slate-50 dark:bg-slate-950/60 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <h4 class="font-extrabold text-slate-900 dark:text-white text-xs sm:text-sm">
                                    <?php echo e($line['name']); ?>

                                </h4>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-[10px] text-slate-400 font-mono">كود: <?php echo e($line['code']); ?></span>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($landed && bccomp($landed['unit_allocated'], '0.000', 3) > 0): ?>
                                        <span class="text-[10px] px-1.5 py-0.2 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 font-bold border border-amber-500/20">
                                            🚚 تكلفة بعد المصاريف: <?php echo e(number_format($landed['landed_cost'], 2)); ?> ج.م/<?php echo e($line['unit']); ?>

                                        </span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </div>
                            <button 
                                type="button" 
                                wire:click="removeItem(<?php echo e($idx); ?>)" 
                                class="w-8 h-8 rounded-xl bg-rose-500/10 hover:bg-rose-500 text-rose-600 hover:text-white flex items-center justify-center text-xs transition-colors cursor-pointer"
                                title="حذف البند"
                            >
                                ✕
                            </button>
                        </div>

                        <!-- Stepper & Price Inputs -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1 border-t border-slate-200/50 dark:border-slate-800/50">
                            <!-- Quantity Stepper Controls -->
                            <div class="flex items-center gap-1">
                                <button 
                                    type="button" 
                                    wire:click="decrementLineQty(<?php echo e($idx); ?>, '1.000')" 
                                    class="w-10 h-10 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-rose-500 hover:text-white text-slate-800 dark:text-slate-200 text-base font-black transition-all active:scale-90 flex items-center justify-center cursor-pointer"
                                >
                                    -
                                </button>
                                
                                <input 
                                    type="number" 
                                    step="0.001" 
                                    min="0.001" 
                                    wire:model.live.debounce.250ms="items.<?php echo e($idx); ?>.quantity" 
                                    class="w-full h-10 text-center bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl font-mono text-sm font-black text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                >

                                <button 
                                    type="button" 
                                    wire:click="incrementLineQty(<?php echo e($idx); ?>, '1.000')" 
                                    class="w-10 h-10 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-emerald-600 hover:text-white text-slate-800 dark:text-slate-200 text-base font-black transition-all active:scale-90 flex items-center justify-center cursor-pointer"
                                >
                                    +
                                </button>
                            </div>

                            <!-- Cost Price & Total Price -->
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1">
                                    <input 
                                        type="number" 
                                        step="0.01" 
                                        min="0" 
                                        wire:model.live.debounce.250ms="items.<?php echo e($idx); ?>.cost_price" 
                                        class="w-full h-10 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-2.5 text-xs font-mono font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                        placeholder="سعر التكلفة الأساسي"
                                    >
                                    <span class="absolute left-2 top-2.5 text-[10px] text-slate-400 font-bold">ج.م</span>
                                </div>

                                <div class="text-left shrink-0 font-mono text-xs font-black text-emerald-600 dark:text-emerald-400">
                                    <?php echo e(number_format($line['total_price'], 2)); ?> ج.م
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="py-12 text-center text-slate-400 text-xs">
                        لم يتم إضافة أي أصناف للتوريد بعد. المس الأصناف من القائمة يساراً 👈
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <!-- ========================================== -->
                <!-- 🚚 Multi-Expense Landed Costs Section     -->
                <!-- ========================================== -->
                <div class="p-4 bg-slate-50 dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-extrabold text-slate-900 dark:text-white">🚚 مصاريف ملحقة بالفاتورة (Landed Costs):</span>
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
                            wire:click="addExpenseRow('شحن ونقل', 'by_quantity', 'treasury_cash')" 
                            class="px-2.5 py-1 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-amber-600 hover:text-white text-[10px] font-bold text-slate-700 dark:text-slate-300 transition-colors cursor-pointer flex items-center gap-1"
                        >
                            <span>🚚 + شحن</span>
                        </button>
                        <button 
                            type="button" 
                            wire:click="addExpenseRow('عتالة وتنزيل', 'by_quantity', 'treasury_cash')" 
                            class="px-2.5 py-1 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-amber-600 hover:text-white text-[10px] font-bold text-slate-700 dark:text-slate-300 transition-colors cursor-pointer flex items-center gap-1"
                        >
                            <span>👷 + عتالة</span>
                        </button>
                        <button 
                            type="button" 
                            wire:click="addExpenseRow('كراتين وتغليف', 'by_value', 'supplier_account')" 
                            class="px-2.5 py-1 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-amber-600 hover:text-white text-[10px] font-bold text-slate-700 dark:text-slate-300 transition-colors cursor-pointer flex items-center gap-1"
                        >
                            <span>📦 + تغليف</span>
                        </button>
                        <button 
                            type="button" 
                            wire:click="addExpenseRow('جمارك / نولون', 'by_value', 'treasury_cash')" 
                            class="px-2.5 py-1 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-amber-600 hover:text-white text-[10px] font-bold text-slate-700 dark:text-slate-300 transition-colors cursor-pointer flex items-center gap-1"
                        >
                            <span>📑 + جمارك</span>
                        </button>
                        <button 
                            type="button" 
                            wire:click="addExpenseRow('مصروف إضافي', 'by_quantity', 'supplier_account')" 
                            class="px-2.5 py-1 rounded-xl bg-emerald-500/10 hover:bg-emerald-600 hover:text-white text-[10px] font-bold text-emerald-700 dark:text-emerald-400 border border-emerald-500/20 transition-colors cursor-pointer flex items-center gap-1"
                        >
                            <span>➕ بند مخصص</span>
                        </button>
                    </div>

                    <!-- Expenses List -->
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($additional_expenses) && count($additional_expenses) > 0): ?>
                    <div class="space-y-2 pt-1">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $additional_expenses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eIdx => $exp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="p-2.5 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
                            <div class="grid grid-cols-12 gap-2 items-center">
                                <div class="col-span-4">
                                    <input 
                                        type="text" 
                                        wire:model.live.debounce.250ms="additional_expenses.<?php echo e($eIdx); ?>.title" 
                                        placeholder="اسم المصروف" 
                                        class="w-full h-8 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg px-2 text-[11px] font-bold text-slate-800 dark:text-slate-200 focus:ring-1 focus:ring-amber-500"
                                    >
                                </div>
                                <div class="col-span-3 relative">
                                    <input 
                                        type="number" 
                                        step="0.01" 
                                        min="0" 
                                        wire:model.live.debounce.250ms="additional_expenses.<?php echo e($eIdx); ?>.amount" 
                                        placeholder="المبلغ" 
                                        class="w-full h-8 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg px-2 text-[11px] font-mono font-bold text-amber-600 dark:text-amber-400 focus:ring-1 focus:ring-amber-500"
                                    >
                                </div>
                                <div class="col-span-4">
                                    <select 
                                        wire:model.live="additional_expenses.<?php echo e($eIdx); ?>.allocation_method" 
                                        class="w-full h-8 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg px-1 text-[10px] font-bold text-slate-700 dark:text-slate-300"
                                    >
                                        <option value="by_quantity">حسب الوزن / الكمية</option>
                                        <option value="by_value">حسب قيمة الصنف</option>
                                        <option value="equal">بالتساوي على البنود</option>
                                    </select>
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
                                        <option value="supplier_account">مضاف لحساب المورد بالفاتورة (المورد دفعها وهنحاسبه)</option>
                                        <option value="treasury_cash">مدفوع كاش نقدًا من الخزينة (سند صرف)</option>
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

                <!-- Totals & Payment Section -->
                <div class="p-4 bg-slate-50 dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 space-y-3">
                    <div class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-400">
                        <span>المجموع الفرعي للتوريد:</span>
                        <span class="font-mono font-black text-slate-900 dark:text-white"><?php echo e(number_format($subtotal, 2)); ?> ج.م</span>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1">الخصم من المورد:</label>
                            <input 
                                type="number" 
                                step="0.01" 
                                min="0" 
                                wire:model.live.debounce.250ms="discount_amount" 
                                class="w-full h-10 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 text-xs font-mono font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            >
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1">المدفوع نقداً للمورد:</label>
                            <input 
                                type="number" 
                                step="0.01" 
                                min="0" 
                                wire:model.live.debounce.250ms="paid_amount" 
                                class="w-full h-10 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            >
                        </div>
                    </div>

                    <!-- Quick Paid Buttons -->
                    <div class="flex items-center gap-1.5">
                        <button 
                            type="button" 
                            wire:click="quickSetPaidExact" 
                            class="flex-1 py-1.5 rounded-lg bg-slate-200 dark:bg-slate-800 text-[10px] font-bold hover:bg-emerald-600 hover:text-white transition-colors cursor-pointer"
                        >
                            سداد الصافي كاملاً
                        </button>
                        <button 
                            type="button" 
                            wire:click="quickSetPaidAmount('0.000')" 
                            class="flex-1 py-1.5 rounded-lg bg-slate-200 dark:bg-slate-800 text-[10px] font-bold hover:bg-amber-600 hover:text-white transition-colors cursor-pointer"
                        >
                            آجل بالكامل (0)
                        </button>
                    </div>

                    <!-- Net & Remaining -->
                    <div class="pt-2 border-t border-slate-200 dark:border-slate-800 space-y-1.5">
                        <div class="flex items-center justify-between text-sm font-black text-slate-900 dark:text-white">
                            <span>الصافي المطلوب:</span>
                            <span class="text-xl font-mono text-emerald-600 dark:text-emerald-400">
                                <?php echo e(number_format($net_total, 2)); ?> <span class="text-xs font-normal">ج.م</span>
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-xs text-amber-700 dark:text-amber-400 font-bold">
                            <span>المتبقي للمورد (آجل):</span>
                            <span class="font-mono font-black"><?php echo e(number_format($remaining_amount, 2)); ?> ج.م</span>
                        </div>
                    </div>

                    <!-- Large Submit Touch Button -->
                    <button 
                        type="button" 
                        wire:click="savePurchase" 
                        wire:loading.attr="disabled"
                        class="w-full h-14 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-black shadow-xl shadow-emerald-600/30 transition-all active:scale-95 cursor-pointer flex items-center justify-center gap-2 mt-2"
                    >
                        <span wire:loading.remove wire:target="savePurchase">💾 حفظ وتوريد البضاعة للمخزن</span>
                        <span wire:loading wire:target="savePurchase">جاري التوريد... ⏳</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php /**PATH I:\projects\erp-2026\backend\resources\views/livewire/purchase-create.blade.php ENDPATH**/ ?>