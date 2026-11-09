<script setup>
import { computed } from 'vue';
import { Check, Copy, Landmark } from 'lucide-vue-next';
import { ref } from 'vue';
import { notifySuccess } from '@/helpers/alert';
import { useTrans } from '@/Composables/useTrans';

const props = defineProps({
  instructions: { type: Object, default: () => ({}) },
  supportPhone: { type: String, default: '' },
});

const { t } = useTrans();
const copiedKey = ref('');

const rows = computed(() =>
  [
    { key: 'instapay_address', label: t('subscription.billing.pay.instapay_address') },
    { key: 'vodafone_cash_number', label: t('subscription.billing.pay.vodafone_cash_number') },
    { key: 'account_name', label: t('subscription.billing.pay.account_name') },
  ]
    .map((row) => ({ ...row, value: props.instructions?.[row.key] || '' }))
    .filter((row) => row.value)
);
const note = computed(() => props.instructions?.payment_note || '');
const hasAny = computed(() => rows.value.length > 0 || note.value !== '');

const copy = async (row) => {
  try {
    await navigator.clipboard.writeText(row.value);
    copiedKey.value = row.key;
    notifySuccess(t('subscription.billing.pay.copied'));
    setTimeout(() => {
      copiedKey.value = '';
    }, 2000);
  } catch {
    copiedKey.value = '';
  }
};
</script>

<template>
  <div class="rounded-2xl border border-theme-border bg-theme-light p-3 sm:p-4 space-y-3">
    <div class="flex items-center gap-2">
      <Landmark class="w-4 h-4 text-theme-primary" />
      <h4 class="text-xs font-black text-slate-900 dark:text-white">
        {{ $t('subscription.billing.pay.instructions_title') }}
      </h4>
    </div>

    <template v-if="hasAny">
      <p class="text-[11px] font-medium text-slate-600 dark:text-slate-300">
        {{ $t('subscription.billing.pay.instructions_hint') }}
      </p>
      <ul class="space-y-2">
        <li
          v-for="row in rows"
          :key="row.key"
          class="flex items-center justify-between gap-2 rounded-xl bg-white/70 dark:bg-slate-900/60 px-3 py-2"
        >
          <div class="min-w-0">
            <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400">{{ row.label }}</p>
            <p class="text-sm font-black text-slate-900 dark:text-white break-all" dir="ltr">{{ row.value }}</p>
          </div>
          <button
            type="button"
            class="min-w-[44px] min-h-[44px] rounded-xl flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer shrink-0"
            :aria-label="$t('subscription.billing.pay.copy')"
            @click="copy(row)"
          >
            <Check v-if="copiedKey === row.key" class="w-4 h-4 text-emerald-500" />
            <Copy v-else class="w-4 h-4" />
          </button>
        </li>
      </ul>
      <p v-if="note" class="text-[11px] font-bold text-slate-700 dark:text-slate-300 leading-relaxed">{{ note }}</p>
    </template>

    <p v-else class="text-xs font-bold text-slate-700 dark:text-slate-300">
      {{ $t('subscription.billing.pay.contact_support', { phone: supportPhone }) }}
    </p>
  </div>
</template>
