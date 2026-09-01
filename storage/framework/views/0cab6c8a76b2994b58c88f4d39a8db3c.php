<div class="space-y-4">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white dark:bg-slate-900/60 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div>
            <h2 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>✏️ تعديل فاتورة مبيعات: <?php echo e($invoice_number); ?></span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">تعديل الأصناف، الأوزان، الأسعار، أو طريقة السداد مع إعادة موازنة المخزون وحساب العميل تلقائياً</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?php echo e(route('invoices.show', $invoice_id)); ?>" class="px-3 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition-all border border-slate-300 dark:border-slate-700">
                ← إلغاء ورجوع للفاتورة
            </a>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errorMessage): ?>
    <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300 text-xs flex items-center gap-2">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span><?php echo e($errorMessage); ?></span>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
    <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300 text-xs space-y-1">
        <div class="font-bold flex items-center gap-1.5">
            <span>⚠️</span>
            <span>يرجى تصحيح الأخطاء التالية:</span>
        </div>
        <ul class="list-disc list-inside pr-4 space-y-0.5 font-medium">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $err): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <li><?php echo e($err); ?></li>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </ul>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left 2 Cols: Quick Product Catalog & Active Items Table -->
        <div class="lg:col-span-2 space-y-4">
            
            <!-- Category Pills & Live Search -->
            <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3 shadow-sm">
                <!-- Search Bar -->
                <div class="relative">
                    <input 
                        type="text" 
                        wire:model.live.debounce.150ms="searchQuery" 
                        placeholder="🔍 ابحث باسم الصنف أو الباركود أو الكود لإضافته للفاتورة..." 
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-amber-500"
                    >
                    <div class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</div>
                </div>

                <!-- Category Filters -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none pt-1 text-[11px]">
                    <button wire:click="$set('selectedCategory', 'all')" class="px-3 py-1.5 rounded-xl font-bold transition-colors shrink-0 cursor-pointer <?php echo e($selectedCategory === 'all' ? 'bg-amber-600 text-white shadow' : 'bg-slate-100 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-800'); ?>">
                        📦 كل الأصناف
                    </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <button wire:click="$set('selectedCategory', '<?php echo e($cat); ?>')" class="px-3 py-1.5 rounded-xl font-bold transition-colors shrink-0 cursor-pointer <?php echo e($selectedCategory === $cat ? 'bg-amber-600 text-white shadow' : 'bg-slate-100 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-800'); ?>">
                        🏷️ <?php echo e($cat); ?>

                    </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>

                <!-- Quick Product Cards Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 pt-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $quickCatalog; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $prod): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800/80 hover:border-amber-500/50 transition-all flex flex-col justify-between group">
                        <div>
                            <div class="font-bold text-slate-800 dark:text-slate-200 text-xs line-clamp-1 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors"><?php echo e($prod->name); ?></div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono mt-0.5">
                                <?php echo e(number_format($prod->selling_price, 2)); ?> ج.م / <?php echo e($prod->unit); ?>

                            </div>
                        </div>

                        <!-- Quick Weight Select Buttons -->
                        <div class="mt-2.5 pt-2 border-t border-slate-200 dark:border-slate-900 flex items-center gap-1">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($prod->unit === 'كجم'): ?>
                                <button type="button" wire:click="addItem(<?php echo e($prod->id); ?>, '0.125')" title="ثمن كيلو (125 جم)" class="flex-1 py-1 rounded bg-slate-200 dark:bg-slate-800 hover:bg-amber-600 text-[9px] font-bold text-slate-700 dark:text-slate-300 hover:text-white transition-colors cursor-pointer">
                                    ثمن (125g)
                                </button>
                                <button type="button" wire:click="addItem(<?php echo e($prod->id); ?>, '0.250')" title="ربع كيلو (250 جم)" class="flex-1 py-1 rounded bg-slate-200 dark:bg-slate-800 hover:bg-amber-600 text-[9px] font-bold text-slate-700 dark:text-slate-300 hover:text-white transition-colors cursor-pointer">
                                    ربع (250g)
                                </button>
                                <button type="button" wire:click="addItem(<?php echo e($prod->id); ?>, '0.500')" title="نصف كيلو (500 جم)" class="flex-1 py-1 rounded bg-slate-200 dark:bg-slate-800 hover:bg-amber-600 text-[9px] font-bold text-slate-700 dark:text-slate-300 hover:text-white transition-colors cursor-pointer">
                                    نصف (500g)
                                </button>
                                <button type="button" wire:click="addItem(<?php echo e($prod->id); ?>, '1.000')" title="كيلو كامل" class="flex-1 py-1 rounded bg-amber-600 hover:bg-amber-500 text-[9px] font-black text-white transition-colors cursor-pointer">
                                    1كجم
                                </button>
                            <?php else: ?>
                                <button type="button" wire:click="addItem(<?php echo e($prod->id); ?>, '1.000')" class="w-full py-1 rounded bg-amber-500/10 hover:bg-amber-500 text-amber-700 dark:text-amber-300 hover:text-white text-[10px] font-bold transition-colors cursor-pointer">
                                    + إضافة 1 <?php echo e($prod->unit); ?>

                                </button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="col-span-3 p-4 text-center text-slate-400 text-xs">
                        لا توجد أصناف مطابقة للبحث
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <!-- Current Invoice Items Table -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="p-3 bg-slate-50 dark:bg-slate-950/60 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-xs font-bold text-slate-700 dark:text-slate-300">
                    <span>محتويات الفاتورة الحالية (<?php echo e(count($items)); ?> بند)</span>
                    <span class="text-[11px] text-amber-600 dark:text-amber-400 font-normal">تعديل مباشر على الأوزان والكميات والأسعار</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="p-3">#</th>
                                <th class="p-3">الصنف</th>
                                <th class="p-3 w-52">الوزن / الكمية بالميزان</th>
                                <th class="p-3 w-28">سعر الوحدة</th>
                                <th class="p-3 w-24">الخصم</th>
                                <th class="p-3 w-28">الإجمالي</th>
                                <th class="p-3 text-center w-10">حذف</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/20">
                                <td class="p-3 text-slate-400 font-mono"><?php echo e($idx + 1); ?></td>
                                <td class="p-3">
                                    <div class="font-bold text-slate-800 dark:text-slate-200"><?php echo e($line['name']); ?></div>
                                    <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">
                                        الوحدة: <span class="text-amber-600 dark:text-amber-400 font-bold"><?php echo e($line['unit']); ?></span> | الرصيد: <?php echo e(number_format($line['current_stock'], 3)); ?>

                                    </div>
                                </td>
                                <td class="p-3">
                                    <div class="space-y-1.5">
                                        <div class="relative flex items-center">
                                            <input 
                                                type="number" 
                                                step="0.001" 
                                                min="0.001" 
                                                wire:model.live.debounce.250ms="items.<?php echo e($idx); ?>.quantity" 
                                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg px-2 py-1 text-center text-xs font-mono font-bold text-amber-600 dark:text-amber-400 focus:outline-none focus:border-amber-500"
                                            >
                                            <span class="absolute left-2 text-[10px] text-slate-400 font-bold"><?php echo e($line['unit']); ?></span>
                                        </div>

                                        <!-- Micro weight adjusters if Kg -->
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($line['unit'] === 'كجم'): ?>
                                        <div class="flex items-center gap-1 justify-center">
                                            <button type="button" wire:click="setLineWeightPreset(<?php echo e($idx); ?>, '0.125')" title="ثمن كيلو (125 جم)" class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-800 hover:bg-amber-600 text-[9px] rounded font-mono text-slate-700 dark:text-slate-300 hover:text-white cursor-pointer">ثمن 125g</button>
                                            <button type="button" wire:click="setLineWeightPreset(<?php echo e($idx); ?>, '0.250')" title="ربع كيلو (250 جم)" class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-800 hover:bg-amber-600 text-[9px] rounded font-mono text-slate-700 dark:text-slate-300 hover:text-white cursor-pointer">ربع 250g</button>
                                            <button type="button" wire:click="setLineWeightPreset(<?php echo e($idx); ?>, '0.500')" title="نصف كيلو (500 جم)" class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-800 hover:bg-amber-600 text-[9px] rounded font-mono text-slate-700 dark:text-slate-300 hover:text-white cursor-pointer">نصف 500g</button>
                                            <button type="button" wire:click="setLineWeightPreset(<?php echo e($idx); ?>, '1.000')" title="كيلو كامل" class="px-1.5 py-0.5 bg-amber-100 dark:bg-amber-950 border border-amber-300 dark:border-amber-800 hover:bg-amber-600 text-[9px] rounded font-mono text-amber-800 dark:text-amber-300 hover:text-white font-bold cursor-pointer">1kg</button>
                                        </div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <input 
                                        type="number" 
                                        step="0.01" 
                                        min="0" 
                                        wire:model.live.debounce.250ms="items.<?php echo e($idx); ?>.unit_price" 
                                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg px-2 py-1 text-center text-xs font-mono text-slate-900 dark:text-white focus:outline-none focus:border-amber-500"
                                    >
                                </td>
                                <td class="p-3">
                                    <input 
                                        type="number" 
                                        step="0.01" 
                                        min="0" 
                                        wire:model.live.debounce.250ms="items.<?php echo e($idx); ?>.discount_amount" 
                                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg px-2 py-1 text-center text-xs font-mono text-rose-600 dark:text-rose-400 focus:outline-none focus:border-rose-500"
                                    >
                                </td>
                                <td class="p-3 font-mono font-bold text-slate-900 dark:text-white">
                                    <?php echo e(number_format($line['total_price'], 2)); ?>

                                </td>
                                <td class="p-3 text-center">
                                    <button wire:click="removeItem(<?php echo e($idx); ?>)" class="p-1 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 transition-colors cursor-pointer">🗑️</button>
                                </td>
                            </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400">
                                    لم يتم إضافة أصناف بعد. اختر من القائمة بالأعلى أو بالبحث السريع.
                                </td>
                            </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Col: Customer & Instant Financial Checkout -->
        <div class="space-y-4">
            <!-- Customer Selection -->
            <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3 shadow-sm">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">العميل:</label>
                <select wire:model.live="customer_id" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-amber-500">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <option value="<?php echo e($c->id); ?>">
                        <?php echo e($c->name); ?> (رصيد: <?php echo e(number_format($c->current_balance, 2)); ?> ج.م)
                    </option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>

                <!-- Payment Type -->
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 pt-2">طريقة الدفع:</label>
                <div class="grid grid-cols-3 gap-2">
                    <button 
                        type="button" 
                        wire:click="$set('payment_type', 'cash')" 
                        class="py-2 text-xs font-bold rounded-xl border transition-all cursor-pointer <?php echo e($payment_type === 'cash' ? 'bg-emerald-500/20 border-emerald-500 text-emerald-700 dark:text-emerald-400' : 'bg-slate-50 dark:bg-slate-950 border-slate-300 dark:border-slate-800 text-slate-600 dark:text-slate-400'); ?>"
                    >
                        كاش (نقدي)
                    </button>
                    <button 
                        type="button" 
                        wire:click="$set('payment_type', 'credit')" 
                        class="py-2 text-xs font-bold rounded-xl border transition-all cursor-pointer <?php echo e($payment_type === 'credit' ? 'bg-amber-500/20 border-amber-500 text-amber-700 dark:text-amber-400' : 'bg-slate-50 dark:bg-slate-950 border-slate-300 dark:border-slate-800 text-slate-600 dark:text-slate-400'); ?>"
                    >
                        آجل (شكك)
                    </button>
                    <button 
                        type="button" 
                        wire:click="$set('payment_type', 'partial')" 
                        class="py-2 text-xs font-bold rounded-xl border transition-all cursor-pointer <?php echo e($payment_type === 'partial' ? 'bg-indigo-500/20 border-indigo-500 text-indigo-700 dark:text-indigo-400' : 'bg-slate-50 dark:bg-slate-950 border-slate-300 dark:border-slate-800 text-slate-600 dark:text-slate-400'); ?>"
                    >
                        دفع جزئي
                    </button>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment_type === 'partial'): ?>
                <div class="pt-2">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">المبلغ المدفوع مقدماً:</label>
                    <input 
                        type="number" 
                        step="0.01" 
                        wire:model.live.debounce.250ms="paid_amount" 
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-mono font-bold text-slate-900 dark:text-white focus:outline-none focus:border-amber-500"
                    >
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <!-- Financial Checkout Card -->
            <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2">الحساب المالي والخصومات</h3>

                <!-- Invoice-level Discount -->
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">نوع خصم الفاتورة:</label>
                        <select wire:model.live="discount_type" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-2 py-1.5 text-xs text-slate-900 dark:text-white">
                            <option value="fixed">مبلغ ثابت (ج.م)</option>
                            <option value="percentage">نسبة مئوية (%)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">قيمة الخصم:</label>
                        <input 
                            type="number" 
                            step="0.01" 
                            min="0" 
                            wire:model.live.debounce.250ms="discount_value" 
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-2 py-1.5 text-xs font-mono text-rose-600 dark:text-rose-400 font-bold text-center"
                        >
                    </div>
                </div>

                <!-- Live Financial Breakdown -->
                <div class="space-y-2 pt-2 border-t border-slate-200 dark:border-slate-800 text-xs">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                        <span>المجموع الفرعي:</span>
                        <span class="font-mono text-slate-900 dark:text-white font-bold"><?php echo e(number_format($subtotal, 2)); ?> ج.م</span>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(bccomp($discount_amount, '0.000', 3) > 0): ?>
                    <div class="flex items-center justify-between text-rose-600 dark:text-rose-400">
                        <span>إجمالي الخصم:</span>
                        <span class="font-mono font-bold">-<?php echo e(number_format($discount_amount, 2)); ?> ج.م</span>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <!-- ========================================== -->
                    <!-- 🚚 Dynamic Additional Expenses Block       -->
                    <!-- ========================================== -->
                    <div class="p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-slate-700 dark:text-slate-300">🚚 مصاريف الشحن والخدمات:</span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(bccomp($additional_expenses_total, '0.000', 3) > 0): ?>
                                <span class="text-[10px] font-mono font-black text-amber-600 dark:text-amber-400 bg-amber-500/10 px-1.5 py-0.5 rounded border border-amber-500/20">
                                    +<?php echo e(number_format($additional_expenses_total, 2)); ?> ج.م
                                </span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <!-- Quick Add Buttons -->
                        <div class="flex flex-wrap gap-1">
                            <button type="button" wire:click="addExpenseRow('شحن وتوصيل', 'customer_account')" class="px-2 py-0.5 rounded bg-slate-200 dark:bg-slate-800 hover:bg-amber-600 hover:text-white text-[10px] font-bold text-slate-700 dark:text-slate-300 transition-colors cursor-pointer">🚚 + شحن</button>
                            <button type="button" wire:click="addExpenseRow('تغليف وكراتين', 'customer_account')" class="px-2 py-0.5 rounded bg-slate-200 dark:bg-slate-800 hover:bg-amber-600 hover:text-white text-[10px] font-bold text-slate-700 dark:text-slate-300 transition-colors cursor-pointer">📦 + تغليف</button>
                            <button type="button" wire:click="addExpenseRow('إكرامية طيار الدليفري', 'treasury_cash')" class="px-2 py-0.5 rounded bg-slate-200 dark:bg-slate-800 hover:bg-amber-600 hover:text-white text-[10px] font-bold text-slate-700 dark:text-slate-300 transition-colors cursor-pointer">🎁 + إكرامية</button>
                            <button type="button" wire:click="addExpenseRow('مصروف إضافي', 'customer_account')" class="px-2 py-0.5 rounded bg-amber-500/10 hover:bg-amber-600 hover:text-white text-[10px] font-bold text-amber-700 dark:text-amber-400 border border-amber-500/20 transition-colors cursor-pointer">➕ مخصص</button>
                        </div>

                        <!-- Expense Rows -->
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($additional_expenses) && count($additional_expenses) > 0): ?>
                        <div class="space-y-1.5 pt-1">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $additional_expenses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eIdx => $exp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <div class="p-2 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 space-y-1 text-[11px]">
                                <div class="grid grid-cols-12 gap-1.5 items-center">
                                    <div class="col-span-6">
                                        <input type="text" wire:model.live.debounce.250ms="additional_expenses.<?php echo e($eIdx); ?>.title" placeholder="اسم المصروف" class="w-full h-7 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded px-1.5 text-[10px] font-bold text-slate-800 dark:text-slate-200">
                                    </div>
                                    <div class="col-span-5">
                                        <input type="number" step="0.01" min="0" wire:model.live.debounce.250ms="additional_expenses.<?php echo e($eIdx); ?>.amount" placeholder="المبلغ" class="w-full h-7 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded px-1.5 text-[10px] font-mono font-bold text-amber-600 dark:text-amber-400">
                                    </div>
                                    <div class="col-span-1 text-center">
                                        <button type="button" wire:click="removeExpenseRow(<?php echo e($eIdx); ?>)" class="w-5 h-5 rounded bg-rose-500/10 hover:bg-rose-500 text-rose-600 hover:text-white flex items-center justify-center text-[10px] cursor-pointer">✕</button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-12 gap-1 items-center pt-1 border-t border-slate-100 dark:border-slate-800 text-[10px]">
                                    <div class="col-span-4 text-slate-400">التحميل:</div>
                                    <div class="col-span-8">
                                        <select wire:model.live="additional_expenses.<?php echo e($eIdx); ?>.paid_by" class="w-full h-6 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded text-[9px] font-bold">
                                            <option value="customer_account">حساب العميل بالفاتورة</option>
                                            <option value="treasury_cash">كاش من الخزينة</option>
                                            <option value="treasury_instapay">إنستاباي خزينة</option>
                                            <option value="treasury_e_wallet">محفظة إلكترونية</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(bccomp($shipping_cost, '0.000', 3) > 0): ?>
                    <div class="flex items-center justify-between text-amber-600 dark:text-amber-400 font-bold">
                        <span>إجمالي مصاريف الشحن والخدمات:</span>
                        <span class="font-mono">+<?php echo e(number_format($shipping_cost, 2)); ?> ج.م</span>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <div class="flex items-center justify-between text-sm font-black text-amber-600 dark:text-amber-400 pt-2 border-t border-slate-200 dark:border-slate-800">
                        <span>الصافي المطلوب:</span>
                        <span class="font-mono"><?php echo e(number_format($net_total, 2)); ?> ج.م</span>
                    </div>

                    <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                        <span>المدفوع:</span>
                        <span class="font-mono font-bold"><?php echo e(number_format($paid_amount, 2)); ?> ج.م</span>
                    </div>

                    <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                        <span>المتبقي في الحساب:</span>
                        <span class="font-mono font-bold <?php echo e(bccomp($remaining_amount, '0.000', 3) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'); ?>">
                            <?php echo e(number_format($remaining_amount, 2)); ?> ج.م
                        </span>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">ملاحظات الفاتورة:</label>
                    <textarea wire:model="notes" rows="2" placeholder="ملاحظات العميل أو تفاصيل الطحن والتسليم..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl p-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-amber-500"></textarea>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-2 pt-2">
                    <button 
                        type="button" 
                        wire:click="updateInvoice" 
                        wire:loading.attr="disabled"
                        class="w-full py-3 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-black rounded-xl text-sm shadow-lg shadow-amber-500/20 flex items-center justify-center gap-2 transition-all transform active:scale-95 cursor-pointer"
                    >
                        <span wire:loading.remove>💾 حفظ تعديلات الفاتورة</span>
                        <span wire:loading>جاري التحديث وإعادة ضبط المخزون...</span>
                    </button>

                    <button 
                        type="button" 
                        wire:click="updateInvoice(null, 'print')" 
                        wire:loading.attr="disabled"
                        class="w-full py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold rounded-xl text-xs flex items-center justify-center gap-2 transition-colors cursor-pointer border border-slate-300 dark:border-slate-700"
                    >
                        <span>🖨️ حفظ وطباعة الفاتورة (F9 أو Ctrl+Enter)</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Specific Keydown Shortcuts -->
    <script>
        document.addEventListener('keydown', function(e) {
            // F9 or Ctrl+Enter: Save and Print
            if (e.key === 'F9' || (e.ctrlKey && e.key === 'Enter')) {
                e.preventDefault();
                window.Livewire.find('<?php echo e($_instance->getId()); ?>').call('updateInvoice', null, 'print');
            }
            // F8: Save only
            if (e.key === 'F8') {
                e.preventDefault();
                window.Livewire.find('<?php echo e($_instance->getId()); ?>').call('updateInvoice');
            }
        });
    </script>
</div>
<?php /**PATH I:\projects\erp-2026\backend\resources\views/livewire/invoice-edit.blade.php ENDPATH**/ ?>