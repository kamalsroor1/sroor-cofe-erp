<div class="space-y-6">
    <!-- Header & Date Navigation -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-slate-900/60 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div>
            <h2 class="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>📅 يومية المبيعات وحركة الدرج (يوم بيوم)</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">متابعة دقيقة لمبيعات اليوم، النقدية المقبوضة، المصروفات، وحساب صافي الدرج وتقفيل الوردية</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <!-- Store Selector -->
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (\Illuminate\Support\Facades\Blade::check('hasrole', 'admin')): ?>
            <div class="flex items-center gap-1 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-2.5 py-1 text-xs">
                <span class="text-slate-500 dark:text-slate-400 font-bold">الفرع:</span>
                <select wire:model.live="selectedStoreId" class="bg-transparent text-slate-900 dark:text-white font-bold focus:outline-none cursor-pointer [&>option]:bg-white [&>option]:text-slate-900 dark:[&>option]:bg-slate-900 dark:[&>option]:text-slate-100">
                    <option class="bg-white dark:bg-slate-900 text-slate-900 dark:text-white" value="all">كل الفروع والعربيات</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $stores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $st): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <option class="bg-white dark:bg-slate-900 text-slate-900 dark:text-white" value="<?php echo e($st->id); ?>">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($st->type === 'wholesale_van'): ?> 🚚 <?php elseif($st->type === 'main_warehouse'): ?> 🏢 <?php else: ?> 🏬 <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php echo e($st->name); ?>

                    </option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
            </div>
            <?php else: ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentStore): ?>
                <div class="px-3 py-1 bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-400 font-bold text-xs rounded-xl">
                    <span><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentStore->type === 'wholesale_van'): ?> 🚚 <?php elseif($currentStore->type === 'main_warehouse'): ?> 🏢 <?php else: ?> 🏬 <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php echo e($currentStore->name); ?></span>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <!-- Date Filter Presets -->
            <button wire:click="setDate('today')" class="px-3 py-1.5 rounded-xl font-bold text-xs border transition-colors cursor-pointer <?php echo e($selectedDate === now()->toDateString() ? 'bg-amber-600 text-white border-amber-500' : 'bg-slate-100 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-800'); ?>">
                اليوم
            </button>
            <button wire:click="setDate('yesterday')" class="px-3 py-1.5 rounded-xl font-bold text-xs border transition-colors cursor-pointer <?php echo e($selectedDate === now()->subDay()->toDateString() ? 'bg-amber-600 text-white border-amber-500' : 'bg-slate-100 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-800'); ?>">
                أمس
            </button>

            <!-- Modern Date Picker -->
            <div class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-2 py-0.5 text-xs">
                <span class="text-slate-500 dark:text-slate-400 font-bold">📅</span>
                <?php if (isset($component)) { $__componentOriginal2686ed4927c64f67d2844e9b73af898c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2686ed4927c64f67d2844e9b73af898c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.datepicker','data' => ['wire:model.live' => 'selectedDate','class' => 'w-32 !py-1 !px-2 !text-xs !bg-transparent !border-none focus:!ring-0','placeholder' => 'اختر التاريخ']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('datepicker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model.live' => 'selectedDate','class' => 'w-32 !py-1 !px-2 !text-xs !bg-transparent !border-none focus:!ring-0','placeholder' => 'اختر التاريخ']); ?>
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

            <!-- View Full Treasury Report Button -->
            <a href="<?php echo e(route('reports.index')); ?>?tab=treasury" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-sm flex items-center gap-1.5 transition-colors cursor-pointer font-tajawal">
                <span>💰 تقرير الخزائن والسيولة</span>
            </a>

            <!-- Print Daily Summary Button -->
            <a href="<?php echo e(route('daily.journal.print', ['date' => $selectedDate, 'store_id' => $selectedStoreId, 'autoprint' => 1])); ?>" target="_blank" class="px-3.5 py-1.5 bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs rounded-xl shadow-sm flex items-center gap-1.5 transition-colors cursor-pointer font-tajawal">
                <span>🖨️ طباعة تقرير A4 رسمي</span>
            </a>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($successMessage): ?>
    <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-xs font-bold flex items-center gap-2">
        <span>✓</span> <?php echo e($successMessage); ?>

    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errorMessage): ?>
    <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300 text-xs font-bold flex items-center gap-2">
        <span>✕</span> <?php echo e($errorMessage); ?>

    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <!-- Shift / Drawer Action Bar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl flex flex-col lg:flex-row lg:items-center justify-between gap-4 shadow-sm">
        <div class="flex items-start sm:items-center gap-3">
            <div class="w-3.5 h-3.5 rounded-full mt-1 sm:mt-0 shrink-0 <?php echo e($activeShift ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400 dark:bg-slate-600'); ?>"></div>
            <div>
                <div class="text-xs font-bold text-slate-900 dark:text-white flex flex-wrap items-center gap-2">
                    <span>حالة الوردية / اليومية:</span>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeShift): ?>
                        <?php
                            $isOverdue = $activeShift->opened_at && (now()->diffInHours($activeShift->opened_at) >= 24 || $activeShift->opened_at->diffInDays(now()) >= 1);
                        ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isOverdue): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] bg-rose-500/20 text-rose-700 dark:text-rose-300 border border-rose-500/40 font-bold animate-pulse">
                            🚨 مفتوحة ومتأخرة (+24h) (#<?php echo e($activeShift->shift_number); ?>)
                        </span>
                        <?php else: ?>
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30 font-bold">
                            🟢 مفتوحة وشغالة (#<?php echo e($activeShift->shift_number); ?>)
                        </span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <span class="text-slate-500 dark:text-slate-400 font-normal">| الكاشير: <strong class="text-slate-900 dark:text-white"><?php echo e($activeShift->user->name ?? 'الكاشير'); ?></strong></span>
                        <span class="text-slate-500 dark:text-slate-400 font-normal">| تم الفتح: <strong class="text-slate-900 dark:text-white font-mono"><?php echo e($activeShift->opened_at ? $activeShift->opened_at->translatedFormat('d F - h:i A') : '—'); ?></strong></span>
                    <?php else: ?>
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-700">
                            مغلقة / غير مفتوحة
                        </span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeShift): ?>
                <div class="text-xs text-slate-600 dark:text-slate-300 mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 font-mono">
                    <span>الافتتاحي: <strong class="text-slate-900 dark:text-white"><?php echo e(number_format($activeShift->opening_cash_balance, 2)); ?></strong> ج.م</span>
                    <span>+ المقبوض: <strong class="text-emerald-600 dark:text-emerald-400"><?php echo e(number_format($totalCashCollected, 2)); ?></strong> ج.م</span>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(bccomp($totalExpenses, '0.000', 3) > 0): ?>
                    <span>- المصروفات: <strong class="text-rose-600 dark:text-rose-400"><?php echo e(number_format($totalExpenses, 2)); ?></strong> ج.م</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <span class="bg-amber-500/20 text-amber-800 dark:text-amber-300 px-2 py-0.5 rounded-lg border border-amber-500/30 font-bold">
                        💰 المفروض بالدرج الآن: <?php echo e(number_format($expectedCashInDrawer, 2)); ?> ج.م
                    </span>
                </div>
                <?php else: ?>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                    يمكنك فتح اليومية وبدء تسجيل الرصيد الافتتاحي (العهدة/الفكة) للدرج.
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeShift): ?>
                <button wire:click="openCloseModal" class="px-4 py-2.5 bg-gradient-to-r from-rose-600 to-rose-700 hover:from-rose-500 hover:to-rose-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-rose-600/30 flex items-center gap-1.5 transition-all cursor-pointer">
                    <span>🔴 تقفيل اليومية (Z-Report)</span>
                </button>
            <?php else: ?>
                <button wire:click="openShiftModal" class="px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-500 hover:to-emerald-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-600/30 flex items-center gap-1.5 transition-all cursor-pointer">
                    <span>🟢 فتح يومية جديدة</span>
                </button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 🏛️ Live Treasury & Multi-Account Balances -->
    <!-- ========================================== -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl shadow-sm space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🏛️ أرصدة الخزن وحسابات الدفع الفعلية الحالية</span>
                </h3>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">متابعة دقيقة لرصيد الكاش، إنستاباي، والمحافظ مع إمكانية التحويل المباشر بين الحسابات</p>
            </div>
            <button 
                type="button" 
                wire:click="openTransferModal" 
                class="px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white text-xs font-bold rounded-xl shadow-md shadow-purple-600/20 flex items-center gap-1.5 transition-all cursor-pointer self-start sm:self-auto"
            >
                <span>🔄 تحويل رصيد بين الخزن</span>
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- 1. Cash Drawer -->
            <?php $cashBal = $treasuryBalances['cash'] ?? null; ?>
            <div class="p-3.5 rounded-xl border border-emerald-500/20 bg-emerald-500/5 space-y-1">
                <div class="flex items-center justify-between text-xs font-bold text-emerald-700 dark:text-emerald-400">
                    <span class="flex items-center gap-1">💵 درج النقدية (الكاش)</span>
                </div>
                <div class="text-xl font-black text-emerald-700 dark:text-emerald-300 font-mono">
                    <?php echo e(number_format($cashBal['balance'] ?? 0, 2)); ?> <span class="text-xs font-normal">ج.م</span>
                </div>
                <div class="text-[10px] text-slate-500 dark:text-slate-400 pt-1 border-t border-emerald-500/10 flex justify-between">
                    <span>وارد: +<?php echo e(number_format($cashBal['inflows'] ?? 0, 1)); ?></span>
                    <span>صادر: -<?php echo e(number_format($cashBal['outflows'] ?? 0, 1)); ?></span>
                </div>
            </div>

            <!-- 2. InstaPay -->
            <?php $instaBal = $treasuryBalances['instapay'] ?? null; ?>
            <div class="p-3.5 rounded-xl border border-purple-500/20 bg-purple-500/5 space-y-1">
                <div class="flex items-center justify-between text-xs font-bold text-purple-700 dark:text-purple-400">
                    <span class="flex items-center gap-1">⚡ حساب إنستاباي (InstaPay)</span>
                </div>
                <div class="text-xl font-black text-purple-700 dark:text-purple-300 font-mono">
                    <?php echo e(number_format($instaBal['balance'] ?? 0, 2)); ?> <span class="text-xs font-normal">ج.م</span>
                </div>
                <div class="text-[10px] text-slate-500 dark:text-slate-400 pt-1 border-t border-purple-500/10 flex justify-between">
                    <span>وارد: +<?php echo e(number_format($instaBal['inflows'] ?? 0, 1)); ?></span>
                    <span>صادر: -<?php echo e(number_format($instaBal['outflows'] ?? 0, 1)); ?></span>
                </div>
            </div>

            <!-- 3. E-Wallet -->
            <?php $walletBal = $treasuryBalances['e_wallet'] ?? null; ?>
            <div class="p-3.5 rounded-xl border border-rose-500/20 bg-rose-500/5 space-y-1">
                <div class="flex items-center justify-between text-xs font-bold text-rose-700 dark:text-rose-400">
                    <span class="flex items-center gap-1">📲 المحفظة الذكية (كاش)</span>
                </div>
                <div class="text-xl font-black text-rose-700 dark:text-rose-300 font-mono">
                    <?php echo e(number_format($walletBal['balance'] ?? 0, 2)); ?> <span class="text-xs font-normal">ج.م</span>
                </div>
                <div class="text-[10px] text-slate-500 dark:text-slate-400 pt-1 border-t border-rose-500/10 flex justify-between">
                    <span>وارد: +<?php echo e(number_format($walletBal['inflows'] ?? 0, 1)); ?></span>
                    <span>صادر: -<?php echo e(number_format($walletBal['outflows'] ?? 0, 1)); ?></span>
                </div>
            </div>

            <!-- 4. Total Liquidity -->
            <div class="p-3.5 rounded-xl border-2 border-indigo-500/40 bg-indigo-500/5 space-y-1">
                <div class="flex items-center justify-between text-xs font-black text-indigo-700 dark:text-indigo-400">
                    <span class="flex items-center gap-1">💰 إجمالي السيولة النقدية</span>
                </div>
                <div class="text-xl font-black text-indigo-700 dark:text-indigo-300 font-mono">
                    <?php echo e(number_format($treasuryBalances['total_liquidity'] ?? 0, 2)); ?> <span class="text-xs font-normal">ج.م</span>
                </div>
                <div class="text-[10px] text-slate-500 dark:text-slate-400 pt-1 border-t border-indigo-500/10">
                    مجموع كل الخزائن والحسابات
                </div>
            </div>
        </div>
    </div>

    <!-- 5 Daily High-Level Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <!-- 1. Opening Cash Balance -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl space-y-1 shadow-sm">
            <div class="text-xs font-bold text-slate-500 dark:text-slate-400">الرصيد الافتتاحي للدرج (الفكة)</div>
            <div class="text-xl font-black text-slate-900 dark:text-slate-200 font-mono mt-2"><?php echo e(number_format($openingCashBalance, 2)); ?> <span class="text-xs text-slate-400">ج.م</span></div>
            <div class="text-[10px] text-slate-400 pt-1 border-t border-slate-100 dark:border-slate-800/80">
                العهدة الافتتاحية لبداية اليوم
            </div>
        </div>

        <!-- 2. Cash Inflow Collected -->
        <div class="bg-white dark:bg-slate-900 border border-emerald-500/30 p-4 rounded-2xl space-y-1 bg-gradient-to-b from-white dark:from-slate-900 to-emerald-50/50 dark:to-emerald-950/20 shadow-sm">
            <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400">النقدية المقبوضة لليوم (كاش)</div>
            <div class="text-xl font-black text-emerald-600 dark:text-emerald-300 font-mono mt-2"><?php echo e(number_format($totalCashCollected, 2)); ?> <span class="text-xs text-emerald-500 dark:text-emerald-400">ج.م</span></div>
            <div class="text-[10px] text-slate-400 pt-1 border-t border-slate-100 dark:border-slate-800/80">
                مبيعات كاش + سندات قبض
            </div>
        </div>

        <!-- 3. Expenses Paid Out -->
        <div class="bg-white dark:bg-slate-900 border border-rose-500/30 p-4 rounded-2xl space-y-1 bg-gradient-to-b from-white dark:from-slate-900 to-rose-50/50 dark:to-rose-950/20 shadow-sm">
            <div class="text-xs font-bold text-rose-600 dark:text-rose-400 flex items-center justify-between">
                <span>المصروفات والنثريات</span>
                <a href="<?php echo e(route('expenses.index')); ?>" class="text-[10px] text-amber-600 dark:text-amber-400 hover:underline">عرض</a>
            </div>
            <div class="text-xl font-black text-rose-600 dark:text-rose-300 font-mono mt-2"><?php echo e(number_format($totalExpenses, 2)); ?> <span class="text-xs text-rose-500 dark:text-rose-400">ج.م</span></div>
            <div class="text-[10px] text-slate-400 pt-1 border-t border-slate-100 dark:border-slate-800/80">
                <?php echo e($expenses->count()); ?> مصروف (شنط، أكواب، صيانة)
            </div>
        </div>

        <!-- 4. Total Sales Volume -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl space-y-1 shadow-sm">
            <div class="text-xs font-bold text-slate-500 dark:text-slate-400">إجمالي مبيعات اليوم</div>
            <div class="text-xl font-black text-slate-900 dark:text-white font-mono mt-2"><?php echo e(number_format($totalSales, 2)); ?> <span class="text-xs text-amber-600 dark:text-amber-400">ج.م</span></div>
            <div class="text-[10px] text-slate-400 pt-1 border-t border-slate-100 dark:border-slate-800/80 flex justify-between">
                <span><?php echo e($invoicesCount); ?> فاتورة</span>
                <span>آجل: <?php echo e(number_format($creditSales, 1)); ?></span>
            </div>
        </div>

        <!-- 5. Total Expected Cash Physically in Drawer Right Now (CRITICAL) -->
        <div class="bg-white dark:bg-slate-900 border-2 border-amber-500/60 p-4 rounded-2xl space-y-1 relative overflow-hidden bg-gradient-to-b from-white dark:from-slate-900 to-amber-50/60 dark:to-amber-950/40 shadow-lg shadow-amber-500/10">
            <div class="text-xs font-black text-amber-600 dark:text-amber-400 flex items-center justify-between">
                <span>💰 المفروض في الدرج الآن</span>
                <span class="text-[10px] bg-amber-500/20 text-amber-800 dark:text-amber-300 px-1.5 py-0.5 rounded font-mono font-bold">الافتتاحي + المقبوض - المصروف</span>
            </div>
            <div class="text-2xl font-black text-amber-600 dark:text-amber-300 font-mono mt-2">
                <?php echo e(number_format($expectedCashInDrawer, 2)); ?> <span class="text-xs text-amber-500 dark:text-amber-400">ج.م</span>
            </div>
            <div class="text-[10px] text-slate-500 dark:text-slate-300 pt-1 border-t border-amber-500/30">
                النقدية الفعلية المفترض تسليمها في الدرج
            </div>
        </div>
    </div>

    <!-- Daily Operations Details Sections -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6" x-data="{ tab: 'invoices' }">
        <!-- Main Feed (2 cols) -->
        <div class="lg:col-span-2 space-y-4">
            <!-- Navigation Tabs -->
            <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 border-b border-slate-200 dark:border-slate-800 pb-2 text-[11px] sm:text-xs font-bold">
                <button @click="tab = 'invoices'" :class="tab === 'invoices' ? 'bg-amber-600 text-white shadow' : 'bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'" class="px-2.5 sm:px-3 py-1.5 rounded-xl transition-all cursor-pointer">
                    🧾 الفواتير (<?php echo e($invoicesCount); ?>)
                </button>
                <button @click="tab = 'expenses'" :class="tab === 'expenses' ? 'bg-amber-600 text-white shadow' : 'bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'" class="px-2.5 sm:px-3 py-1.5 rounded-xl transition-all cursor-pointer">
                    💸 المصروفات (<?php echo e($expenses->count()); ?>)
                </button>
                <button @click="tab = 'payments'" :class="tab === 'payments' ? 'bg-amber-600 text-white shadow' : 'bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'" class="px-2.5 sm:px-3 py-1.5 rounded-xl transition-all cursor-pointer">
                    💵 المقبوضات (<?php echo e($customerPayments->count()); ?>)
                </button>
                <button @click="tab = 'purchases'" :class="tab === 'purchases' ? 'bg-amber-600 text-white shadow' : 'bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'" class="px-2.5 sm:px-3 py-1.5 rounded-xl transition-all cursor-pointer">
                    🛒 المشتريات (<?php echo e($purchases->count()); ?>)
                </button>
                <button @click="tab = 'transfers'" :class="tab === 'transfers' ? 'bg-purple-600 text-white shadow' : 'bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'" class="px-2.5 sm:px-3 py-1.5 rounded-xl transition-all cursor-pointer">
                    🔄 التحويلات بين الخزن (<?php echo e($transfers->count()); ?>)
                </button>
            </div>

            <!-- Tab 1: Invoices Table -->
            <div x-show="tab === 'invoices'" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="p-3 bg-slate-50 dark:bg-slate-950/60 border-b border-slate-200 dark:border-slate-800 font-bold text-xs text-slate-900 dark:text-white">
                    فواتير مبيعات يوم <?php echo e($selectedDate); ?>

                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="p-3">رقم الفاتورة</th>
                                <th class="p-3">العميل</th>
                                <th class="p-3">الوقت</th>
                                <th class="p-3">النوع</th>
                                <th class="p-3">الإجمالي</th>
                                <th class="p-3">المدفوع</th>
                                <th class="p-3 text-center">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $inv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="p-3 font-mono font-bold text-amber-600 dark:text-amber-400">
                                    <a href="<?php echo e(route('invoices.show', $inv->id)); ?>" class="hover:underline"><?php echo e($inv->invoice_number); ?></a>
                                </td>
                                <td class="p-3 font-bold text-slate-800 dark:text-slate-200"><?php echo e($inv->customer->name ?? 'عميل نقدي'); ?></td>
                                <td class="p-3 text-slate-500 dark:text-slate-400 font-mono text-[11px]"><?php echo e($inv->created_at->format('h:i A')); ?></td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo e($inv->payment_type === 'cash' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : ($inv->payment_type === 'credit' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : 'bg-amber-500/10 text-amber-600 dark:text-amber-400')); ?>">
                                        <?php echo e($inv->payment_type === 'cash' ? 'كاش' : ($inv->payment_type === 'credit' ? 'آجل' : 'جزئي')); ?>

                                    </span>
                                </td>
                                <td class="p-3 font-mono font-bold text-slate-900 dark:text-white"><?php echo e(number_format($inv->net_total, 2)); ?></td>
                                <td class="p-3 font-mono text-emerald-600 dark:text-emerald-400"><?php echo e(number_format($inv->paid_amount, 2)); ?></td>
                                <td class="p-3 text-center flex items-center justify-center gap-1">
                                    <a href="<?php echo e(route('invoices.edit', $inv->id)); ?>" class="px-2 py-1 bg-amber-500/10 hover:bg-amber-500 hover:text-slate-950 text-amber-600 dark:text-amber-400 rounded text-[10px] font-bold transition-colors">
                                        ✏️ تعديل
                                    </a>
                                    <a href="<?php echo e(route('invoices.show', $inv->id)); ?>" class="px-2 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded text-[10px] font-bold transition-colors border border-slate-200 dark:border-slate-700">
                                        عرض
                                    </a>
                                </td>
                            </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400">لا توجد فواتير مبيعات مسجلة في هذا اليوم</td>
                            </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: Expenses Table -->
            <div x-show="tab === 'expenses'" x-cloak class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="p-3 bg-slate-50 dark:bg-slate-950/60 border-b border-slate-200 dark:border-slate-800 font-bold text-xs text-slate-900 dark:text-white">
                    مصروفات ونثريات يوم <?php echo e($selectedDate); ?>

                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="p-3">التصنيف</th>
                                <th class="p-3">بيان المصروف</th>
                                <th class="p-3">الوقت</th>
                                <th class="p-3">طريقة الدفع</th>
                                <th class="p-3">المبلغ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $expenses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $exp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20"><?php echo e($exp->category); ?></span>
                                </td>
                                <td class="p-3 font-bold text-slate-800 dark:text-slate-200"><?php echo e($exp->title); ?></td>
                                <td class="p-3 text-slate-500 dark:text-slate-400 font-mono text-[11px]"><?php echo e($exp->created_at->format('h:i A')); ?></td>
                                <td class="p-3 text-slate-600 dark:text-slate-400"><?php echo e($exp->payment_method === 'cash' ? 'نقدي (كاش)' : $exp->payment_method); ?></td>
                                <td class="p-3 font-mono font-bold text-rose-600 dark:text-rose-400"><?php echo e(number_format($exp->amount, 2)); ?> ج.م</td>
                            </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr>
                                <td colspan="5" class="p-8 text-center text-slate-400">لا توجد مصروفات مسجلة في هذا اليوم</td>
                            </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 3: Customer Payments Table -->
            <div x-show="tab === 'payments'" x-cloak class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="p-3 bg-slate-50 dark:bg-slate-950/60 border-b border-slate-200 dark:border-slate-800 font-bold text-xs text-slate-900 dark:text-white">
                    سندات القبض وتحصيلات العملاء في يوم <?php echo e($selectedDate); ?>

                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="p-3">رقم السند</th>
                                <th class="p-3">العميل</th>
                                <th class="p-3">الوقت</th>
                                <th class="p-3">طريقة التحصيل</th>
                                <th class="p-3">المبلغ المقبوض</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $customerPayments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pay): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="p-3 font-mono font-bold text-slate-800 dark:text-slate-300"><?php echo e($pay->payment_number); ?></td>
                                <td class="p-3 font-bold text-slate-800 dark:text-slate-200"><?php echo e($pay->customer->name ?? 'عميل'); ?></td>
                                <td class="p-3 text-slate-500 dark:text-slate-400 font-mono text-[11px]"><?php echo e($pay->created_at->format('h:i A')); ?></td>
                                <td class="p-3 text-slate-600 dark:text-slate-400"><?php echo e($pay->payment_method); ?></td>
                                <td class="p-3 font-mono font-bold text-emerald-600 dark:text-emerald-400"><?php echo e(number_format($pay->amount, 2)); ?> ج.م</td>
                            </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr>
                                <td colspan="5" class="p-8 text-center text-slate-400">لا توجد سندات قبض في هذا اليوم</td>
                            </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 4: Purchases Table -->
            <div x-show="tab === 'purchases'" x-cloak class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="p-3 bg-slate-50 dark:bg-slate-950/60 border-b border-slate-200 dark:border-slate-800 font-bold text-xs text-slate-900 dark:text-white">
                    مشتريات وتوريدات يوم <?php echo e($selectedDate); ?>

                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="p-3">رقم الفاتورة</th>
                                <th class="p-3">المورد</th>
                                <th class="p-3">الوقت</th>
                                <th class="p-3">الإجمالي</th>
                                <th class="p-3">المسدد</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $purchases; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pur): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="p-3 font-mono font-bold text-amber-600 dark:text-amber-400"><?php echo e($pur->purchase_number); ?></td>
                                <td class="p-3 font-bold text-slate-800 dark:text-slate-200"><?php echo e($pur->supplier->name ?? 'مورد'); ?></td>
                                <td class="p-3 text-slate-500 dark:text-slate-400 font-mono text-[11px]"><?php echo e($pur->created_at->format('h:i A')); ?></td>
                                <td class="p-3 font-mono font-bold text-slate-900 dark:text-white"><?php echo e(number_format($pur->net_total, 2)); ?></td>
                                <td class="p-3 font-mono text-emerald-600 dark:text-emerald-400"><?php echo e(number_format($pur->paid_amount, 2)); ?></td>
                            </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr>
                                <td colspan="5" class="p-8 text-center text-slate-400">لا توجد مشتريات مسجلة في هذا اليوم</td>
                            </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 5: Treasury Transfers Table -->
            <div x-show="tab === 'transfers'" x-cloak class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="p-3 bg-slate-50 dark:bg-slate-950/60 border-b border-slate-200 dark:border-slate-800 font-bold text-xs text-slate-900 dark:text-white flex items-center justify-between">
                    <span>حركات التحويل بين الخزن وحسابات الدفع يوم <?php echo e($selectedDate); ?></span>
                    <button type="button" wire:click="openTransferModal" class="px-2.5 py-1 bg-purple-600 hover:bg-purple-500 text-white rounded-lg text-[10px] font-bold cursor-pointer">
                        + تحويل جديد
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="p-3">رقم التحويل</th>
                                <th class="p-3">من الخزينة</th>
                                <th class="p-3">إلى الخزينة</th>
                                <th class="p-3">الوقت</th>
                                <th class="p-3">المبلغ المحول</th>
                                <th class="p-3">الرسوم / العمولة</th>
                                <th class="p-3">المسؤول والبيان</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $transfers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $trf): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="p-3 font-mono font-bold text-purple-600 dark:text-purple-400"><?php echo e($trf->transfer_number); ?></td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                        <?php echo e($trf->from_method_icon); ?> <?php echo e($trf->from_method_label); ?>

                                    </span>
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                                        <?php echo e($trf->to_method_icon); ?> <?php echo e($trf->to_method_label); ?>

                                    </span>
                                </td>
                                <td class="p-3 text-slate-500 dark:text-slate-400 font-mono text-[11px]"><?php echo e($trf->created_at->format('h:i A')); ?></td>
                                <td class="p-3 font-mono font-black text-slate-900 dark:text-white"><?php echo e(number_format($trf->amount, 2)); ?> ج.م</td>
                                <td class="p-3 font-mono text-rose-600 dark:text-rose-400"><?php echo e(bccomp($trf->transfer_fee, '0.000', 3) > 0 ? number_format($trf->transfer_fee, 2) . ' ج.م' : '—'); ?></td>
                                <td class="p-3 text-slate-600 dark:text-slate-400 text-[11px]">
                                    <span class="font-bold text-slate-800 dark:text-slate-200"><?php echo e($trf->user->name ?? 'مستخدم'); ?></span>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($trf->notes): ?>
                                        <div class="text-[10px] text-slate-400"><?php echo e($trf->notes); ?></div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                            </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400">لا توجد تحويلات مسجلة بين الخزن في هذا اليوم</td>
                            </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Col: Daily Shifts Log & Drawer Reconciliation -->
        <div class="space-y-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 space-y-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2 flex items-center justify-between">
                    <span>⏱️ سجل الورديات وتقفيل الـ Z-Report</span>
                </h3>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $shiftsOnDate; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sh): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
                    <div class="flex items-center justify-between font-bold">
                        <span class="text-amber-600 dark:text-amber-400">وردية رقم #<?php echo e($sh->shift_number); ?></span>
                        <span class="px-2 py-0.5 rounded text-[10px] <?php echo e($sh->status === 'closed' ? 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-400' : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'); ?>">
                            <?php echo e($sh->status === 'closed' ? 'مقفلة' : '🟢 جارية الآن'); ?>

                        </span>
                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400">
                        الكاشير: <span class="text-slate-800 dark:text-slate-200 font-bold"><?php echo e($sh->user->name ?? 'مستخدم'); ?></span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 pt-1.5 border-t border-slate-200 dark:border-slate-900 text-[11px]">
                        <div>الافتتاحي: <span class="font-mono text-slate-900 dark:text-white font-bold"><?php echo e(number_format($sh->opening_cash_balance, 2)); ?></span></div>
                        <div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sh->status === 'open'): ?>
                                <span class="text-amber-600 dark:text-amber-400 font-bold">المتوقع: <?php echo e(number_format($expectedCashInDrawer, 2)); ?></span>
                            <?php else: ?>
                                الفعلي: <span class="font-mono text-slate-900 dark:text-white font-bold"><?php echo e(number_format($sh->actual_cash_balance, 2)); ?></span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sh->status === 'closed' && bccomp((string)$sh->cash_difference, '0.000', 3) != 0): ?>
                    <div class="pt-1 text-[11px] font-bold <?php echo e(bccomp((string)$sh->cash_difference, '0.000', 3) < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'); ?>">
                        <?php echo e(bccomp((string)$sh->cash_difference, '0.000', 3) < 0 ? 'عجز في الدرج:' : 'زيادة في الدرج:'); ?>

                        <span class="font-mono"><?php echo e(number_format($sh->cash_difference, 2)); ?> ج.م</span>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <div class="p-4 text-center text-slate-400 text-xs">
                    لا توجد ورديات مسجلة في هذا التاريخ
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Open Shift Modal -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showOpenModal): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-md p-6 space-y-4 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                <h3 class="font-bold text-slate-900 dark:text-white text-base">🟢 فتح يومية / وردية عمل جديدة</h3>
                <button wire:click="$set('showOpenModal', false)" class="text-slate-400 hover:text-slate-700 dark:hover:text-white">✕</button>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">الرصيد الافتتاحي للدرج (الفكة / العهدة):</label>
                    <input type="number" step="0.001" wire:model="opening_cash_balance" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400 focus:outline-none focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">ملاحظات الفتح:</label>
                    <textarea wire:model="open_notes" rows="2" placeholder="ملاحظات تسليم الدرج أو اسم الكاشير..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500"></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                <button type="button" wire:click="$set('showOpenModal', false)" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold cursor-pointer">إلغاء</button>
                <button type="button" wire:click="startShift" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-600/30 cursor-pointer">تأكيد فتح اليومية</button>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <!-- Close Shift Modal (Z-Report) -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showCloseModal): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-md p-6 space-y-4 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                <h3 class="font-bold text-rose-600 dark:text-rose-400 text-base">🔴 تقفيل اليومية (Z-Report)</h3>
                <button wire:click="$set('showCloseModal', false)" class="text-slate-400 hover:text-slate-700 dark:hover:text-white">✕</button>
            </div>

            <div class="space-y-3 text-xs">
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 font-mono space-y-1">
                    <div class="flex justify-between text-slate-500 dark:text-slate-400">
                        <span>الرصيد الافتتاحي:</span>
                        <span class="text-slate-900 dark:text-white"><?php echo e(number_format($activeShift->opening_cash_balance, 2)); ?> ج.م</span>
                    </div>
                    <div class="flex justify-between text-emerald-600 dark:text-emerald-400">
                        <span>+ النقدية المقبوضة:</span>
                        <span><?php echo e(number_format($totalCashCollected, 2)); ?> ج.م</span>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(bccomp($totalExpenses, '0.000', 3) > 0): ?>
                    <div class="flex justify-between text-rose-600 dark:text-rose-400">
                        <span>- المصروفات:</span>
                        <span><?php echo e(number_format($totalExpenses, 2)); ?> ج.م</span>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <div class="flex justify-between text-amber-600 dark:text-amber-400 font-bold pt-1 border-t border-slate-200 dark:border-slate-800 text-sm">
                        <span>💰 المفروض بالدرج:</span>
                        <span><?php echo e(number_format($expectedCashInDrawer, 2)); ?> ج.م</span>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">النقدية الفعلية الموجودة في الدرج بعد العد والفرز:</label>
                    <input type="number" step="0.001" wire:model="actual_cash_balance" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-mono font-bold text-slate-900 dark:text-white focus:outline-none focus:border-rose-500">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">ملاحظات التقفيل:</label>
                    <textarea wire:model="close_notes" rows="2" placeholder="ملاحظات تسليم الدرج أو أسباب العجز/الزيادة إن وجدت..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-rose-500"></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                <button type="button" wire:click="$set('showCloseModal', false)" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold cursor-pointer">إلغاء</button>
                <button type="button" wire:click="submitCloseShift" class="px-5 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-rose-600/30 cursor-pointer">تأكيد التقفيل وإصدار Z-Report</button>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <!-- Treasury Transfer Modal -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showTransferModal): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-lg p-6 space-y-4 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                <h3 class="font-bold text-slate-900 dark:text-white text-base flex items-center gap-2">
                    <span>🔄 تحويل رصيد مالي بين الخزائن والحسابات</span>
                </h3>
                <button wire:click="$set('showTransferModal', false)" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-sm cursor-pointer">✕</button>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errorMessage): ?>
            <div class="p-2.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 text-xs font-bold">
                <?php echo e($errorMessage); ?>

            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="space-y-3.5 text-xs">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- From Method -->
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">التحويل من (الخزينة المحول منها):</label>
                        <select wire:model.live="transfer_from_method" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-purple-500">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Enums\PaymentMethod::activeMethods(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php $b = $treasuryBalances[$m->value]['balance'] ?? '0.000'; ?>
                            <option value="<?php echo e($m->value); ?>"><?php echo e($m->icon()); ?> <?php echo e($m->label()); ?> (رصيد: <?php echo e(number_format($b, 2)); ?> ج.م)</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                    </div>

                    <!-- To Method -->
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">التحويل إلى (الخزينة المستلمة):</label>
                        <select wire:model.live="transfer_to_method" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-purple-500">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Enums\PaymentMethod::activeMethods(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php $b = $treasuryBalances[$m->value]['balance'] ?? '0.000'; ?>
                            <option value="<?php echo e($m->value); ?>"><?php echo e($m->icon()); ?> <?php echo e($m->label()); ?> (رصيد: <?php echo e(number_format($b, 2)); ?> ج.م)</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                    </div>
                </div>

                <!-- Amount & Fees -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">المبلغ المراد تحويله (ج.م):</label>
                        <input 
                            type="number" 
                            step="0.001" 
                            min="0.001" 
                            wire:model="transfer_amount" 
                            placeholder="0.00" 
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-mono font-bold text-purple-600 dark:text-purple-400 focus:ring-2 focus:ring-purple-500"
                        >
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">رسوم / عمولة التحويل (اختياري):</label>
                        <input 
                            type="number" 
                            step="0.001" 
                            min="0" 
                            wire:model="transfer_fee" 
                            placeholder="0.00" 
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-mono font-bold text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-purple-500"
                        >
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">البيان / سبب التحويل:</label>
                    <input 
                        type="text" 
                        wire:model="transfer_notes" 
                        placeholder="مثال: سحب كاش من الـ ATM لتغذية درج المحل..." 
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-purple-500"
                    >
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                <button type="button" wire:click="$set('showTransferModal', false)" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold cursor-pointer">إلغاء</button>
                <button type="button" wire:click="executeTransfer" class="px-5 py-2 bg-purple-600 hover:bg-purple-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-purple-600/30 cursor-pointer">تأكيد وتنفيذ التحويل</button>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH I:\projects\erp-2026\backend\resources\views/livewire/daily-journal-index.blade.php ENDPATH**/ ?>