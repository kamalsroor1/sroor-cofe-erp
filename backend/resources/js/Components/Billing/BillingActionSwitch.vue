<script setup>
import { ref } from 'vue';
import { nextRadioIndex } from '@/helpers/radioNav';

defineProps({
  // The upgrade option is disabled (with a reason shown by the parent) on the top plan.
  upgradeDisabled: { type: Boolean, default: false },
});

const action = defineModel({ type: String, default: 'upgrade' });
const buttons = ref([]);

const OPTIONS = [
  { value: 'renew', labelKey: 'subscription.billing.change.action_renew' },
  { value: 'upgrade', labelKey: 'subscription.billing.change.action_upgrade' },
];

const isDisabled = (option, upgradeDisabled) => option.value === 'upgrade' && upgradeDisabled;

const onKey = (event, index, upgradeDisabled) => {
  const next = nextRadioIndex(event.key, index, OPTIONS.length, document.documentElement.dir === 'rtl');
  if (next === null) return;
  event.preventDefault();
  if (isDisabled(OPTIONS[next], upgradeDisabled)) return;
  action.value = OPTIONS[next].value;
  buttons.value[next]?.focus();
};
</script>

<template>
  <div
    role="radiogroup"
    :aria-label="$t('subscription.billing.change.preview_title')"
    class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 dark:bg-slate-950 p-1"
  >
    <button
      v-for="(option, index) in OPTIONS"
      :key="option.value"
      :ref="(el) => (buttons[index] = el)"
      type="button"
      role="radio"
      :aria-checked="action === option.value"
      :aria-disabled="isDisabled(option, upgradeDisabled)"
      :tabindex="action === option.value ? 0 : -1"
      class="min-h-11 rounded-lg px-2 text-sm font-bold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primary)]"
      :class="[
        action === option.value ? 'bg-theme-primary text-slate-950' : 'text-slate-700 dark:text-slate-300',
        isDisabled(option, upgradeDisabled) ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
      ]"
      @click="!isDisabled(option, upgradeDisabled) && (action = option.value)"
      @keydown="onKey($event, index, upgradeDisabled)"
    >
      {{ $t(option.labelKey) }}
    </button>
  </div>
</template>
