<script setup>
import { computed } from 'vue';
import { useMoney } from '@/Composables/useMoney';
import { useNativeBridge } from '@/Composables/useNativeBridge';

const props = defineProps({
    item: { type: Object, required: true },
    customerPriceTier: { type: String, default: 'retail' },
});

const emit = defineEmits(['select', 'add-qty']);

const { formatMoney, formatQty } = useMoney();
const { triggerHaptic } = useNativeBridge();

const effectivePrice = computed(() => {
    const retail = parseFloat(props.item?.selling_price ?? props.item?.price_retail ?? props.item?.price ?? 0);
    const wholesale = parseFloat(props.item?.min_selling_price ?? props.item?.price_wholesale ?? retail);
    return props.customerPriceTier === 'wholesale' ? (wholesale > 0 ? wholesale : retail) : (retail > 0 ? retail : wholesale);
});

const isWeightBased = computed(() => {
    return props.item.unit === 'كجم' || props.item.unit === 'جم' || props.item.unit?.includes('كيلو');
});

const stockBadgeClass = computed(() => {
    if (props.item.current_stock > props.item.min_stock_level) {
        return 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30';
    }
    if (props.item.current_stock > 0) {
        return 'bg-theme-primary/15 text-theme-primary text-theme-primary border border-theme-border';
    }
    return 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30';
});

const onSelect = () => {
    triggerHaptic('light');
    emit('select', props.item);
};

const onAddQty = (qty) => {
    triggerHaptic('medium');
    emit('add-qty', { item: props.item, quantity: qty });
};
</script>

<template>
    <div
        class="bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/90 border border-slate-200 dark:border-slate-800 hover:border-theme-primary rounded-2xl flex flex-col justify-between transition-all duration-150 shadow-xs group overflow-hidden card-native-tap select-none min-h-[120px]"
    >
        <!-- Main Card Body (Touch Target >= 48px) -->
        <div
            @click="onSelect"
            role="button"
            tabindex="0"
            class="p-3 cursor-pointer select-none flex-1 flex flex-col justify-between active:bg-slate-100 dark:active:bg-slate-800/80 transition-colors"
        >
            <div>
                <div class="flex items-center justify-between text-[10px] text-slate-500 dark:text-slate-400 font-bold mb-1.5 gap-1">
                    <span class="truncate max-w-[65%]">{{ item.category || $t('common.all') }}</span>
                    <span class="px-2 py-0.5 rounded-full text-[9px] font-mono font-black shrink-0" :class="stockBadgeClass">
                        {{ formatQty(item.current_stock, 1) }} {{ item.unit }}
                    </span>
                </div>
                <h3 class="font-black text-xs sm:text-sm text-slate-900 dark:text-white line-clamp-2 leading-snug group-hover:text-theme-primary dark:group-hover:text-theme-primary transition">
                    {{ item.name }}
                </h3>
            </div>

            <div class="mt-3 pt-2 border-t border-slate-100 dark:border-slate-800/80 flex items-baseline justify-between gap-1">
                <div class="flex items-baseline gap-1">
                    <span class="text-sm font-black font-mono text-emerald-600 dark:text-emerald-400">
                        {{ formatMoney(effectivePrice) }}
                    </span>
                    <span class="text-[10px] text-slate-400 font-bold">{{ $t('common.currency') }}</span>
                </div>
                <span v-if="item.code" class="text-[10px] text-slate-400 font-mono font-bold truncate">
                    #{{ item.code }}
                </span>
            </div>
        </div>

        <!-- Direct Quick Weight Steppers Bar (For Coffee & Weight Items) -->
        <div v-if="isWeightBased" class="p-1.5 bg-slate-50 dark:bg-slate-900/90 border-t border-slate-200 dark:border-slate-800/80 grid grid-cols-4 gap-1.5">
            <button
                @click.stop="onAddQty(0.125)"
                type="button"
                class="h-9 rounded-xl bg-slate-200/90 hover:bg-theme-hover hover:text-slate-950 dark:bg-slate-800 dark:hover:bg-theme-hover text-slate-800 dark:text-slate-200 font-black text-xs font-mono transition-all duration-150 active:scale-90 flex items-center justify-center cursor-pointer shadow-xs"
                :title="$t('inventory.weight_eighth')"
            >
                1/8
            </button>
            <button
                @click.stop="onAddQty(0.250)"
                type="button"
                class="h-9 rounded-xl bg-slate-200/90 hover:bg-theme-hover hover:text-slate-950 dark:bg-slate-800 dark:hover:bg-theme-hover text-slate-800 dark:text-slate-200 font-black text-xs font-mono transition-all duration-150 active:scale-90 flex items-center justify-center cursor-pointer shadow-xs"
                :title="$t('inventory.weight_quarter')"
            >
                1/4
            </button>
            <button
                @click.stop="onAddQty(0.500)"
                type="button"
                class="h-9 rounded-xl bg-slate-200/90 hover:bg-theme-hover hover:text-slate-950 dark:bg-slate-800 dark:hover:bg-theme-hover text-slate-800 dark:text-slate-200 font-black text-xs font-mono transition-all duration-150 active:scale-90 flex items-center justify-center cursor-pointer shadow-xs"
                :title="$t('inventory.weight_half')"
            >
                1/2
            </button>
            <button
                @click.stop="onAddQty(1.000)"
                type="button"
                class="h-9 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs font-mono transition-all duration-150 active:scale-90 flex items-center justify-center cursor-pointer shadow-xs"
                :title="$t('inventory.weight_kilo')"
            >
                1ك
            </button>
        </div>
    </div>
</template>