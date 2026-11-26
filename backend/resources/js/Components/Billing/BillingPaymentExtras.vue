<script setup>
import { ref, watch } from 'vue';
import { ChevronDown } from 'lucide-vue-next';
import BaseInput from '@/Components/Form/BaseInput.vue';
import { nowLocalInput } from '@/helpers/billing';

const props = defineProps({
  errors: { type: Object, default: () => ({}) },
  isPartial: { type: Boolean, default: false },
  remainingLabel: { type: String, default: '' },
});

const amount = defineModel('amount', { type: [String, Number], default: '' });
const paidAt = defineModel('paidAt', { type: String, default: '' });
const reference = defineModel('reference', { type: String, default: '' });

const open = ref(false);

// A server or local error on a hidden field must not stay invisible.
watch(
  () => [props.errors.amount, props.errors.paid_at],
  ([a, p]) => {
    if (a || p) open.value = true;
  }
);
</script>

<template>
  <div class="space-y-3">
    <!-- Slot of the optional transfer reference (card #241). The API still requires one, so it stays visible. -->
    <BaseInput
      v-model="reference"
      :label="$t('subscription.billing.pay.payer_reference')"
      maxlength="100"
      :error="errors.payer_reference"
      required
    />

    <div class="rounded-xl border border-slate-200 dark:border-slate-700">
      <button
        type="button"
        class="w-full min-h-11 px-3 flex items-center justify-between gap-2 text-sm font-bold text-slate-700 dark:text-slate-200 cursor-pointer"
        :aria-expanded="open"
        @click="open = !open"
      >
        <span class="text-start">{{ $t('subscription.billing.pay.extras_toggle') }}</span>
        <ChevronDown class="w-4 h-4 shrink-0 transition" :class="open ? 'rotate-180' : ''" />
      </button>
      <div v-if="open" class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 pt-0">
        <BaseInput
          v-model="amount"
          :label="$t('subscription.billing.pay.amount')"
          inputmode="decimal"
          dir="ltr"
          :error="errors.amount"
          required
        />
        <BaseInput
          v-model="paidAt"
          type="datetime-local"
          :max="nowLocalInput()"
          :label="$t('subscription.billing.pay.transfer_time_optional')"
          :error="errors.paid_at"
        />
        <p v-if="isPartial" class="sm:col-span-2 text-xs font-bold text-amber-700 dark:text-amber-400">
          {{ $t('subscription.billing.pay.partial_warning', { remaining: remainingLabel }) }}
        </p>
      </div>
    </div>
  </div>
</template>
