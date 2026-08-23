<template>
  <AppModal
    :show="show"
    :title="$t('treasury.open_shift')"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('submit')" class="space-y-4 font-tajawal">
      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
          {{ $t('treasury.opening_cash_prompt') }} <span class="text-rose-500">*</span>
        </label>
        <input
          :value="form.opening_cash_balance"
          @input="$emit('update:field', 'opening_cash_balance', $event.target.value)"
          type="number"
          step="0.001"
          required
          autofocus
          class="w-full h-11 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-lg font-bold text-theme-primary font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
          placeholder="0.00"
        >
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
          {{ $t('treasury.open_shift_notes_prompt') }}
        </label>
        <input
          :value="form.notes"
          @input="$emit('update:field', 'notes', $event.target.value)"
          type="text"
          class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
          :placeholder="$t('treasury.open_shift_notes_placeholder')"
        >
      </div>

      <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
        <button
          type="button"
          @click="$emit('close')"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold cursor-pointer"
        >
          {{ $t('common.cancel') }}
        </button>

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
import BaseButton from '../Common/BaseButton.vue';

defineProps({
  show: { type: Boolean, default: false },
  form: { type: Object, default: () => ({}) },
  submitting: { type: Boolean, default: false },
});

defineEmits(['close', 'submit', 'update:field']);
</script>
