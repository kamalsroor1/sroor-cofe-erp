<template>
  <AppModal
    :show="show"
    :title="editingItem ? $t('inventory.edit_item') : $t('inventory.add_new_item')"
    :icon="Package"
    max-width="3xl"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('submit')" class="space-y-4 font-tajawal">
      <!-- Row 1: Name & Code/Barcode -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <BaseInput
          :model-value="form.name"
          @update:model-value="updateForm('name', $event)"
          :label="$t('inventory.item_name')"
          :required="true"
          :placeholder="$t('inventory.item_name_placeholder')"
          :error="errors?.name"
        />

        <BaseInput
          :model-value="form.code"
          @update:model-value="updateForm('code', $event)"
          :label="`${$t('inventory.code')} (${$t('inventory.barcode')})`"
          :placeholder="$t('inventory.auto_code_placeholder')"
          :error="errors?.code"
          dir="ltr"
          input-class="font-mono text-xs"
        />
      </div>

      <!-- Row 2: Category & Unit -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <BaseSelect
          :model-value="form.category"
          @update:model-value="updateForm('category', $event)"
          :label="$t('inventory.category')"
          :placeholder="$t('inventory.category_placeholder')"
          :options="categoryOptions"
          :error="errors?.category"
        />

        <BaseSelect
          :model-value="form.unit"
          @update:model-value="updateForm('unit', $event)"
          :label="$t('inventory.unit')"
          :required="true"
          :options="unitOptions"
          :searchable="false"
          :error="errors?.unit"
        />
      </div>

      <!-- Row 3: Pricing Grid (Cost, Retail, Min Selling/Wholesale, Min Stock Level) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <BaseNumberInput
          :model-value="form.cost_price"
          @update:model-value="updateForm('cost_price', $event)"
          :label="$t('inventory.cost_price')"
          :required="true"
          :min="0"
          :step="0.001"
          :error="errors?.cost_price"
        />

        <BaseNumberInput
          :model-value="form.selling_price"
          @update:model-value="updateForm('selling_price', $event)"
          :label="$t('inventory.selling_price')"
          :required="true"
          :min="0"
          :step="0.001"
          :error="errors?.selling_price"
        />

        <BaseNumberInput
          :model-value="form.min_selling_price"
          @update:model-value="updateForm('min_selling_price', $event)"
          :label="$t('inventory.min_selling_price')"
          :min="0"
          :step="0.001"
          :error="errors?.min_selling_price"
        />

        <BaseNumberInput
          :model-value="form.min_stock_level"
          @update:model-value="updateForm('min_stock_level', $event)"
          :label="$t('inventory.min_stock_level')"
          :min="0"
          :step="0.001"
          :error="errors?.min_stock_level"
        />
      </div>

      <!-- Row 4: Notes -->
      <BaseTextarea
        :model-value="form.notes"
        @update:model-value="updateForm('notes', $event)"
        :label="$t('common.notes')"
        :placeholder="$t('inventory.item_notes_placeholder')"
        :rows="2"
      />

      <!-- Modal Footer Actions -->
      <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
        <BaseButton type="button" variant="ghost" size="md" :label="$t('common.cancel')" @click="$emit('close')" />

        <BaseButton type="submit" variant="gradient" size="md" :loading="isSubmitting" :label="$t('common.save')" />
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import { computed } from 'vue';
import { Package } from 'lucide-vue-next';
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseNumberInput from '../Form/BaseNumberInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseTextarea from '../Form/BaseTextarea.vue';
import BaseButton from '../Common/BaseButton.vue';

const props = defineProps({
  show: { type: Boolean, default: false },
  editingItem: { type: Object, default: null },
  form: { type: Object, required: true },
  categories: { type: Array, default: () => [] },
  // Unit names or { value, label } options (useUnits().unitOptionsFor) — the tenant list, never a built-in one.
  units: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
  isSubmitting: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit', 'update:form']);

// Never mutate the prop: emit a patched shallow copy and let the parent apply it.
const updateForm = (key, value) => emit('update:form', { ...props.form, [key]: value });

const categoryOptions = computed(() => {
  return props.categories.map((c) => ({
    value: typeof c === 'object' ? c.name : c,
    label: typeof c === 'object' ? `${c.icon || '☕'} ${c.name}` : c,
  }));
});

const unitOptions = computed(() =>
  props.units.map((u) => (u && typeof u === 'object' ? { value: u.value, label: u.label } : { value: u, label: u }))
);
</script>
