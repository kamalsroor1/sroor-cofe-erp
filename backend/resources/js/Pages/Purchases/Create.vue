<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import DatePicker from '@/Components/DatePicker.vue';
import PageHeader from '@/Components/Common/PageHeader.vue';
import InvoiceLineItemsTable from '@/Components/Common/InvoiceLineItemsTable.vue';
import InvoiceFinancialSummary from '@/Components/Common/InvoiceFinancialSummary.vue';
import { useMoney } from '@/Composables/useMoney';
import { trans } from '@/helpers/trans';

const props = defineProps({
    suppliers: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
    prefill_items: { type: Array, default: () => [] },
});

const { formatMoney } = useMoney();

const initialItems = (props.prefill_items && props.prefill_items.length > 0)
    ? props.prefill_items.map(p => ({
        item_id: p.item_id,
        name: p.name,
        unit: p.unit || trans('inventory.unit_kg') || 'كجم',
        quantity: p.quantity || 10,
        unit_cost: Number(p.unit_cost) || 0,
    }))
    : [];

const form = useForm({
    supplier_id: props.suppliers[0]?.id || null,
    purchase_date: new Date().toISOString().split('T')[0],
    supplier_invoice_ref: '',
    paid_amount: '0.00',
    discount_amount: '0.00',
    notes: '',
    items: initialItems,
});

const availableItemOptions = computed(() =>
    props.items.map(item => ({
        id: item.id,
        name: `${item.name} (${trans('inventory.current_stock') || 'المتوفر'}: ${item.current_stock} ${item.unit || ''}) - ${trans('inventory.cost_price') || 'سعر التكلفة'}: ${item.cost_price}`,
    }))
);

const selectedItemToAdd = ref(null);

const addItemRow = () => {
    if (!selectedItemToAdd.value) return;
    const item = props.items.find(i => i.id === selectedItemToAdd.value);
    if (!item) return;

    if (form.items.some(it => it.item_id === item.id)) {
        alert(trans('purchases.item_already_added') || 'هذا الصنف مضاف بالفعل بالفاتورة');
        return;
    }

    form.items.push({
        item_id: item.id,
        name: item.name,
        unit: item.unit || trans('inventory.unit_kg') || 'كجم',
        quantity: 10,
        unit_cost: Number(item.cost_price) || 0,
    });

    selectedItemToAdd.value = null;
};

const removeItemRow = (index) => {
    form.items.splice(index, 1);
};

const updateItem = ({ index, field, value }) => {
    form.items[index][field] = Number(value);
};

const subtotal = computed(() =>
    form.items.reduce((sum, it) => sum + ((Number(it.quantity) || 0) * (Number(it.unit_cost) || 0)), 0)
);

const netTotal = computed(() => {
    const disc = Number(form.discount_amount) || 0;
    return Math.max(subtotal.value - disc, 0);
});

const remainingAmount = computed(() => {
    const paid = Number(form.paid_amount) || 0;
    return Math.max(netTotal.value - paid, 0);
});

const submitPurchase = () => {
    if (form.items.length === 0) {
        alert(trans('purchases.add_at_least_one') || 'يرجى إضافة صنف واحد على الأقل لفاتورة الشراء');
        return;
    }
    form.post('/purchases', { preserveScroll: true });
};
</script>

<template>
    <Head :title="$t('purchases.create_po_title')" />

    <AppLayout>
        <div class="max-w-5xl mx-auto space-y-6 font-tajawal">
            <!-- Header -->
            <PageHeader
                :title="$t('purchases.create_po_title')"
                :subtitle="$t('purchases.create_po_subtitle')"
                back-href="/purchases"
            />

            <form @submit.prevent="submitPurchase" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left 2 Cols: Form Info & Items Table -->
                <div class="lg:col-span-2 space-y-5">
                    <!-- Supplier & Invoice Details -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xs space-y-4">
                        <h2 class="text-sm font-black text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2.5 flex items-center gap-2">
                            <span>🏢</span>
                            <span>{{ $t('purchases.supplier') }} & {{ $t('purchases.purchase_date') }}</span>
                        </h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('purchases.supplier') }} *</label>
                                <SearchableSelect
                                    v-model="form.supplier_id"
                                    :options="suppliers"
                                    :placeholder="$t('purchases.select_supplier')"
                                />
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('purchases.purchase_date') }} *</label>
                                <DatePicker v-model="form.purchase_date" :placeholder="$t('purchases.purchase_date')" />
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('purchases.supplier_invoice_ref') }}</label>
                                <input
                                    v-model="form.supplier_invoice_ref"
                                    type="text"
                                    placeholder="INV-9908"
                                    class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white font-mono focus:border-amber-500 focus:outline-none shadow-inner"
                                >
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('invoices.notes') }}</label>
                                <input
                                    v-model="form.notes"
                                    type="text"
                                    :placeholder="$t('invoices.notes')"
                                    class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-amber-500 focus:outline-none shadow-inner"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Items Selection -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xs space-y-4">
                        <h2 class="text-sm font-black text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2.5 flex items-center gap-2">
                            <span>📦</span>
                            <span>{{ $t('purchases.items_to_supply') }}</span>
                        </h2>

                        <InvoiceLineItemsTable
                            :items="form.items"
                            :item-options="availableItemOptions"
                            :selected-item="selectedItemToAdd"
                            :price-label="$t('purchases.unit_cost')"
                            price-field="unit_cost"
                            :search-placeholder="$t('purchases.search_placeholder')"
                            :empty-message="$t('purchases.empty_items')"
                            :add-label="$t('common.add')"
                            @update:selected-item="selectedItemToAdd = $event"
                            @add="addItemRow"
                            @remove="removeItemRow"
                            @update:item="updateItem"
                        />
                    </div>
                </div>

                <!-- Right Col: Financial Summary -->
                <div>
                    <InvoiceFinancialSummary
                        :title="$t('purchases.payment_summary')"
                        :subtotal="subtotal"
                        :net-total="netTotal"
                        :discount-amount="form.discount_amount"
                        :paid-amount="form.paid_amount"
                        :remaining-amount="remainingAmount"
                        :is-processing="form.processing"
                        :is-disabled="form.items.length === 0"
                        :submit-label="$t('purchases.confirm_purchase')"
                        submit-icon="📥"
                        @update:discount-amount="form.discount_amount = $event"
                        @update:paid-amount="form.paid_amount = $event"
                    />
                </div>
            </form>
        </div>
    </AppLayout>
</template>
