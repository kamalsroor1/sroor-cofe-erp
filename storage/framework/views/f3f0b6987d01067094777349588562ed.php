<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900/60 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div>
            <h2 class="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>📑 سجل فواتير المبيعات الصادرة</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">متابعة الفواتير المعتمدة، حالات السداد والآجل، وإلغاء الفواتير وفق الأصول المحاسبية</p>
        </div>
        <a href="<?php echo e(route('invoices.create')); ?>" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2 transition-all cursor-pointer">
            <span>+ فاتورة بيع جديدة (F2)</span>
        </a>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
            <div class="flex flex-col sm:flex-row items-center gap-2 w-full lg:w-auto">
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="بحث برقم الفاتورة أو اسم العميل..." 
                    class="w-full sm:w-64 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-4 py-2 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-emerald-500"
                >
                <select 
                    wire:model.live="selectedStore" 
                    class="w-full sm:w-56 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500 font-bold [&>option]:bg-white [&>option]:text-slate-900 dark:[&>option]:bg-slate-900 dark:[&>option]:text-slate-100"
                >
                    <option class="bg-white dark:bg-slate-900 text-slate-900 dark:text-white" value="">🏢 كل الفروع ونقاط البيع</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $stores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $st): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <option class="bg-white dark:bg-slate-900 text-slate-900 dark:text-white" value="<?php echo e($st->id); ?>"><?php echo e($st->type === 'wholesale_van' ? '🚚 ' : ($st->is_main ? '🏢 ' : '🏬 ')); ?><?php echo e($st->name); ?> (<?php echo e($st->code ?: 'B'.$st->id); ?>)</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
            </div>

            <!-- Date Filter Inputs -->
            <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-950 p-1.5 rounded-xl border border-slate-300 dark:border-slate-700 text-xs">
                <div class="flex items-center gap-1">
                    <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 shrink-0">📅 من:</span>
                    <?php if (isset($component)) { $__componentOriginal2686ed4927c64f67d2844e9b73af898c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2686ed4927c64f67d2844e9b73af898c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.datepicker','data' => ['wire:model.live' => 'fromDate','class' => '!h-8 !w-32 !py-1 !px-2 !text-xs','placeholder' => 'من تاريخ']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('datepicker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model.live' => 'fromDate','class' => '!h-8 !w-32 !py-1 !px-2 !text-xs','placeholder' => 'من تاريخ']); ?>
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
                <div class="flex items-center gap-1">
                    <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 shrink-0">إلى:</span>
                    <?php if (isset($component)) { $__componentOriginal2686ed4927c64f67d2844e9b73af898c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2686ed4927c64f67d2844e9b73af898c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.datepicker','data' => ['wire:model.live' => 'toDate','class' => '!h-8 !w-32 !py-1 !px-2 !text-xs','placeholder' => 'إلى تاريخ']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('datepicker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model.live' => 'toDate','class' => '!h-8 !w-32 !py-1 !px-2 !text-xs','placeholder' => 'إلى تاريخ']); ?>
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
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-slate-500 dark:text-slate-400 text-[11px] hidden sm:inline">الحالة:</span>
                <button wire:click="$set('filterStatus', 'active')" class="px-2.5 py-1.5 rounded-lg font-bold border transition-colors cursor-pointer text-xs <?php echo e($filterStatus === 'active' ? 'bg-emerald-600 text-white border-emerald-500' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'); ?>">النشطة</button>
                <button wire:click="$set('filterStatus', 'trashed')" class="px-2.5 py-1.5 rounded-lg font-bold border transition-colors cursor-pointer text-xs flex items-center gap-1 <?php echo e($filterStatus === 'trashed' ? 'bg-rose-600 text-white border-rose-500' : 'border-slate-200 dark:border-slate-800 text-rose-600 dark:text-rose-400'); ?>">
                    <span>سلة المحذوفات</span>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($trashedCount > 0): ?>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] <?php echo e($filterStatus === 'trashed' ? 'bg-white text-rose-600' : 'bg-rose-500/20 text-rose-600'); ?> font-mono font-bold"><?php echo e($trashedCount); ?></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </button>
                <button wire:click="$set('filterStatus', 'all')" class="px-2.5 py-1.5 rounded-lg font-bold border transition-colors cursor-pointer text-xs <?php echo e($filterStatus === 'all' ? 'bg-slate-700 text-white border-slate-600' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'); ?>">الكل</button>
            </div>

            <!-- Payment Status & Method Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex flex-wrap items-center gap-1">
                    <span class="text-slate-500 dark:text-slate-400 text-[11px] hidden sm:inline">السداد:</span>
                    <button wire:click="$set('paymentStatus', 'all')" class="px-2 py-1 rounded-lg font-bold border transition-colors cursor-pointer text-xs <?php echo e($paymentStatus === 'all' ? 'bg-slate-200 dark:bg-slate-800 border-slate-300 dark:border-slate-600 text-slate-900 dark:text-white' : 'border-transparent text-slate-500 dark:text-slate-400'); ?>">الكل</button>
                    <button wire:click="$set('paymentStatus', 'unpaid')" class="px-2 py-1 rounded-lg font-bold border transition-colors cursor-pointer text-xs <?php echo e($paymentStatus === 'unpaid' ? 'bg-rose-500/20 border-rose-500/40 text-rose-700 dark:text-rose-400' : 'border-transparent text-slate-500 dark:text-slate-400'); ?>">آجل</button>
                    <button wire:click="$set('paymentStatus', 'partially_paid')" class="px-2 py-1 rounded-lg font-bold border transition-colors cursor-pointer text-xs <?php echo e($paymentStatus === 'partially_paid' ? 'bg-amber-500/20 border-amber-500/40 text-amber-700 dark:text-amber-400' : 'border-transparent text-slate-500 dark:text-slate-400'); ?>">جزئي</button>
                    <button wire:click="$set('paymentStatus', 'paid')" class="px-2 py-1 rounded-lg font-bold border transition-colors cursor-pointer text-xs <?php echo e($paymentStatus === 'paid' ? 'bg-emerald-500/20 border-emerald-500/40 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-slate-500 dark:text-slate-400'); ?>">مدفوع</button>
                </div>

                <!-- Payment Method Filter Buttons -->
                <div class="flex flex-wrap items-center gap-1 border-r border-slate-200 dark:border-slate-800 pr-2">
                    <span class="text-slate-500 dark:text-slate-400 text-[11px] hidden sm:inline">وسيلة الدفع:</span>
                    <button wire:click="$set('paymentMethod', 'all')" class="px-2 py-1 rounded-lg font-bold border transition-colors cursor-pointer text-xs <?php echo e($paymentMethod === 'all' ? 'bg-slate-200 dark:bg-slate-800 border-slate-300 dark:border-slate-600 text-slate-900 dark:text-white' : 'border-transparent text-slate-500 dark:text-slate-400'); ?>">الكل</button>
                    <button wire:click="$set('paymentMethod', 'cash')" class="px-2 py-1 rounded-lg font-bold border transition-colors cursor-pointer text-xs flex items-center gap-1 <?php echo e($paymentMethod === 'cash' ? 'bg-emerald-500/20 border-emerald-500/40 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-slate-500 dark:text-slate-400'); ?>">
                        <span>💵</span>
                        <span>كاش</span>
                    </button>
                    <button wire:click="$set('paymentMethod', 'instapay')" class="px-2 py-1 rounded-lg font-bold border transition-colors cursor-pointer text-xs flex items-center gap-1 <?php echo e($paymentMethod === 'instapay' ? 'bg-purple-500/20 border-purple-500/40 text-purple-700 dark:text-purple-400' : 'border-transparent text-slate-500 dark:text-slate-400'); ?>">
                        <span>⚡</span>
                        <span>إنستاباي</span>
                    </button>
                    <button wire:click="$set('paymentMethod', 'e_wallet')" class="px-2 py-1 rounded-lg font-bold border transition-colors cursor-pointer text-xs flex items-center gap-1 <?php echo e($paymentMethod === 'e_wallet' ? 'bg-rose-500/20 border-rose-500/40 text-rose-700 dark:text-rose-400' : 'border-transparent text-slate-500 dark:text-slate-400'); ?>">
                        <span>📲</span>
                        <span>محفظة</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Cards View (Visible on screens < 640px) -->
    <div class="sm:hidden space-y-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $inv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 space-y-3 shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800/80 pb-2.5">
                <div>
                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm"><?php echo e($inv->invoice_number); ?></span>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="text-[11px] text-slate-500 dark:text-slate-400"><?php echo e($inv->invoice_date->format('Y-m-d')); ?></span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($inv->store): ?>
                        <span class="text-slate-300 dark:text-slate-700">•</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                            <?php echo e($inv->store->type === 'wholesale_van' ? '🚚 ' : ($inv->store->is_main ? '🏢 ' : '🏬 ')); ?><?php echo e($inv->store->name); ?>

                        </span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
                <div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($inv->status === 'cancelled'): ?>
                        <span class="px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">ملغاة</span>
                    <?php elseif($inv->payment_status === 'paid'): ?>
                        <span class="px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">مدفوعة</span>
                    <?php elseif($inv->payment_status === 'partially_paid'): ?>
                        <span class="px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">مسدد جزئي</span>
                    <?php else: ?>
                        <span class="px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">آجل</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <div class="flex items-center justify-between text-xs">
                <div>
                    <span class="text-slate-500 dark:text-slate-400">العميل:</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200 mr-1"><?php echo e($inv->customer->name); ?></span>
                </div>
                <div class="font-mono font-bold text-slate-900 dark:text-white text-sm">
                    <?php echo e(number_format($inv->net_total, 2)); ?> ج.م
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 p-2 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800 text-[11px] font-mono">
                <div>
                    <span class="text-slate-500">المدفوع:</span>
                    <span class="text-emerald-600 dark:text-emerald-400 font-bold mr-1"><?php echo e(number_format($inv->paid_amount, 2)); ?></span>
                </div>
                <div class="text-left">
                    <span class="text-slate-500">المتبقي:</span>
                    <span class="font-bold <?php echo e(bccomp($inv->remaining_amount, '0.000', 3) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-400'); ?> mr-1"><?php echo e(number_format($inv->remaining_amount, 2)); ?></span>
                </div>
            </div>

            <div class="flex items-center gap-1.5 pt-1">
                <a href="<?php echo e(route('invoices.show', $inv->id)); ?>" class="flex-1 py-1.5 text-center rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold text-xs border border-slate-300 dark:border-slate-700">
                    عرض / طباعة
                </a>
                <a href="<?php echo e(route('invoices.print.thermal', $inv->id)); ?>" target="_blank" class="px-3 py-1.5 text-center rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-sm">
                    🖨️
                </a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($inv->status !== 'cancelled'): ?>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('invoices.edit')): ?>
                <a href="<?php echo e(route('invoices.edit', $inv->id)); ?>" class="px-3 py-1.5 text-center rounded-xl bg-amber-500/10 hover:bg-amber-500 text-amber-600 hover:text-slate-950 dark:text-amber-400 font-bold text-xs border border-amber-500/30">
                    ✏️
                </a>
                <?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('invoices.delete')): ?>
                <button
                    wire:click="deleteInvoice(<?php echo e($inv->id); ?>)"
                    wire:confirm="هل أنت متأكد من حذف الفاتورة رقم <?php echo e($inv->invoice_number); ?> نهائياً؟"
                    class="px-3 py-1.5 text-center rounded-xl bg-rose-500/10 hover:bg-rose-600 text-rose-600 hover:text-white dark:text-rose-400 font-bold text-xs border border-rose-500/30 cursor-pointer"
                >
                    🗑️
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <div class="p-8 text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl text-slate-400 text-xs">
            لا توجد فواتير مسجلة
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <!-- Desktop / Tablet Table View (Hidden on screens < 640px) -->
    <div class="hidden sm:block bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="p-3.5">رقم الفاتورة</th>
                        <th class="p-3.5">العميل</th>
                        <th class="p-3.5">الفرع / نقطة البيع</th>
                        <th class="p-3.5">التاريخ</th>
                        <th class="p-3.5">نوع الدفع</th>
                        <th class="p-3.5">الصافي المطلوب</th>
                        <th class="p-3.5">المدفوع</th>
                        <th class="p-3.5">المتبقي</th>
                        <th class="p-3.5">الحالة</th>
                        <th class="p-3.5 text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $inv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="p-3.5 font-mono font-bold text-emerald-600 dark:text-emerald-400"><?php echo e($inv->invoice_number); ?></td>
                        <td class="p-3.5 font-bold text-slate-800 dark:text-slate-100"><?php echo e($inv->customer->name); ?></td>
                        <td class="p-3.5">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($inv->store): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold <?php echo e($inv->store->type === 'wholesale_van' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20' : ($inv->store->is_main ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20' : 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20')); ?>">
                                    <span><?php echo e($inv->store->type === 'wholesale_van' ? '🚚' : ($inv->store->is_main ? '🏢' : '🏬')); ?></span>
                                    <span><?php echo e($inv->store->name); ?></span>
                                    <span class="text-[10px] opacity-75 font-mono">(<?php echo e($inv->store->code ?: 'B'.$inv->store->id); ?>)</span>
                                </span>
                            <?php else: ?>
                                <span class="text-slate-400 text-xs">—</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        <td class="p-3.5 font-mono text-slate-500 dark:text-slate-400"><?php echo e($inv->invoice_date->format('Y-m-d')); ?></td>
                        <td class="p-3.5 text-slate-700 dark:text-slate-300">
                            <div class="flex flex-col gap-1">
                                <span class="font-bold text-xs">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($inv->payment_type === 'cash'): ?> كاش فوري
                                    <?php elseif($inv->payment_type === 'credit'): ?> آجل (ذمم)
                                    <?php else: ?> دفع جزئي
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($inv->payment_type !== 'credit'): ?>
                                    <?php $pm = $inv->payment_method ?? 'cash'; ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 rounded-md w-fit border
                                        <?php echo e($pm === 'cash' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' : ($pm === 'instapay' ? 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20' : ($pm === 'e_wallet' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20' : ($pm === 'visa' ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20' : 'bg-slate-100 text-slate-600 border-slate-200')))); ?>">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pm === 'cash'): ?> <span>💵 كاش</span>
                                        <?php elseif($pm === 'instapay'): ?> <span>⚡ إنستاباي</span>
                                        <?php elseif($pm === 'e_wallet'): ?> <span>📲 محفظة</span>
                                        <?php elseif($pm === 'visa'): ?> <span>💳 فيزا</span>
                                        <?php elseif($pm === 'bank_transfer'): ?> <span>🏦 تحويل</span>
                                        <?php else: ?> <span><?php echo e($pm); ?></span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </td>
                        <td class="p-3.5 font-mono font-bold text-slate-900 dark:text-white"><?php echo e(number_format($inv->net_total, 2)); ?> ج.م</td>
                        <td class="p-3.5 font-mono text-emerald-600 dark:text-emerald-400"><?php echo e(number_format($inv->paid_amount, 2)); ?></td>
                        <td class="p-3.5 font-mono font-bold <?php echo e(bccomp($inv->remaining_amount, '0.000', 3) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-400'); ?>">
                            <?php echo e(number_format($inv->remaining_amount, 2)); ?>

                        </td>
                        <td class="p-3.5">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($inv->status === 'cancelled'): ?>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">ملغاة</span>
                            <?php elseif($inv->payment_status === 'paid'): ?>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">مدفوعة</span>
                            <?php elseif($inv->payment_status === 'partially_paid'): ?>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">مسدد جزئي</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">آجل</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        <td class="p-3.5 text-center flex items-center justify-center gap-1.5">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($inv->trashed()): ?>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('trash.access')): ?>
                                <button
                                    wire:click="restoreInvoice(<?php echo e($inv->id); ?>)"
                                    class="px-2.5 py-1 rounded-lg bg-emerald-500/10 hover:bg-emerald-600 hover:text-white text-emerald-700 dark:text-emerald-400 font-bold text-[11px] border border-emerald-500/30 transition-colors inline-flex items-center gap-1 cursor-pointer"
                                    title="استعادة الفاتورة"
                                >
                                    <span>♻️ استعادة</span>
                                </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="<?php echo e(route('invoices.show', $inv->id)); ?>" class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white font-bold text-[11px] transition-colors border border-slate-300 dark:border-slate-700">
                                    تفاصيل / طباعة
                                </a>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($inv->status !== 'cancelled'): ?>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('invoices.edit')): ?>
                                <a href="<?php echo e(route('invoices.edit', $inv->id)); ?>" class="px-2 py-1 rounded-lg bg-amber-500/10 hover:bg-amber-600 hover:text-slate-950 text-amber-600 dark:text-amber-400 text-[11px] font-bold border border-amber-500/30 transition-all flex items-center gap-1 cursor-pointer" title="تعديل الفاتورة">
                                    <span>✏️ تعديل</span>
                                </a>
                                <?php endif; ?>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('invoices.delete')): ?>
                                <button
                                    wire:click="deleteInvoice(<?php echo e($inv->id); ?>)"
                                    wire:confirm="هل أنت متأكد من أرشفة الفاتورة رقم <?php echo e($inv->invoice_number); ?> ونقلها لسلة المحذوفات؟"
                                    class="px-2 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-600 hover:text-white text-rose-600 dark:text-rose-400 text-[11px] font-bold border border-rose-500/30 transition-all flex items-center gap-1 cursor-pointer"
                                    title="نقل لسلة المحذوفات"
                                >
                                    <span>🗑️</span>
                                </button>
                                <?php endif; ?>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                    </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <tr>
                        <td colspan="9" class="p-12 text-center text-slate-400">لا توجد فواتير مسجلة</td>
                    </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            <?php echo e($invoices->links()); ?>

        </div>
    </div>

    <!-- Pagination for Mobile -->
    <div class="sm:hidden p-2">
        <?php echo e($invoices->links()); ?>

    </div>

    <!-- Cancel Invoice Modal -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showCancelModal): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-md p-6 space-y-4 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                <h3 class="font-bold text-rose-600 dark:text-rose-400 text-base">إلغاء فاتورة مبيعات معتمدة</h3>
                <button wire:click="$set('showCancelModal', false)" class="text-slate-400 hover:text-slate-700 dark:hover:text-white">✕</button>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errorMessage): ?>
            <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300 text-xs">
                <?php echo e($errorMessage); ?>

            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <p class="text-xs text-slate-600 dark:text-slate-300">
                تنبيه: إلغاء الفاتورة سيعيد بضاعة الفاتورة بالكامل للمخزن، ويخصم المبلغ من حساب العميل، ولن يتم حذف الفاتورة بل ستحتفظ بسجل الإلغاء.
            </p>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">سبب الإلغاء (إلزامي):</label>
                <textarea wire:model="cancelReason" rows="3" placeholder="اكتب سبب إلغاء الفاتورة..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl p-3 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-rose-500"></textarea>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['cancelReason'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 text-[10px]"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                <button type="button" wire:click="$set('showCancelModal', false)" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold cursor-pointer">إلغاء الأمر</button>
                <button type="button" wire:click="confirmCancel" class="px-5 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-rose-600/30 cursor-pointer">تأكيد الإلغاء وعكس المخزن</button>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH I:\projects\erp-2026\backend\resources\views/livewire/invoice-index.blade.php ENDPATH**/ ?>