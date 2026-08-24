<template>
  <AppModal
    :show="show"
    :title="$t('super.status_and_plan_modal_title')"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('submit')" class="space-y-4 font-tajawal text-xs">
      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ $t('super.account_status_label') }}</label>
        <select
          :value="form.status"
          @change="$emit('update:field', 'status', $event.target.value)"
          class="w-full h-11 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-bold focus:ring-2 focus:ring-purple-500"
        >
          <option value="active">{{ $t('super.status_active_opt') }}</option>
          <option value="trial">{{ $t('super.status_trial_opt') }}</option>
          <option value="suspended">{{ $t('super.status_suspended_opt') }}</option>
          <option value="expired">{{ $t('super.status_expired_opt') }}</option>
        </select>
      </div>

      <div>
        <BaseInput
          :model-value="form.extend_days"
          @update:model-value="$emit('update:field', 'extend_days', Number($event))"
          :label="$t('super.extend_sub_days_label')"
          type="number"
          min="0"
          class="font-mono"
          placeholder="30"
        />
      </div>

      <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
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
          :loading="isUpdatingStatus"
          class="bg-gradient-to-r from-purple-500 to-indigo-600 text-white font-black shadow-md"
        >
          {{ isUpdatingStatus ? $t('common.saving') : $t('common.save') }}
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseButton from '../Common/BaseButton.vue';

defineProps({
  show: { type: Boolean, default: false },
  form: { type: Object, default: () => ({}) },
  isUpdatingStatus: { type: Boolean, default: false },
});

defineEmits(['close', 'submit', 'update:field']);
</script>
