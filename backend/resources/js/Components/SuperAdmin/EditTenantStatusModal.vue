<template>
  <AppModal
    :show="show"
    :title="$t('super.edit_tenant_status_title', { name: selectedTenant?.name || '' })"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('submit')" class="space-y-3.5 text-xs font-tajawal">
      <div>
        <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">{{ $t('super.new_status_label') }}</label>
        <select
          :value="form.status"
          @change="$emit('update:field', 'status', $event.target.value)"
          class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-purple-500 focus:outline-none"
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
          :label="$t('super.extend_days_label')"
          type="number"
          min="0"
          max="365"
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
          class="shadow-lg shadow-theme-primary font-black"
        >
          {{ isSubmitting ? $t('common.loading') : $t('super.save_status_btn') }}
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
  selectedTenant: { type: Object, default: null },
  form: { type: Object, default: () => ({}) },
  isSubmitting: { type: Boolean, default: false },
});

defineEmits(['close', 'submit', 'update:field']);
</script>
