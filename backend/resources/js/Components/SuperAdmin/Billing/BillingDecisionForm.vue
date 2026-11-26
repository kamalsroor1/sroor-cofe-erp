<script setup>
import { toRef } from 'vue';
import { Check, X } from 'lucide-vue-next';
import BaseCheckbox from '../../Form/BaseCheckbox.vue';
import BillingVerifyPreview from './BillingVerifyPreview.vue';
import BillingRejectDialog from './BillingRejectDialog.vue';
import { REVIEW_CHECKS } from '../../../helpers/billingReview';
import { toDatetimeLocal } from '../../../helpers/planEditor';
import { useVerifyDecision } from '../../../Composables/useVerifyDecision';

const props = defineProps({
  payment: { type: Object, required: true },
});

const emit = defineEmits(['decided']);

const {
  transferredAt,
  checks,
  timeError,
  busy,
  approvable,
  preview,
  rejectOpen,
  rejectReason,
  reasonLength,
  reasonValid,
  approve,
  openReject,
  reject,
} = useVerifyDecision(toRef(props, 'payment'), (id) => emit('decided', id));

const maxLocal = () => toDatetimeLocal(new Date());
</script>

<template>
  <form class="space-y-3" novalidate data-testid="verify-form" @submit.prevent="approve">
    <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('billing.console.verify.decision_title') }}</h3>

    <div class="space-y-1.5">
      <label for="verify-transferred-at" class="block text-sm font-black text-slate-800 dark:text-slate-100">
        {{ $t('billing.console.verify.transfer_time') }}
        <span class="ms-0.5 font-black text-rose-600 dark:text-rose-400" aria-hidden="true">*</span>
      </label>
      <input
        id="verify-transferred-at"
        v-model="transferredAt"
        type="datetime-local"
        required
        :max="maxLocal()"
        dir="ltr"
        class="min-h-12 w-full rounded-xl border bg-slate-50 px-3.5 text-base font-bold text-slate-900 outline-hidden focus:ring-2 dark:bg-slate-800 dark:text-slate-100"
        :class="
          timeError
            ? 'border-rose-500 focus:ring-rose-500/20'
            : 'border-slate-300 focus:border-theme-primary focus:ring-theme-primary/20 dark:border-slate-700'
        "
        :aria-invalid="timeError ? 'true' : 'false'"
        aria-describedby="verify-transferred-at-help"
      />
      <p
        v-if="timeError"
        id="verify-transferred-at-help"
        role="alert"
        class="text-xs font-bold text-rose-600 dark:text-rose-400"
      >
        {{ timeError }}
      </p>
      <p v-else id="verify-transferred-at-help" class="text-xs font-bold text-slate-500 dark:text-slate-400">
        {{ $t('billing.console.verify.transfer_time_help') }}
      </p>
    </div>

    <BillingVerifyPreview :preview="preview" :currency="payment.currency" />

    <fieldset class="space-y-0.5">
      <legend class="mb-1 text-xs font-black text-slate-600 dark:text-slate-300">
        {{ $t('billing.console.verify.checks_title') }}
      </legend>
      <BaseCheckbox
        v-for="key in REVIEW_CHECKS"
        :key="key"
        v-model="checks[key]"
        :label="$t(`billing.console.verify.check_${key}`)"
        :data-testid="`verify-check-${key}`"
      />
    </fieldset>

    <p v-if="!approvable && !busy" class="text-xs font-bold text-slate-500 dark:text-slate-400">
      {{ $t('billing.console.verify.approve_blocked') }}
    </p>

    <div
      class="grid grid-cols-1 gap-2 sm:grid-cols-2 max-lg:sticky max-lg:bottom-0 max-lg:z-10 max-lg:border-t max-lg:border-slate-200 max-lg:bg-white max-lg:py-2 dark:max-lg:border-slate-800 dark:max-lg:bg-slate-900"
    >
      <button
        type="submit"
        :disabled="!approvable"
        class="inline-flex min-h-12 cursor-pointer items-center justify-center gap-2 rounded-xl bg-theme-primary px-4 text-sm font-black text-slate-950 transition active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50"
        data-testid="verify-approve"
      >
        <Check class="h-4 w-4" aria-hidden="true" />
        <span>{{ $t('billing.console.verify.approve') }}</span>
      </button>
      <button
        type="button"
        :disabled="busy"
        class="inline-flex min-h-12 cursor-pointer items-center justify-center gap-2 rounded-xl border border-rose-500 bg-transparent px-4 text-sm font-black text-rose-700 transition active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 dark:text-rose-300"
        data-testid="verify-reject"
        @click="openReject"
      >
        <X class="h-4 w-4" aria-hidden="true" />
        <span>{{ $t('billing.console.verify.reject') }}</span>
      </button>
    </div>

    <BillingRejectDialog
      :show="rejectOpen"
      v-model:reason="rejectReason"
      :length="reasonLength"
      :valid="reasonValid"
      :busy="busy"
      :subtitle="payment.tenant?.name || ''"
      @close="rejectOpen = false"
      @confirm="reject"
    />
  </form>
</template>
