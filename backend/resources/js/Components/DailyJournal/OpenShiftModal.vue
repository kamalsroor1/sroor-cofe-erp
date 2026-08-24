<template>
  <AppModal
    :show="show"
    :title="$t('treasury.open_shift')"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('submit')" class="space-y-4 font-tajawal">
      <div>
        <BaseNumberInput
          :model-value="form.opening_cash_balance"
          @update:model-value="$emit('update:field', 'opening_cash_balance', $event)"
          :label="$t('treasury.opening_cash_prompt')"
          :decimals="3"
          class="font-mono text-theme-primary font-bold text-lg"
          required
        />
      </div>

      <div>
        <BaseInput
          :model-value="form.notes"
          @update:model-value="$emit('update:field', 'notes', $event)"
          :label="$t('treasury.open_shift_notes_prompt')"
          :placeholder="$t('treasury.open_shift_notes_placeholder')"
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
          :loading="submitting"
          class="font-black shadow-theme-primary shadow-lg"
        >
          {{ $t('treasury.confirm_open_shift_btn') }}
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseNumberInput from '../Form/BaseNumberInput.vue';
import BaseButton from '../Common/BaseButton.vue';

defineProps({
  show: { type: Boolean, default: false },
  form: { type: Object, default: () => ({}) },
  submitting: { type: Boolean, default: false },
});

defineEmits(['close', 'submit', 'update:field']);
</script>
