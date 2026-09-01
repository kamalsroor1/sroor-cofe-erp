<script setup>
import SearchableSelect from '@/Components/SearchableSelect.vue';

const props = defineProps({
    items: { type: Array, required: true },
    itemOptions: { type: Array, required: true },
    selectedItem: { type: [Number, String, null], default: null },
    priceLabel: { type: String, default: '' },
    priceField: { type: String, default: 'unit_cost' }, // 'unit_cost' | 'unit_price'
    searchPlaceholder: { type: String, default: '' },
    emptyMessage: { type: String, default: '' },
    addLabel: { type: String, default: '+' },
});

const emit = defineEmits(['update:selectedItem', 'add', 'remove', 'update:item']);

const onAdd = () => emit('add');
const onRemove = (index) => emit('remove', index);

const updateItemField = (index, field, value) => {
    emit('update:item', { index, field, value });
};

const formatTotal = (qty, price) => {
    const total = (Number(qty) || 0) * (Number(price) || 0);
    return total.toFixed(3);
};
</script>

<template>
    <!-- Add Item Row Selector -->
    <div class="flex flex-col sm:flex-row items-center gap-2">
        <div class="w-full flex-1">
            <SearchableSelect
                :model-value="selectedItem"
                :options="itemOptions"
                :placeholder="searchPlaceholder"
                @update:model-value="$emit('update:selectedItem', $event)"
            />
        </div>
        <button
            type="button"
            class="w-full sm:w-auto h-11 px-5 rounded-2xl btn-primary-theme text-xs font-black transition active:scale-95 cursor-pointer shadow-theme-primary shrink-0"
            @click="onAdd"
        >
            + {{ addLabel }}
        </button>
    </div>

    <!-- Desktop Table -->
    <div class="hidden md:block overflow-x-auto pt-2">
        <table class="w-full text-right text-xs">
            <thead>
                <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold">
                    <th class="pb-2.5">{{ $t('inventory.item_name') }}</th>
                    <th class="pb-2.5 w-28">{{ $t('common.quantity') }}</th>
                    <th class="pb-2.5 w-28">{{ priceLabel }}</th>
                    <th class="pb-2.5 font-mono">{{ $t('common.total') }}</th>
                    <th class="pb-2.5 text-center w-10">✕</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
                <tr v-for="(it, itIdx) in items" :key="it.item_id">
                    <td class="py-3 font-bold text-slate-900 dark:text-white font-tajawal">
                        {{ it.name }}
                        <span class="text-[10px] text-slate-400 dark:text-slate-500 mr-1">({{ it.unit }})</span>
                    </td>

                    <td class="py-3">
                        <input
                            :value="it.quantity"
                            type="number"
                            step="0.001"
                            min="0.001"
                            required
                            class="w-24 h-10 px-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs font-mono font-bold text-theme-primary focus:outline-none shadow-inner"
                            @input="updateItemField(itIdx, 'quantity', $event.target.value)"
                        >
                    </td>

                    <td class="py-3">
                        <input
                            :value="it[priceField]"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                            class="w-24 h-10 px-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs font-mono font-bold text-slate-900 dark:text-white focus:outline-none shadow-inner"
                            @input="updateItemField(itIdx, priceField, $event.target.value)"
                        >
                    </td>

                    <td class="py-3 font-mono font-black text-emerald-600 dark:text-emerald-400">
                        {{ formatTotal(it.quantity, it[priceField]) }} {{ $t('common.currency') }}
                    </td>

                    <td class="py-3 text-center">
                        <button
                            type="button"
                            class="w-9 h-9 rounded-xl bg-rose-500/15 hover:bg-rose-500/30 text-rose-600 dark:text-rose-400 flex items-center justify-center transition active:scale-90 cursor-pointer"
                            @click="onRemove(itIdx)"
                        >
                            ✕
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Mobile Cards -->
    <div class="md:hidden space-y-3 font-tajawal">
        <div
            v-for="(it, itIdx) in items"
            :key="it.item_id"
            class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800/80 space-y-3 shadow-xs"
        >
            <div class="flex items-start justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
                <div>
                    <div class="font-bold text-xs text-slate-900 dark:text-white">{{ it.name }}</div>
                    <div class="text-[10px] text-slate-400 font-mono">({{ it.unit }})</div>
                </div>
                <button
                    type="button"
                    class="w-9 h-9 rounded-xl bg-rose-500/15 text-rose-500 dark:text-rose-400 flex items-center justify-center transition active:scale-90 cursor-pointer shrink-0"
                    @click="onRemove(itIdx)"
                >
                    ✕
                </button>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400">{{ $t('common.quantity') }}</label>
                    <input
                        :value="it.quantity"
                        type="number"
                        step="0.001"
                        min="0.001"
                        required
                        class="w-full h-10 px-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold text-theme-primary text-center focus:outline-none shadow-inner"
                        @input="updateItemField(itIdx, 'quantity', $event.target.value)"
                    >
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400">{{ priceLabel }}</label>
                    <input
                        :value="it[priceField]"
                        type="number"
                        step="0.01"
                        min="0"
                        required
                        class="w-full h-10 px-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold text-slate-900 dark:text-white text-center focus:outline-none shadow-inner"
                        @input="updateItemField(itIdx, priceField, $event.target.value)"
                    >
                </div>
            </div>

            <div class="flex items-center justify-between text-xs font-mono pt-2 border-t border-slate-200 dark:border-slate-800">
                <span class="text-slate-500 dark:text-slate-400 font-tajawal">{{ $t('common.total') }}:</span>
                <span class="font-black text-sm text-emerald-600 dark:text-emerald-400">
                    {{ formatTotal(it.quantity, it[priceField]) }} {{ $t('common.currency') }}
                </span>
            </div>
        </div>
    </div>

    <!-- Empty State -->
    <div
        v-if="items.length === 0"
        class="py-8 text-center text-slate-400 text-xs font-bold font-tajawal"
    >
        {{ emptyMessage }}
    </div>
</template>
