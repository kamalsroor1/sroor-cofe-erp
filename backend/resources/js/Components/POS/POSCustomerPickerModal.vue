<script setup>
import { ref, computed } from 'vue';
import { useMoney } from '@/Composables/useMoney';
import { useNativeBridge } from '@/Composables/useNativeBridge';

const props = defineProps({
    show: { type: Boolean, default: false },
    customers: { type: Array, default: () => [] },
    selectedCustomerId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close', 'select', 'create-new', 'openQuickAdd']);

const { formatMoney } = useMoney();
const { triggerHaptic } = useNativeBridge();
const customerSearch = ref('');

// Touch Drag State for Mobile Bottom Sheet
const touchStartY = ref(0);
const touchCurrentY = ref(0);
const dragOffset = ref(0);
const isDragging = ref(false);

const filteredCustomers = computed(() => {
    const q = customerSearch.value.trim().toLowerCase();
    if (!q) return props.customers;
    return props.customers.filter(c =>
        c.name.toLowerCase().includes(q) ||
        c.phone?.includes(q)
    );
});

const close = () => {
    dragOffset.value = 0;
    emit('close');
};

const onSelectCustomer = (c) => {
    triggerHaptic('light');
    emit('select', c);
};

const onOpenCreate = () => {
    triggerHaptic('light');
    emit('openQuickAdd');
};

// Drag Handlers
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
                v-if="show"
                @click="close"
                class="fixed inset-0 z-50 bg-black/75 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4 font-tajawal select-none"
                dir="rtl"
            >
                <div
                    @click.stop
                    class="w-full sm:max-w-md bg-white dark:bg-slate-900 border-t sm:border border-slate-200 dark:border-slate-800 rounded-t-3xl sm:rounded-3xl p-5 sm:p-6 shadow-2xl space-y-3.5 max-h-[90vh] sm:max-h-[85vh] flex flex-col transition-transform duration-150 ease-out pb-[max(1.25rem,env(safe-area-inset-bottom,1.25rem))] sm:pb-6"
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
                        class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3 shrink-0"
                        @touchstart="onTouchStart"
                        @touchmove="onTouchMove"
                        @touchend="onTouchEnd"
                    >
                        <h3 class="font-black text-sm sm:text-base text-slate-900 dark:text-white">{{ $t('pos.choose_invoice_customer') }}</h3>
                        <button
                            @click="close"
                            type="button"
                            class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white flex items-center justify-center text-sm font-bold transition active:scale-90 cursor-pointer shadow-xs shrink-0"
                        >
                            ✕
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="relative shrink-0">
                        <input
                            v-model="customerSearch"
                            type="text"
                            :placeholder="$t('pos.search_customer_placeholder')"
                            class="w-full h-11 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl px-4 text-xs sm:text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:border-theme-primary shadow-inner"
                        />
                    </div>

                    <!-- Customer List -->
                    <div class="flex-1 overflow-y-auto space-y-2 pr-0.5 min-h-[140px]">
                        <div
                            v-for="c in filteredCustomers"
                            :key="c.id"
                            @click="onSelectCustomer(c)"
                            class="p-3 rounded-2xl border flex items-center justify-between cursor-pointer transition active:scale-98 shadow-xs min-h-[50px]"
                            :class="selectedCustomerId === c.id
                                ? 'bg-theme-light border-theme-primary text-theme-primary font-black'
                                : 'bg-slate-50 dark:bg-slate-800/40 border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'"
                        >
                            <div>
                                <p class="text-xs sm:text-sm font-black">{{ c.name }}</p>
                                <p class="text-[11px] text-slate-400 font-mono mt-0.5">{{ c.phone || $t('common.no_phone') }}</p>
                            </div>
                            <div class="text-left font-mono">
                                <span class="text-xs font-bold" :class="c.current_balance > 0 ? 'text-rose-500' : 'text-emerald-500'">
                                    {{ formatMoney(c.current_balance || 0) }} {{ $t('common.currency') }}
                                </span>
                            </div>
                        </div>

                        <div v-if="filteredCustomers.length === 0" class="py-8 text-center text-slate-400 text-xs font-bold">
                            {{ $t('contacts.no_customers_found') }}
                        </div>
                    </div>

                    <!-- Quick Add Customer Button -->
                    <div class="pt-2 border-t border-slate-200 dark:border-slate-800 shrink-0">
                        <button
                            @click="onOpenCreate"
                            type="button"
                            class="w-full h-11 rounded-2xl bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 text-amber-700 dark:text-amber-300 font-black text-xs flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer shadow-xs"
                        >
                            <span>➕</span>
                            <span>{{ $t('contacts.add_new_customer') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
