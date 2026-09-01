<template>
  <AppModal
    :show="show"
    :title="$t('invoices.cancel_modal_title')"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('confirm')" class="space-y-4 font-tajawal">
      <div class="p-3.5 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-xs">
        <p class="font-bold">{{ $t('invoices.cancel_modal_desc', { number: invoice?.invoice_number }) }}</p>
      </div>

      <BaseTextarea
        :model-value="reason"
        @update:model-value="$emit('update:reason', $event)"
        :label="$t('invoices.cancel_reason_label')"
        :placeholder="$t('invoices.cancel_reason_placeholder')"
        :rows="3"
        required
      />

      <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
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
          variant="danger"
          size="md"
          :loading="loading"
          class="font-black shadow-lg shadow-rose-500/20"
        >
          {{ $t('invoices.confirm_cancel_btn') }}
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import AppModal from '../../Common/AppModal.vue';
import BaseTextarea from '../../Form/BaseTextarea.vue';
import BaseButton from '../../Common/BaseButton.vue';

defineProps({
  show: { type: Boolean, default: false },
  invoice: { type: Object, default: null },
  reason: { type: String, default: '' },
  loading: { type: Boolean, default: false },
});

defineEmits(['close', 'confirm', 'update:reason']);
</script>
