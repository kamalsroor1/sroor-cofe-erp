<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Common/PageHeader.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import DatePicker from '@/Components/DatePicker.vue';
import { useMoney } from '@/Composables/useMoney';
import { trans } from '@/helpers/trans';

const props = defineProps({
    invoice: { type: Object, required: true },
    customers: { type: Array, default: () => [] },
    items_catalog: { type: Array, default: () => [] },
});

const { formatMoney } = useMoney();

const form = useForm({
    customer_id: props.invoice.customer_id,
    invoice_date: props.invoice.invoice_date,
    payment_type: props.invoice.payment_type,
    discount_type: props.invoice.discount_type || 'fixed',
    discount_value: props.invoice.discount_value || 0,
    paid_amount: props.invoice.paid_amount || 0,
    shipping_cost: 0,
    notes: props.invoice.notes || '',
    items: props.invoice.items.map(item => ({ ...item })),
    additional_expenses: props.invoice.additional_expenses ? [...props.invoice.additional_expenses] : [],
});

const customerOptions = computed(() => props.customers.map(c => ({
    id: c.id,
    name: `${c.name} (${c.phone || '—'})`
})));

const itemCatalogOptions = computed(() => props.items_catalog.map(i => ({
    id: i.id,
    name: `${i.name} [${i.code || '—'}] - ${i.selling_price} (${trans('inventory.current_stock') || 'مخزون'}: ${i.current_stock})`,
    raw: i,
})));

const selectedItemId = ref(null);

const addItemToInvoice = () => {
    if (!selectedItemId.value) return;
    const selectedOption = itemCatalogOptions.value.find(o => o.id === selectedItemId.value);
    if (!selectedOption) return;

    const rawItem = selectedOption.raw;
    const existing = form.items.find(i => i.item_id === rawItem.id);
    if (existing) {
        existing.quantity += 1;
        calculateLineTotal(existing);
    } else {
        const newLine = {
            item_id: rawItem.id,
            name: rawItem.name,
            code: rawItem.code,
            unit: rawItem.unit,
            current_stock: rawItem.current_stock,
            quantity: 1,
            unit_price: rawItem.selling_price,
            discount_amount: 0,
            total_price: rawItem.selling_price,
        };
        form.items.push(newLine);
    }
    selectedItemId.value = null;
};

const calculateLineTotal = (item) => {
    const gross = (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);
    const disc = parseFloat(item.discount_amount) || 0;
    item.total_price = Math.max(0, gross - disc);
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

// Expenses
const addExpenseRow = () => {
    form.additional_expenses.push({ title: '', amount: 0 });
};

const removeExpenseRow = (idx) => {
    form.additional_expenses.splice(idx, 1);
};

// Computed Totals
const subtotal = computed(() => {
    return form.items.reduce((sum, item) => sum + (parseFloat(item.total_price) || 0), 0);
});

const discountTotal = computed(() => {
    if (form.discount_type === 'percentage') {
        return (subtotal.value * (parseFloat(form.discount_value) || 0)) / 100;
    }
    return parseFloat(form.discount_value) || 0;
});

const expensesTotal = computed(() => {
    return form.additional_expenses.reduce((sum, exp) => sum + (parseFloat(exp.amount) || 0), 0);
});

const netTotal = computed(() => {
    return Math.max(0, subtotal.value - discountTotal.value + expensesTotal.value + (parseFloat(form.shipping_cost) || 0));
});

const remainingAmount = computed(() => {
    return Math.max(0, netTotal.value - (parseFloat(form.paid_amount) || 0));
});

const submitUpdate = () => {
    form.put(`/invoices/${props.invoice.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`${$t('invoices.edit_invoice')}: ${invoice.invoice_number}`" />

    <AppLayout>
        <div class="space-y-6 font-tajawal">
            <!-- Header -->
            <PageHeader
                :title="`${$t('invoices.edit_invoice')}: #${invoice.invoice_number}`"
                :subtitle="$t('invoices.subtitle')"
                :back-href="`/invoices/${invoice.id}`"
            />

            <form @submit.prevent="submitUpdate" class="space-y-6">
                <!-- Top Details Grid -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xs grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('invoices.customer') }} *</label>
                        <SearchableSelect
                            v-model="form.customer_id"
                            :options="customerOptions"
                            :placeholder="$t('invoices.select_customer')"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('common.date') }} *</label>
                        <DatePicker v-model="form.invoice_date" :placeholder="$t('common.date')" />
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('invoices.payment_type') }} *</label>
                        <select
                            v-model="form.payment_type"
                            class="w-full h-11 px-3.5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-amber-500 focus:outline-none shadow-inner"
                        >
                            <option value="cash">{{ $t('invoices.payment_cash') }} 💵</option>
                            <option value="credit">{{ $t('invoices.payment_credit') }} ⏳</option>
                            <option value="partial">{{ $t('invoices.payment_partial') }} ⚖️</option>
                        </select>
                    </div>
                </div>

                <!-- Add Items Section -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xs space-y-4 font-tajawal">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-800 pb-3">
                        <h2 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <span>📦</span>
                            <span>{{ $t('pos.cart_items') }}</span>
                        </h2>

                        <div class="w-full sm:w-96 flex items-center gap-2">
                            <SearchableSelect
                                v-model="selectedItemId"
                                :options="itemCatalogOptions"
                                :placeholder="$t('inventory.choose_item')"
                            />
                            <button
                                @click="addItemToInvoice"
                                type="button"
                                class="h-11 px-5 rounded-2xl btn-primary-theme text-xs font-black shrink-0 transition active:scale-95 cursor-pointer shadow-theme-primary"
                            >
                                + {{ $t('common.add') }}
                            </button>
                        </div>
                    </div>

                    <!-- Items Desktop Table (Hidden on Mobile) -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-right text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold">
                                    <th class="pb-3">{{ $t('inventory.item_name') }}</th>
                                    <th class="pb-3 w-28">{{ $t('common.quantity') }}</th>
                                    <th class="pb-3 w-28">{{ $t('invoices.unit_price') }}</th>
                                    <th class="pb-3 w-28">{{ $t('invoices.discount') }}</th>
                                    <th class="pb-3 w-28 font-mono">{{ $t('common.total') }}</th>
                                    <th class="pb-3 text-center w-12">{{ $t('common.delete') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
                                <tr v-for="(item, idx) in form.items" :key="idx" class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                                    <td class="py-3 font-tajawal">
                                        <div class="font-bold text-slate-900 dark:text-white">{{ item.name }}</div>
                                        <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">{{ item.code || '—' }} ({{ item.unit }})</div>
                                    </td>

                                    <td class="py-3">
                                        <input
                                            v-model.number="item.quantity"
                                            @input="calculateLineTotal(item)"
                                            type="number"
                                            step="0.001"
                                            min="0.001"
                                            class="w-full h-10 px-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs font-mono font-bold text-slate-900 dark:text-white text-center focus:border-amber-500 focus:outline-none shadow-inner"
                                        >
                                    </td>

                                    <td class="py-3">
                                        <input
                                            v-model.number="item.unit_price"
                                            @input="calculateLineTotal(item)"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-full h-10 px-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs font-mono font-bold text-slate-900 dark:text-white text-center focus:border-amber-500 focus:outline-none shadow-inner"
                                        >
                                    </td>

                                    <td class="py-3">
                                        <input
                                            v-model.number="item.discount_amount"
                                            @input="calculateLineTotal(item)"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-full h-10 px-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs font-mono text-rose-600 dark:text-rose-400 font-bold text-center focus:border-amber-500 focus:outline-none shadow-inner"
                                        >
                                    </td>

                                    <td class="py-3 font-mono font-black text-theme-primary">
                                        {{ formatMoney(item.total_price) }} {{ $t('common.currency') }}
                                    </td>

                                    <td class="py-3 text-center">
                                        <button
                                            @click="removeItem(idx)"
                                            type="button"
                                            class="w-9 h-9 rounded-xl bg-rose-500/15 hover:bg-rose-500/30 text-rose-600 dark:text-rose-400 flex items-center justify-center transition active:scale-90 cursor-pointer"
                                        >
                                            🗑️
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Items Mobile Cards (Visible on Small Screens) -->
                    <div class="md:hidden space-y-3 font-tajawal">
                        <div
                            v-for="(item, idx) in form.items"
                            :key="idx"
                            class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 space-y-3 shadow-xs"
                        >
                            <!-- Top: Name + Delete -->
                            <div class="flex items-start justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-2.5">
                                <div>
                                    <div class="font-bold text-sm text-slate-900 dark:text-white">{{ item.name }}</div>
                                    <div class="text-[10px] text-slate-500 font-mono">{{ item.code || '—' }} ({{ item.unit }})</div>
                                </div>
                                <button
                                    @click="removeItem(idx)"
                                    type="button"
                                    class="w-9 h-9 rounded-xl bg-rose-500/15 text-rose-600 dark:text-rose-400 flex items-center justify-center transition active:scale-90 cursor-pointer shrink-0"
                                >
                                    🗑️
                                </button>
                            </div>

                            <!-- Inputs Grid -->
                            <div class="grid grid-cols-3 gap-2">
                                <div class="space-y-1">
                                    <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400">{{ $t('common.quantity') }}</label>
                                    <input
                                        v-model.number="item.quantity"
                                        @input="calculateLineTotal(item)"
                                        type="number"
                                        step="0.001"
                                        min="0.001"
                                        class="w-full h-10 px-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold text-slate-900 dark:text-white text-center focus:outline-none shadow-inner"
                                    >
                                </div>

                                <div class="space-y-1">
                                    <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400">{{ $t('invoices.unit_price') }}</label>
                                    <input
                                        v-model.number="item.unit_price"
                                        @input="calculateLineTotal(item)"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-full h-10 px-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold text-slate-900 dark:text-white text-center focus:outline-none shadow-inner"
                                    >
                                </div>

                                <div class="space-y-1">
                                    <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400">{{ $t('invoices.discount') }}</label>
                                    <input
                                        v-model.number="item.discount_amount"
                                        @input="calculateLineTotal(item)"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-full h-10 px-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold text-rose-600 dark:text-rose-400 text-center focus:outline-none shadow-inner"
                                    >
                                </div>
                            </div>

                            <!-- Line Total -->
                            <div class="flex items-center justify-between text-xs font-mono pt-2 border-t border-slate-200 dark:border-slate-800">
                                <span class="text-slate-500 dark:text-slate-400 font-tajawal">{{ $t('common.total') }}:</span>
                                <span class="font-black text-sm text-theme-primary">{{ formatMoney(item.total_price) }} {{ $t('common.currency') }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="form.items.length === 0" class="py-10 text-center text-slate-400 text-xs font-bold font-tajawal">
                        ⚠️ {{ $t('pos.empty_cart') }}
                    </div>
                </div>

                <!-- Financial Summary & Actions -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 font-tajawal">
                    <!-- Left: Notes & Discounts -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xs space-y-4">
                        <h2 class="text-xs font-black text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2 flex items-center gap-2">
                            <span>🏷️</span>
                            <span>{{ $t('invoices.discount') }} & {{ $t('invoices.shipping') }}</span>
                        </h2>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-[11px] font-bold text-slate-700 dark:text-slate-300">{{ $t('invoices.discount') }}</label>
                                <select
                                    v-model="form.discount_type"
                                    class="w-full h-11 px-3 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-amber-500 focus:outline-none shadow-inner"
                                >
                                    <option value="fixed">{{ $t('common.fixed_amount') || 'مبلغ ثابت' }}</option>
                                    <option value="percentage">{{ $t('common.percentage') || 'نسبة مئوية (%)' }}</option>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <label class="text-[11px] font-bold text-slate-700 dark:text-slate-300">{{ $t('invoices.discount') }}</label>
                                <input
                                    v-model.number="form.discount_value"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full h-11 px-3 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm font-mono font-bold text-rose-600 dark:text-rose-400 focus:border-amber-500 focus:outline-none shadow-inner"
                                >
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-[11px] font-bold text-slate-700 dark:text-slate-300">{{ $t('invoices.notes') }}</label>
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                :placeholder="$t('invoices.notes')"
                                class="w-full p-3 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-amber-500 focus:outline-none shadow-inner"
                            ></textarea>
                        </div>
                    </div>

                    <!-- Right: Calculations & Save -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xs space-y-4">
                        <h2 class="text-xs font-black text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2 flex items-center gap-2">
                            <span>💰</span>
                            <span>{{ $t('invoices.financial_summary') }}</span>
                        </h2>

                        <div class="space-y-2 text-xs font-mono">
                            <div class="flex justify-between text-slate-500 dark:text-slate-400 font-tajawal">
                                <span>{{ $t('invoices.subtotal') }}:</span>
                                <span class="font-mono text-slate-900 dark:text-white font-bold">{{ formatMoney(subtotal) }} {{ $t('common.currency') }}</span>
                            </div>

                            <div class="flex justify-between text-slate-500 dark:text-slate-400 font-tajawal">
                                <span>{{ $t('invoices.discount') }}:</span>
                                <span class="font-mono text-rose-600 dark:text-rose-400 font-bold">- {{ formatMoney(discountTotal) }} {{ $t('common.currency') }}</span>
                            </div>

                            <div class="flex justify-between pt-2 border-t border-slate-200 dark:border-slate-800 text-sm font-black text-slate-900 dark:text-white font-tajawal">
                                <span>{{ $t('invoices.net_total') }}:</span>
                                <span class="font-mono text-theme-primary text-base">{{ formatMoney(netTotal) }} {{ $t('common.currency') }}</span>
                            </div>

                            <div v-if="form.payment_type !== 'credit'" class="pt-2">
                                <label class="text-[11px] font-bold text-slate-700 dark:text-slate-300 block mb-1 font-tajawal">{{ $t('invoices.paid') }} ({{ $t('common.currency') }})</label>
                                <input
                                    v-model.number="form.paid_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm font-mono font-bold text-emerald-600 dark:text-emerald-400 focus:border-amber-500 focus:outline-none shadow-inner"
                                >
                            </div>

                            <div class="flex justify-between text-slate-500 dark:text-slate-400 pt-1 font-tajawal">
                                <span>{{ $t('invoices.remaining') }}:</span>
                                <span class="font-mono text-rose-600 dark:text-rose-400 font-bold">{{ formatMoney(remainingAmount) }} {{ $t('common.currency') }}</span>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-3">
                            <Link
                                :href="`/invoices/${invoice.id}`"
                                class="h-11 px-5 rounded-2xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold transition flex items-center justify-center active:scale-95 shadow-xs"
                            >
                                {{ $t('common.cancel') }}
                            </Link>

                            <button
                                type="submit"
                                :disabled="form.processing || form.items.length === 0"
                                class="h-11 px-6 rounded-2xl btn-primary-theme font-black text-xs transition transform active:scale-95 cursor-pointer disabled:opacity-50 shadow-theme-primary"
                            >
                                {{ form.processing ? $t('common.save') + '...' : $t('common.save') + ' 💾' }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>