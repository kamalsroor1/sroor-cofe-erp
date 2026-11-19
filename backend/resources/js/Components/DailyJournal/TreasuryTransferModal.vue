<template>
  <AppModal :show="show" :title="$t('treasury.transfer_modal_title')" @close="$emit('close')">
    <form @submit.prevent="$emit('submit')" class="space-y-4 font-tajawal" data-testid="treasury-transfer-form">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <BaseSelect
          :model-value="form.from_method"
          @update:model-value="$emit('update:field', 'from_method', $event)"
          :options="methodOptions"
          :label="$t('treasury.from_account')"
          :searchable="false"
          required
        />
        <BaseSelect
          :model-value="form.to_method"
          @update:model-value="$emit('update:field', 'to_method', $event)"
          :options="methodOptions"
          :label="$t('treasury.to_account')"
          :searchable="false"
          required
        />
      </div>

      <p
        v-if="sameAccount"
        class="px-3 py-2 rounded-xl text-xs font-bold bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400"
        role="alert"
      >
        {{ $t('treasury.same_account_error') }}
      </p>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <BaseNumberInput
          :model-value="form.amount"
          @update:model-value="$emit('update:field', 'amount', $event)"
          :label="$t('treasury.transfer_amount')"
          :decimals="3"
          class="font-mono font-bold text-theme-primary"
          required
        />
        <BaseNumberInput
          :model-value="form.transfer_fee"
          @update:model-value="$emit('update:field', 'transfer_fee', $event)"
          :label="$t('treasury.transfer_fee')"
          :decimals="3"
          class="font-mono"
        />
      </div>

      <BaseInput
        :model-value="form.notes"
        @update:model-value="$emit('update:field', 'notes', $event)"
        :label="$t('treasury.transfer_notes')"
        :placeholder="$t('treasury.transfer_notes_placeholder')"
      />

      <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
        <BaseButton type="button" variant="secondary" size="md" @click="$emit('close')">
          {{ $t('common.cancel') }}
        </BaseButton>
        <BaseButton
          type="submit"
          variant="primary"
          size="md"
          :loading="submitting"
          :disabled="sameAccount"
          class="font-black shadow-theme-primary shadow-lg"
        >
          {{ $t('treasury.confirm_transfer') }}
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import { computed } from 'vue';
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseNumberInput from '../Form/BaseNumberInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseButton from '../Common/BaseButton.vue';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();

const props = defineProps({
  show: { type: Boolean, default: false },
  form: { type: Object, default: () => ({}) },
  submitting: { type: Boolean, default: false },
});

defineEmits(['close', 'submit', 'update:field']);

const sameAccount = computed(() => !!props.form.from_method && props.form.from_method === props.form.to_method);

const methodOptions = computed(() => [
  { value: 'cash', label: t('treasury.cash_drawer') },
  { value: 'instapay', label: t('contacts.instapay') },
  { value: 'e_wallet', label: t('contacts.wallet') },
  { value: 'visa', label: t('treasury.method_visa') },
  { value: 'bank_transfer', label: t('contacts.bank_transfer') },
]);
</script>
