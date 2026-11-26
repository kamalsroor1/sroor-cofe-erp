<script setup>
import AppModal from '../../Common/AppModal.vue';
import BaseButton from '../../Common/BaseButton.vue';
import { REJECT_QUICK_REASONS, quickReasonActive } from '../../../helpers/billingReview';
import { REJECT_REASON_MAX, REJECT_REASON_MIN } from '../../../Composables/useVerifyDecision';
import { useTrans } from '../../../Composables/useTrans';

defineProps({
  show: { type: Boolean, default: false },
  reason: { type: String, default: '' },
  length: { type: Number, default: 0 },
  valid: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
  subtitle: { type: String, default: '' },
});

defineEmits(['close', 'update:reason', 'confirm']);

const { t } = useTrans();
const quickText = (key) => t(`billing.console.verify.reject_reason_${key}`);
</script>

<template>
  <AppModal
    :show="show"
    :title="$t('billing.console.verify.reject_title')"
    :subtitle="subtitle"
    max-width="lg"
    @close="$emit('close')"
  >
    <form class="space-y-3 font-tajawal" novalidate @submit.prevent="$emit('confirm')">
      <p class="text-xs font-bold text-slate-600 dark:text-slate-300">
        {{ $t('billing.console.verify.reject_shop_sees') }}
      </p>

      <div class="flex flex-wrap gap-2" role="group" :aria-label="$t('billing.console.verify.reject_title')">
        <button
          v-for="key in REJECT_QUICK_REASONS"
          :key="key"
          type="button"
          class="min-h-11 cursor-pointer rounded-full border px-3 text-xs font-black transition active:scale-[0.98]"
          :class="
            quickReasonActive(reason, quickText(key))
              ? 'border-theme-primary bg-theme-primary/10 text-slate-900 dark:text-white'
              : 'border-slate-300 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200'
          "
          :aria-pressed="quickReasonActive(reason, quickText(key))"
          @click="$emit('update:reason', quickText(key))"
        >
          {{ quickText(key) }}
        </button>
      </div>

      <div class="space-y-1">
        <label for="billing-reject-reason" class="block text-xs font-black text-slate-700 dark:text-slate-200">
          {{ $t('billing.console.queue.reason_label') }}
        </label>
        <textarea
          id="billing-reject-reason"
          :value="reason"
          rows="3"
          :maxlength="REJECT_REASON_MAX"
          :placeholder="$t('billing.console.queue.reason_placeholder')"
          class="w-full rounded-xl border border-slate-300 bg-slate-50 p-3 text-base font-bold text-slate-900 outline-hidden focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 sm:text-sm"
          @input="$emit('update:reason', $event.target.value)"
        ></textarea>
        <p class="text-end text-xs font-bold text-slate-500 dark:text-slate-400" dir="ltr">
          {{ $t('billing.console.verify.reject_counter', { count: length, max: REJECT_REASON_MAX }) }}
        </p>
        <p
          v-if="length > 0 && length < REJECT_REASON_MIN"
          class="text-xs font-bold text-rose-600 dark:text-rose-400"
          role="alert"
        >
          {{ $t('billing.console.queue.reason_required') }}
        </p>
      </div>

      <div class="flex justify-end gap-2 pt-1">
        <BaseButton
          type="button"
          variant="ghost"
          :label="$t('common.cancel')"
          :disabled="busy"
          @click="$emit('close')"
        />
        <BaseButton
          type="submit"
          variant="danger"
          :loading="busy"
          :disabled="!valid"
          :label="$t('billing.console.queue.reject_confirm')"
        />
      </div>
    </form>
  </AppModal>
</template>
