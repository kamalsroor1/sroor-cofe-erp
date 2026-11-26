<script setup>
import { ref } from 'vue';
import { Check } from 'lucide-vue-next';
import { useFormatters } from '@/Composables/useFormatters';
import { planPrice, priceKey } from '@/helpers/billingChange';
import { nextRadioIndex } from '@/helpers/radioNav';

const props = defineProps({
  plans: { type: Array, required: true },
  cycle: { type: String, default: 'monthly' },
});

const selected = defineModel({ type: Number, default: null });
const buttons = ref([]);
const { formatMoney } = useFormatters();

const onKey = (event, index) => {
  const next = nextRadioIndex(event.key, index, props.plans.length, document.documentElement.dir === 'rtl');
  if (next === null) return;
  event.preventDefault();
  selected.value = props.plans[next].id;
  buttons.value[next]?.focus();
};
</script>

<template>
  <div role="radiogroup" :aria-label="$t('subscription.billing.change.choose_plan_title')" class="space-y-2">
    <button
      v-for="(plan, index) in plans"
      :key="plan.id"
      :ref="(el) => (buttons[index] = el)"
      type="button"
      role="radio"
      :aria-checked="selected === plan.id"
      :tabindex="selected === plan.id ? 0 : -1"
      class="w-full min-h-14 rounded-xl border px-3 flex items-center justify-between gap-3 cursor-pointer transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primary)]"
      :class="
        selected === plan.id
          ? 'bg-theme-primary/10 border-theme-primary'
          : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700'
      "
      @click="selected = plan.id"
      @keydown="onKey($event, index)"
    >
      <span class="flex items-center gap-2 min-w-0 text-start">
        <span
          class="w-5 h-5 shrink-0 rounded-full border-2 flex items-center justify-center"
          :class="selected === plan.id ? 'border-theme-primary bg-theme-primary text-slate-950' : 'border-slate-400'"
          aria-hidden="true"
        >
          <Check v-if="selected === plan.id" class="w-3 h-3" />
        </span>
        <span class="font-black text-slate-900 dark:text-white truncate">{{ plan.name }}</span>
      </span>
      <span class="shrink-0 text-sm font-bold text-slate-700 dark:text-slate-300" dir="auto">
        {{ $t(`subscription.billing.change.${priceKey(cycle)}`, { price: formatMoney(planPrice(plan, cycle)) }) }}
      </span>
    </button>
  </div>
</template>
