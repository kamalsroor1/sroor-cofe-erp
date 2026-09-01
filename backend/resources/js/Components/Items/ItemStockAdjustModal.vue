<template>
  <AppModal
    :show="show"
    :title="`${$t('inventory.adjust_stock')}: ${targetItem?.name || ''}`"
    :icon="Sliders"
    max-width="lg"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('submit')" class="space-y-4 font-tajawal">
      <!-- Current Stock Live Info Tile -->
      <div class="p-3.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl flex items-center justify-between">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('inventory.current_stock') }}:</span>
        <span class="text-base font-black text-theme-primary font-mono">
          {{ formatQty(targetItem?.current_stock || 0) }} {{ targetItem?.unit || '' }}
        </span>
      </div>

      <!-- Movement Type Select -->
      <BaseSelect
        v-model="adjustForm.movement_type"
        :label="$t('inventory.movement_type')"
        :required="true"
        :options="movementTypeOptions"
        :searchable="false"
      />

      <!-- Quantity Input -->
      <BaseNumberInput
        v-model="adjustForm.quantity"
        :label="$t('inventory.quantity')"
        :required="true"
        :min="0.001"
        :step="0.001"
        placeholder="0.000"
      />

      <!-- Reason / Notes Input -->
      <BaseInput
        v-model="adjustForm.notes"
        :label="$t('inventory.adjust_reason_prompt')"
        :placeholder="$t('inventory.adjust_reason_placeholder')"
      />

      <!-- Modal Footer Actions -->
      <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
        <BaseButton
          type="button"
          variant="ghost"
          size="md"
          :label="$t('common.cancel')"
          @click="$emit('close')"
        />

        <BaseButton
          type="submit"
          variant="gradient"
          size="md"
          :loading="isSubmitting"
          :label="$t('inventory.confirm_stock_adjustment')"
        />
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import { computed } from 'vue';
import { Sliders } from 'lucide-vue-next';
import AppModal from '../Common/AppModal.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseNumberInput from '../Form/BaseNumberInput.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseButton from '../Common/BaseButton.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { trans } from '../../helpers/trans';

const { formatQty } = useFormatters();

defineProps({
  show: { type: Boolean, default: false },
  targetItem: { type: Object, default: null },
  adjustForm: { type: Object, required: true },
  isSubmitting: { type: Boolean, default: false },
});

defineEmits(['close', 'submit']);

const movementTypeOptions = computed(() => [
  { value: 'stock_adjustment_in', label: trans('inventory.movement_adj_in') },
  { value: 'stock_adjustment_out', label: trans('inventory.movement_adj_out') },
  { value: 'waste_out', label: trans('inventory.movement_waste') },
  { value: 'stock_deposit_in', label: trans('inventory.movement_deposit') },
]);
</script>
