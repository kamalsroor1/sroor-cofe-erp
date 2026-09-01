<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'placeholder' => 'اختر التاريخ...',
    'enableTime' => false,
    'dateFormat' => 'Y-m-d',
    'altFormat' => 'Y-m-d',
    'mode' => 'single',
    'minDate' => null,
    'maxDate' => null,
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'placeholder' => 'اختر التاريخ...',
    'enableTime' => false,
    'dateFormat' => 'Y-m-d',
    'altFormat' => 'Y-m-d',
    'mode' => 'single',
    'minDate' => null,
    'maxDate' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div
    x-data="{
        value: <?php if ((object) ($attributes->wire('model')) instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e($attributes->wire('model')->value()); ?>')<?php echo e($attributes->wire('model')->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e($attributes->wire('model')); ?>')<?php endif; ?>,
        instance: null,
        init() {
            this.instance = flatpickr(this.$refs.input, {
                locale: 'ar',
                monthSelectorType: 'static',
                disableMobile: true,
                mode: '<?php echo e($mode); ?>',
                dateFormat: '<?php echo e($dateFormat); ?>',
                altInput: true,
                altFormat: '<?php echo e($altFormat); ?>',
                enableTime: <?php echo e($enableTime ? 'true' : 'false'); ?>,
                <?php if($minDate): ?> minDate: '<?php echo e($minDate); ?>', <?php endif; ?>
                <?php if($maxDate): ?> maxDate: '<?php echo e($maxDate); ?>', <?php endif; ?>
                defaultDate: this.value,
                onChange: (selectedDates, dateStr) => {
                    this.value = dateStr;
                }
            });

            this.$watch('value', (val) => {
                if (this.instance && val !== this.instance.input.value) {
                    this.instance.setDate(val, false);
                }
            });
        }
    }"
    class="relative inline-block w-full"
>
    <input
        x-ref="input"
        type="text"
        placeholder="<?php echo e($placeholder); ?>"
        <?php echo e($attributes->whereDoesntStartWith('wire:model')->merge([
            'class' => 'w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs font-mono font-bold focus:ring-2 focus:ring-amber-500 focus:outline-none cursor-pointer placeholder-slate-400 dark:placeholder-slate-500'
        ])); ?>

    />
</div>
<?php /**PATH I:\projects\erp-2026\backend\resources\views/components/datepicker.blade.php ENDPATH**/ ?>