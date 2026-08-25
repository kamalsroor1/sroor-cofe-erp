<script setup>
import { ref, computed } from 'vue';
import { useMoney } from '@/Composables/useMoney';
import { useNativeBridge } from '@/Composables/useNativeBridge';
import { trans } from '@/helpers/trans';
import { X, Scale } from 'lucide-vue-next';

const props = defineProps({
    show: { type: Boolean, default: false },
    item: { type: Object, default: null },
    customerPriceTier: { type: String, default: 'retail' },
});

const emit = defineEmits(['close', 'confirm']);

const presetWeights = computed(() => [
    { label: trans('inventory.weight_eighth'), qty: 0.125 },
    { label: trans('inventory.weight_quarter'), qty: 0.250 },
    { label: trans('inventory.weight_half'), qty: 0.500 },
    { label: trans('inventory.weight_kilo'), qty: 1.000 },
]);

const { formatMoney } = useMoney();
const { triggerHaptic } = useNativeBridge();

const customWeightInput = ref('');

// Touch Drag-to-Close State for Mobile Bottom Sheet
const touchStartY = ref(0);
const touchCurrentY = ref(0);
const dragOffset = ref(0);
const isDragging = ref(false);

const effectiveKiloPrice = (item) => {
    if (!item) return 0;
    const retail = parseFloat(item.selling_price ?? item.price_retail ?? item.price ?? 0);
    const wholesale = parseFloat(item.min_selling_price ?? item.price_wholesale ?? retail);
    return props.customerPriceTier === 'wholesale' ? (wholesale > 0 ? wholesale : retail) : (retail > 0 ? retail : wholesale);
};

const selectPreset = (qty) => {
    triggerHaptic('medium');
    emit('confirm', { item: props.item, quantity: qty });
    emit('close');
};

const applyCustomWeight = () => {
    const val = parseFloat(customWeightInput.value);
    if (!isNaN(val) && val > 0) {
        triggerHaptic('medium');
        emit('confirm', { item: props.item, quantity: val });
        customWeightInput.value = '';
        emit('close');
    }
};

const close = () => {
    dragOffset.value = 0;
    emit('close');
};

// Drag Gesture Handlers
const onTouchStart = (e) => {
    touchStartY.value = e.touches[0].clientY;
    touchCurrentY.value = e.touches[0].clientY;
    isDragging.value = true;
};

const onTouchMove = (e) => {
    if (!isDragging.value) return;
    touchCurrentY.value = e.touches[0].clientY;
    const diff = touchCurrentY.value - touchStartY.value;
    if (diff > 0) {
        dragOffset.value = diff;
    }
};

const onTouchEnd = () => {
    if (!isDragging.value) return;
    if (dragOffset.value > 70) {
        triggerHaptic('medium');
        close();
    } else {
        dragOffset.value = 0;
    }
    isDragging.value = false;
};
</script>

<template>
    <Teleport to="body">
        <Transition name="sheet-slide">
            <div
                v-if="show && item"
                @click="close"
                class="fixed inset-0 z-50 bg-black/75 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4 font-tajawal select-none"
                dir="rtl"
            >
                <div
                    @click.stop
                    class="w-full sm:max-w-md bg-white dark:bg-slate-900 border-t sm:border border-slate-200 dark:border-slate-800 rounded-t-3xl sm:rounded-3xl p-5 sm:p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto transition-transform duration-150 ease-out pb-[max(1.25rem,env(safe-area-inset-bottom,1.25rem))] sm:pb-6"
                    :style="dragOffset > 0 ? { transform: `translateY(${dragOffset}px)` } : {}"
                >
                    <!-- Native Mobile Drag Handle -->
                    <div
                        class="sm:hidden flex flex-col items-center justify-center -mt-2 -mb-1 py-1 cursor-grab active:cursor-grabbing shrink-0"
                        @touchstart="onTouchStart"
                        @touchmove="onTouchMove"
                        @touchend="onTouchEnd"
                    >
                        <div class="w-12 h-1.5 rounded-full bg-slate-300 dark:bg-slate-700" />
                    </div>

                    <!-- Header -->
                    <div
                        class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3"
                        @touchstart="onTouchStart"
                        @touchmove="onTouchMove"
                        @touchend="onTouchEnd"
                    >
                        <div class="space-y-0.5">
                            <h3 class="font-black text-sm sm:text-base text-slate-900 dark:text-white leading-tight">{{ item.name }}</h3>
                            <p class="text-[11px] text-emerald-600 dark:text-emerald-400 font-mono font-bold">
                                {{ $t('pos.kilo_price') }}: {{ formatMoney(effectiveKiloPrice(item)) }} {{ $t('common.currency') }}
                            </p>
                        </div>
                        <button
                            @click="close"
                            type="button"
                            class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white flex items-center justify-center text-sm font-bold transition active:scale-90 cursor-pointer shadow-xs shrink-0"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Preset Weights Grid (Buttons) -->
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="w in presetWeights"
                            :key="w.qty"
                            @click="selectPreset(w.qty)"
                            type="button"
                            class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/80 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700/80 transition text-right group cursor-pointer active:scale-95 shadow-xs"
                        >
                            <span class="block text-xs sm:text-sm font-black text-slate-900 dark:text-white group-hover:text-theme-primary transition">{{ w.label }}</span>
                            <span class="block text-[11px] text-slate-400 font-mono mt-0.5">
                                {{ formatMoney(effectiveKiloPrice(item) * w.qty) }} {{ $t('common.currency') }}
                            </span>
                        </button>
                    </div>

                    <!-- Custom Weight Input -->
                    <div class="pt-2 border-t border-slate-200 dark:border-slate-800 space-y-2">
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400">
                            {{ $t('pos.custom_weight') }} ({{ $t('pos.grams_or_kilos') }})
                        </label>
                        <div class="flex gap-2">
                            <input
                                v-model="customWeightInput"
                                type="number"
                                step="any"
                                min="0"
                                inputmode="decimal"
                                :placeholder="$t('pos.enter_custom_weight')"
                                @keyup.enter="applyCustomWeight"
                                class="flex-1 h-11 px-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white text-xs font-mono font-bold focus:ring-2 focus:ring-theme-primary outline-hidden transition shadow-xs"
                            />
                            <button
                                @click="applyCustomWeight"
                                type="button"
                                class="h-11 px-4 rounded-2xl btn-primary-theme font-black text-xs transition active:scale-95 cursor-pointer shrink-0 shadow-theme-primary"
                            >
                                {{ $t('common.confirm') }}
                            </button>
                        </div>
                        <p class="text-[10px] text-slate-400">
                            {{ $t('pos.weight_hint') }}
                        </p>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
