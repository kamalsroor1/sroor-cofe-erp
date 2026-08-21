<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import DatePicker from '@/Components/DatePicker.vue';
import PageHeader from '@/Components/Common/PageHeader.vue';
import InvoiceLineItemsTable from '@/Components/Common/InvoiceLineItemsTable.vue';
import InvoiceFinancialSummary from '@/Components/Common/InvoiceFinancialSummary.vue';
import { trans } from '@/helpers/trans';

const props = defineProps({
    customers: { type: Array, default: () => [] },
    suppliers: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
});

const form = useForm({
    return_type: 'sales_return',
    customer_id: props.customers[0]?.id || null,
    supplier_id: props.suppliers[0]?.id || null,
    return_date: new Date().toISOString().split('T')[0],
    refund_amount: '0.00',
    reason: '',
    items: [],
});

const customerOptions = computed(() =>
    props.customers.map(c => ({
        id: c.id,
        name: `${c.name} ${c.phone ? '(' + c.phone + ')' : ''}`,
    }))
);

const supplierOptions = computed(() =>
    props.suppliers.map(s => ({
        id: s.id,
        name: `${s.name} ${s.phone ? '(' + s.phone + ')' : ''}`,
    }))
);

const availableItemOptions = computed(() =>
    props.items.map(item => ({
        id: item.id,
        name: `${item.name} (${item.unit || ''}) - ${trans('inventory.selling_price') || 'سعر البيع'}: ${item.selling_price} | ${trans('inventory.cost_price') || 'التكلفة'}: ${item.cost_price}`,
    }))
);

const selectedItemToAdd = ref(null);

const addItemRow = () => {
    if (!selectedItemToAdd.value) return;
    const item = props.items.find(i => i.id === selectedItemToAdd.value);
    if (!item) return;

    if (form.items.some(it => it.item_id === item.id)) {
        alert(trans('returns.item_already_added') || 'هذا الصنف مضاف بالفعل بالمستند');
        return;
    }

    const defaultPrice = form.return_type === 'sales_return' ? Number(item.selling_price) : Number(item.cost_price);

    form.items.push({
        item_id: item.id,
        name: item.name,
        unit: item.unit || trans('inventory.unit_kg') || 'كجم',
        quantity: 1,
        unit_price: defaultPrice || 0,
    });

    selectedItemToAdd.value = null;
};

const removeItemRow = (index) => {
    form.items.splice(index, 1);
};

const updateItem = ({ index, field, value }) => {
    form.items[index][field] = Number(value);
};

const netTotal = computed(() =>
    form.items.reduce((sum, it) => sum + ((Number(it.quantity) || 0) * (Number(it.unit_price) || 0)), 0)
);

const submitReturn = () => {
    if (form.items.length === 0) {
        alert(trans('returns.add_at_least_one_item') || 'يرجى إضافة صنف واحد على الأقل للمرتجع');
        return;
    }
    form.post('/returns', { preserveScroll: true });
};
</script>

<template>
    <Head :title="$t('returns.create_title')" />

    <AppLayout>
        <div class="max-w-5xl mx-auto space-y-6 font-tajawal">
            <!-- Header -->
            <PageHeader
                :title="$t('returns.create_title')"
                :subtitle="$t('returns.create_subtitle')"
                back-href="/returns"
            />

            <form @submit.prevent="submitReturn" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left 2 Cols: Form Info & Items Table -->
                <div class="lg:col-span-2 space-y-5">
                    <!-- Return Type & Party -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xs space-y-4">
                        <h2 class="text-sm font-black text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2 flex items-center gap-2">
                            <span>🔄</span>
                            <span>{{ $t('returns.return_type') }} & {{ $t('returns.party_name') }}</span>
                        </h2>

                        <!-- Type Selector Pill -->
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                class="h-12 px-4 rounded-2xl border text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer active:scale-95 shadow-xs"
                                :class="form.return_type === 'sales_return' ? 'bg-rose-500 text-white font-black border-rose-400 shadow-md shadow-rose-500/20' : 'bg-slate-100 dark:bg-slate-950 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                                @click="form.return_type = 'sales_return'"
                            >
                                <span>↩️</span>
                                <span>{{ $t('returns.sales_return') }}</span>
                            </button>

                            <button
                                type="button"
                                class="h-12 px-4 rounded-2xl border text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer active:scale-95 shadow-xs"
                                :class="form.return_type === 'purchase_return' ? 'btn-primary-theme font-black shadow-theme-primary' : 'bg-slate-100 dark:bg-slate-950 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                                @click="form.return_type = 'purchase_return'"
                            >
                                <span>↪️</span>
                                <span>{{ $t('returns.purchase_return') }}</span>
                            </button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                            <div v-if="form.return_type === 'sales_return'" class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('returns.customer_from') }}</label>
                                <SearchableSelect
                                    v-model="form.customer_id"
                                    :options="customerOptions"
                                    :placeholder="$t('invoices.select_customer')"
                                />
                            </div>

                            <div v-else class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('returns.supplier_to') }}</label>
                                <SearchableSelect
                                    v-model="form.supplier_id"
                                    :options="supplierOptions"
                                    :placeholder="$t('suppliers.select_supplier') || 'اختر المورد...'"
                                />
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('returns.return_date') }} *</label>
                                <DatePicker v-model="form.return_date" :placeholder="$t('returns.return_date')" />
                            </div>

                            <div class="space-y-1.5 sm:col-span-2">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('returns.reason') }}</label>
                                <input
                                    v-model="form.reason"
                                    type="text"
                                    :placeholder="$t('returns.reason_placeholder')"
                                    class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-theme-primary focus:outline-none shadow-inner"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Items Selection -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xs space-y-4 font-tajawal">
                        <h2 class="text-sm font-black text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2 flex items-center gap-2">
                            <span>📦</span>
                            <span>{{ $t('returns.return_items') }}</span>
                        </h2>

                        <InvoiceLineItemsTable
                            :items="form.items"
                            :item-options="availableItemOptions"
                            :selected-item="selectedItemToAdd"
                            :price-label="$t('invoices.unit_price')"
                            price-field="unit_price"
                            :search-placeholder="$t('returns.select_item_to_add')"
                            :empty-message="$t('returns.empty_return_items')"
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
                        :title="$t('returns.summary_title')"
                        :net-total="netTotal"
                        :refund-amount="form.refund_amount"
                        :is-processing="form.processing"
                        :is-disabled="form.items.length === 0"
                        :submit-label="$t('returns.confirm_return_save')"
                        submit-icon="🔄"
                        @update:refund-amount="form.refund_amount = $event"
                    />
                </div>
            </form>
        </div>
    </AppLayout>
</template>