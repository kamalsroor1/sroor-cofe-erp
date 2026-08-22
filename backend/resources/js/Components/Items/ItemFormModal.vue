<script setup>
import { ref, computed, onMounted } from 'vue';
import AppModal from '@/Components/Common/AppModal.vue';
import BaseInput from '@/Components/Form/BaseInput.vue';
import BaseNumberInput from '@/Components/Form/BaseNumberInput.vue';
import BaseSelect from '@/Components/Form/BaseSelect.vue';
import { Package } from 'lucide-vue-next';
import { trans } from '@/helpers/trans';
import api from '@/services/api';

const props = defineProps({
    show: { type: Boolean, required: true },
    editingItem: { type: Object, default: null },
    form: { type: Object, required: true },
});

defineEmits(['close', 'submit']);

const categoriesList = ref([]);
const loadCategories = async () => {
    try {
        const res = await api.get('/categories');
        categoriesList.value = res.data?.data || [];
    } catch (e) {
        console.error('Failed to load categories in ItemFormModal', e);
    }
};

onMounted(() => {
    loadCategories();
});

const categoryOptions = computed(() => {
    return categoriesList.value.map(c => ({
        value: c.name,
        label: `${c.icon || '☕'} ${c.name}`
    }));
});

const unitOptions = computed(() => [
    { value: 'كجم', label: `${trans('inventory.unit_weight_short')} (كجم)` },
    { value: 'جرام', label: trans('inventory.unit_gram') },
    { value: 'قطعة', label: trans('inventory.unit_piece_short') },
    { value: 'شيكارة', label: trans('inventory.unit_bag_box') },
]);
</script>

<template>
    <AppModal
        :show="show"
        :title="editingItem ? $t('inventory.edit_item') : $t('inventory.add_new_item')"
        :icon="Package"
        max-width="lg"
        @close="$emit('close')"
    >
        <form id="item-form" @submit.prevent="$emit('submit')" class="space-y-4">
            <!-- Name -->
            <BaseInput
                v-model="form.name"
                :label="$t('inventory.item_name')"
                :required="true"
                :placeholder="$t('inventory.item_name')"
                :error="form.errors?.name"
            />

            <!-- Code & Category -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <BaseInput
                    v-model="form.code"
                    :label="`${$t('inventory.item_code')} / ${$t('inventory.barcode')}`"
                    :placeholder="$t('inventory.barcode_placeholder')"
                    :error="form.errors?.code"
                    dir="ltr"
                />

                <BaseSelect
                    v-model="form.category"
                    :label="$t('inventory.category')"
                    :placeholder="$t('inventory.category_placeholder') || 'اختر الفئة أو التصنيف'"
                    :options="categoryOptions"
                    :error="form.errors?.category"
                />
            </div>

            <!-- Unit, Cost Price, Selling Price -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <BaseSelect
                    v-model="form.unit"
                    :label="$t('inventory.unit')"
                    :required="true"
                    :options="unitOptions"
                    :searchable="false"
                    :error="form.errors?.unit"
                />

                <BaseNumberInput
                    v-model="form.cost_price"
                    :label="$t('inventory.purchase_price')"
                    :required="true"
                    step="0.001"
                    :suffix="$t('common.currency')"
                    :error="form.errors?.cost_price"
                />

                <BaseNumberInput
                    v-model="form.selling_price"
                    :label="$t('inventory.retail_price')"
                    :required="true"
                    step="0.001"
                    :suffix="$t('common.currency')"
                    :error="form.errors?.selling_price"
                />
            </div>

            <!-- Min Stock Level -->
            <BaseNumberInput
                v-model="form.min_stock_level"
                :label="$t('inventory.min_stock_level')"
                step="1"
                min="0"
                :error="form.errors?.min_stock_level"
            />

            <!-- Initial Stock (Only on Create) -->
            <BaseNumberInput
                v-if="!editingItem"
                v-model="form.initial_stock"
                :label="$t('inventory.initial_stock_balance')"
                step="0.001"
                min="0"
                :error="form.errors?.initial_stock"
            />

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                <button
                    type="button"
                    @click="$emit('close')"
                    class="px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-bold text-slate-700 dark:text-slate-300 transition cursor-pointer"
                >
                    {{ $t('common.cancel') }}
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-6 py-2.5 rounded-xl bg-theme-primary text-slate-950 hover:bg-theme-hover font-black text-xs transition cursor-pointer shadow-lg shadow-theme-primary/20 disabled:opacity-50"
                >
                    {{ form.processing ? $t('common.saving') : (editingItem ? $t('common.save_changes') : $t('inventory.create_item_btn')) }}
                </button>
            </div>
        </form>
    </AppModal>
</template>