<script setup>
import { ref, computed } from 'vue';
import { useMoney } from '@/Composables/useMoney';
import { useNativeBridge } from '@/Composables/useNativeBridge';
import { Trash2, Minus, Plus, Tag } from 'lucide-vue-next';

const props = defineProps({
    line: { type: Object, required: true },
    index: { type: Number, required: true },
});

const emit = defineEmits(['remove', 'apply-last-price', 'change']);

const { formatMoney, formatQty } = useMoney();
const { triggerHaptic } = useNativeBridge();

// Touch Swipe State
const touchStartX = ref(0);
const touchCurrentX = ref(0);
const isSwiping = ref(false);
const swipeOffset = ref(0);

const onTouchStart = (e) => {
    touchStartX.value = e.touches[0].clientX;
    touchCurrentX.value = e.touches[0].clientX;
    isSwiping.value = true;
};

const onTouchMove = (e) => {
    if (!isSwiping.value) return;
    touchCurrentX.value = e.touches[0].clientX;
    const diff = touchStartX.value - touchCurrentX.value; // RTL drag
    if (diff > 0 && diff < 120) {
        swipeOffset.value = diff;
    } else if (diff <= 0) {
        swipeOffset.value = 0;
    }
};

const onTouchEnd = () => {
    if (swipeOffset.value > 60) {
        triggerHaptic('medium');
        swipeOffset.value = 75; // Stay open showing red delete action
    } else {
        swipeOffset.value = 0;
    }
    isSwiping.value = false;
};

const resetSwipe = () => {
    swipeOffset.value = 0;
};

const handleDelete = () => {
    triggerHaptic('heavy');
    emit('remove', props.index);
};

const isWeightBased = computed(() => {
    return props.line.unit === 'كجم' || props.line.unit === 'جم' || props.line.unit?.includes('كيلو');
});

const setExactWeight = (w) => {
    triggerHaptic('light');
    props.line.quantity = w;
    emit('change');
};

const decreaseQty = () => {
    triggerHaptic('light');
    const step = isWeightBased.value ? 0.250 : 1;
    const min = isWeightBased.value ? 0.125 : 1;
    if (props.line.quantity > min) {
        props.line.quantity = Number((props.line.quantity - step).toFixed(3));
        emit('change');
    } else {
        handleDelete();
    }
};

const increaseQty = () => {
    triggerHaptic('light');
    const step = isWeightBased.value ? 0.250 : 1;
    props.line.quantity = Number((props.line.quantity + step).toFixed(3));
    emit('change');
};
</script>

<template>
    <div class="relative overflow-hidden rounded-2xl select-none font-tajawal">
        <!-- Hidden Swipe-to-Delete Action Button (Underneath Card) -->
        <div
            v-if="swipeOffset > 0"
            class="absolute inset-y-0 left-0 w-20 bg-rose-600 flex items-center justify-center text-white z-0 cursor-pointer active:brightness-90 transition"
            @click="handleDelete"
        >
            <div class="flex flex-col items-center gap-1 font-bold text-[10px]">
                <Trash2 class="w-5 h-5" />
                <span>{{ $t('common.delete') }}</span>
            </div>
        </div>

        <!-- Main Cart Item Card (Supports Touch Swipe) -->
        <div
            @touchstart="onTouchStart"
            @touchmove="onTouchMove"
            @touchend="onTouchEnd"
            class="relative z-10 p-3 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 space-y-2.5 group hover:border-slate-300 dark:hover:border-slate-700 transition-transform duration-150 ease-out shadow-xs"
            :style="swipeOffset > 0 ? { transform: `translateX(-${swipeOffset}px)` } : {}"
        >
            <div class="flex items-center justify-between gap-2">
                <div class="flex-1 truncate">
                    <div class="font-black text-xs sm:text-sm text-slate-900 dark:text-white truncate">{{ line.name }}</div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono flex items-center gap-1.5 mt-1">
                        <span>{{ $t('invoices.unit_price') }}:</span>
                        <input
                            v-model.number="line.unit_price"
                            @input="emit('change')"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            class="w-18 h-7 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg px-1.5 text-center font-mono font-bold text-slate-900 dark:text-white text-xs focus:outline-none focus:border-theme-primary"
                        />
                        <span>{{ $t('common.currency') }}</span>
                    </div>
                </div>

                    <!-- Qty Steppers (Finger-friendly min 40px) -->
                    <div class="flex items-center gap-1.5 shrink-0">
                        <button
                            @click="decreaseQty"
                            type="button"
                            class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-white font-black text-base flex items-center justify-center transition active:scale-90 cursor-pointer border border-slate-200 dark:border-transparent shadow-xs"
                            aria-label="Decrease quantity"
                        >
                            <Minus class="w-3.5 h-3.5" />
                        </button>

                        <input
                            v-model.number="line.quantity"
                            @input="emit('change')"
                            type="number"
                            inputmode="decimal"
                            step="0.001"
                            class="w-16 h-9 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-center text-xs font-mono font-black text-theme-primary text-theme-primary focus:outline-none focus:border-theme-primary shadow-inner"
                        />

                        <button
                            @click="increaseQty"
                            type="button"
                            class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-white font-black text-base flex items-center justify-center transition active:scale-90 cursor-pointer border border-slate-200 dark:border-transparent shadow-xs"
                            aria-label="Increase quantity"
                        >
                            <Plus class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <!-- Line Total & Delete -->
                    <div class="flex items-center gap-2 shrink-0 pl-1">
                        <div class="font-mono font-black text-xs sm:text-sm text-emerald-600 dark:text-emerald-400 text-left">
                            {{ formatMoney(line.unit_price * line.quantity) }}
                        </div>
                        <button
                            @click="handleDelete"
                            type="button"
                            class="w-9 h-9 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs flex items-center justify-center transition active:scale-90 cursor-pointer shadow-xs"
                            :title="$t('common.delete')"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Quick Weight Chips for Bulk / Coffee -->
                <div v-if="isWeightBased" class="flex items-center gap-1.5 pt-2 border-t border-slate-100 dark:border-slate-900 text-xs">
                    <span class="text-slate-500 text-[10px] px-1">{{ $t('common.quantity') }}:</span>
                    <button
                        @click="setExactWeight(0.125)"
                        type="button"
                        class="h-7 px-2.5 rounded-lg bg-slate-100 hover:bg-theme-hover hover:text-slate-950 dark:bg-slate-900 text-slate-700 dark:text-slate-300 font-mono font-black text-xs transition active:scale-90"
                    >
                        1/8
                    </button>
                    <button
                        @click="setExactWeight(0.250)"
                        type="button"
                        class="h-7 px-2.5 rounded-lg bg-slate-100 hover:bg-theme-hover hover:text-slate-950 dark:bg-slate-900 text-slate-700 dark:text-slate-300 font-mono font-black text-xs transition active:scale-90"
                    >
                        1/4
                    </button>
                    <button
                        @click="setExactWeight(0.500)"
                        type="button"
                        class="h-7 px-2.5 rounded-lg bg-slate-100 hover:bg-theme-hover hover:text-slate-950 dark:bg-slate-900 text-slate-700 dark:text-slate-300 font-mono font-black text-xs transition active:scale-90"
                    >
                        1/2
                    </button>
                    <button
                        @click="setExactWeight(1.000)"
                        type="button"
                        class="h-7 px-2.5 rounded-lg bg-emerald-600/20 hover:bg-emerald-500 hover:text-white text-emerald-700 dark:text-emerald-300 font-mono font-black text-xs transition active:scale-90"
                    >
                        1ك
                    </button>
                </div>

                <!-- Last Customer Price Tag (if available) -->
                <div v-if="line.last_sold_price" class="flex items-center justify-between pt-1.5 border-t border-slate-100 dark:border-slate-900 text-[11px]">
                    <span class="text-theme-primary text-theme-primary font-mono flex items-center gap-1">
                        <Tag class="w-3.5 h-3.5 shrink-0" />
                        <span>{{ $t('pos.last_customer_price') }}: {{ formatMoney(line.last_sold_price.unit_price) }} {{ $t('common.currency') }}</span>
                    </span>
                    <button
                        @click="emit('apply-last-price', line)"
                        type="button"
                        class="h-7 px-3 rounded-lg bg-theme-light hover:bg-theme-hover/30 text-theme-primary text-theme-primary font-black text-[10px] transition cursor-pointer"
                    >
                        {{ $t('pos.apply_btn') }}
                    </button>
                </div>
        </div>
    </div>
</template>