<template>
  <AppModal
    :show="show"
    :title="$t('super.account_status_and_plan_modal_title')"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('submit')" class="space-y-4 text-xs font-tajawal">
      <div>
        <BaseSelect
          :model-value="statusForm.status"
          @update:model-value="$emit('update:field', 'status', $event)"
          :options="statusOptions"
          :label="$t('super.account_status_label')"
          :searchable="false"
          required
        />
      </div>

      <div>
        <BaseInput
          :model-value="statusForm.extend_days"
          @update:model-value="$emit('update:field', 'extend_days', Number($event))"
          :label="$t('super.extend_subscription_label')"
          :placeholder="$t('super.extend_days_placeholder')"
          type="number"
          min="0"
          class="font-mono"
        />
      </div>

      <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
        <BaseButton
          type="button"
          variant="secondary"
          size="md"
          @click="$emit('close')"
        >
          {{ $t('common.cancel') }}
        </BaseButton>

        <BaseButton
          type="submit"
          variant="primary"
          size="md"
          :loading="isSubmitting"
        >
          {{ isSubmitting ? $t('common.loading') : $t('common.save') }}
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseButton from '../Common/BaseButton.vue';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();

defineProps({
  show: { type: Boolean, default: false },
  statusForm: { type: Object, default: () => ({}) },
  isSubmitting: { type: Boolean, default: false },
});

defineEmits(['close', 'submit', 'update:field']);

const statusOptions = [
  { value: 'active', label: t('super.status_active') },
  { value: 'suspended', label: t('super.status_suspended') },
  { value: 'pending', label: t('super.status_pending') },
  { value: 'cancelled', label: t('super.status_cancelled') },
];
</script>
