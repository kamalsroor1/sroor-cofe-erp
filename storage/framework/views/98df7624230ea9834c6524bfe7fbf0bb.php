<div class="relative" x-data="{ open: <?php if ((object) ('isOpen') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('isOpen'->value()); ?>')<?php echo e('isOpen'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('isOpen'); ?>')<?php endif; ?> }" @click.outside="open = false">
    <!-- Bell Button -->
    <button 
        type="button" 
        wire:click="toggleDropdown"
        class="relative p-2.5 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition-all cursor-pointer border border-slate-200 dark:border-slate-700 flex items-center justify-center"
        title="مركز الإشعارات والتنبيهات"
    >
        <span class="text-lg">🔔</span>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($totalAlertsCount > 0): ?>
        <span class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-5 w-5 bg-rose-600 text-white font-mono font-black text-[10px] items-center justify-center shadow-md shadow-rose-600/30">
                <?php echo e($totalAlertsCount); ?>

            </span>
        </span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </button>

    <!-- Dropdown Menu -->
    <div 
        x-show="open" 
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95 translate-y-2"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-2"
        class="absolute left-0 sm:left-auto sm:right-0 mt-3 w-80 sm:w-96 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl z-50 overflow-hidden"
        style="display: none;"
    >
        <!-- Dropdown Header -->
        <div class="p-4 bg-slate-50 dark:bg-slate-950/80 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-base">🔔</span>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white font-tajawal">التنبيهات المباشرة</h3>
            </div>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold <?php echo e($totalAlertsCount > 0 ? 'bg-rose-500/20 text-rose-600' : 'bg-slate-200 dark:bg-slate-800 text-slate-500'); ?>">
                <?php echo e($totalAlertsCount); ?> تنبيه
            </span>
        </div>

        <!-- Notifications List -->
        <div class="max-h-96 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/60 p-2 space-y-1">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $notifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notif): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div class="p-3 rounded-2xl transition-colors <?php echo e($notif['type'] === 'danger' ? 'bg-rose-50/60 dark:bg-rose-950/20 hover:bg-rose-50 dark:hover:bg-rose-950/40' : ($notif['type'] === 'warning' ? 'bg-amber-50/60 dark:bg-amber-950/20 hover:bg-amber-50 dark:hover:bg-amber-950/40' : 'bg-cyan-50/60 dark:bg-cyan-950/20 hover:bg-cyan-50 dark:hover:bg-cyan-950/40')); ?>">
                <div class="flex items-start gap-2.5">
                    <span class="text-xl shrink-0 mt-0.5"><?php echo e($notif['icon']); ?></span>
                    <div class="flex-1 min-w-0">
                        <h4 class="text-xs font-black text-slate-900 dark:text-white font-tajawal"><?php echo e($notif['title']); ?></h4>
                        <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-0.5 leading-relaxed"><?php echo e($notif['description']); ?></p>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($notif['link'])): ?>
                        <a 
                            href="<?php echo e($notif['link']); ?>" 
                            class="inline-block mt-1.5 text-[11px] font-bold text-amber-600 dark:text-amber-400 hover:underline"
                        >
                            <?php echo e($notif['link_label']); ?>

                        </a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <div class="p-8 text-center text-slate-400">
                <span class="text-3xl block mb-2">🎉</span>
                <p class="text-xs font-bold">لا توجد أي تنبيهات أو نواقص حالياً.</p>
                <p class="text-[11px] text-slate-500 mt-0.5">كل الأرصدة والمخزون في وضع آمن ومستقر.</p>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <!-- Dropdown Footer -->
        <div class="p-3 bg-slate-50 dark:bg-slate-950/80 border-t border-slate-200 dark:border-slate-800 text-center">
            <a 
                href="<?php echo e(route('reports.index')); ?>" 
                class="text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-amber-600 dark:hover:text-amber-400 transition-colors block"
            >
                عرض كافة التقارير والتحليلات المالية ←
            </a>
        </div>
    </div>
</div>
<?php /**PATH I:\projects\erp-2026\backend\resources\views/livewire/notification-center.blade.php ENDPATH**/ ?>