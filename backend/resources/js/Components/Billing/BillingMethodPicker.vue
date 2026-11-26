<script setup>
import { computed, ref } from 'vue';
import { nextRadioIndex } from '@/helpers/radioNav';

const props = defineProps({
  methods: { type: Array, required: true },
  error: { type: String, default: '' },
});

const selected = defineModel({ type: String, default: '' });
const buttons = ref([]);

const columns = computed(() => Math.min(Math.max(props.methods.length, 1), 3));

const choose = (key) => {
  selected.value = key;
};

// Arrow keys move the choice (radiogroup pattern).
const onKey = (event, index) => {
  const next = nextRadioIndex(event.key, index, props.methods.length, document.documentElement.dir === 'rtl');
  if (next === null) return;
  event.preventDefault();
  choose(props.methods[next].key);
  buttons.value[next]?.focus();
};
</script>

<template>
  <div class="space-y-2">
    <h3 v-if="methods.length === 1" class="text-base font-black text-slate-900 dark:text-white">
      {{ methods[0].label }}
    </h3>
    <div
      v-else
      role="radiogroup"
      :aria-label="$t('subscription.billing.pay.step_method')"
      class="grid gap-1.5"
      :style="{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }"
    >
      <button
        v-for="(method, index) in methods"
        :key="method.key"
        :ref="(el) => (buttons[index] = el)"
        type="button"
        role="radio"
        :aria-checked="selected === method.key"
        :tabindex="selected === method.key || (!selected && index === 0) ? 0 : -1"
        class="min-h-12 rounded-xl px-2 text-sm font-bold cursor-pointer transition active:scale-[0.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primary)]"
        :class="
          selected === method.key
            ? 'bg-theme-primary text-slate-950'
            : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300'
        "
        @click="choose(method.key)"
        @keydown="onKey($event, index)"
      >
        {{ method.label }}
      </button>
    </div>
    <p v-if="methods.length > 1 && !selected" class="text-xs font-medium text-slate-500 dark:text-slate-400">
      {{ $t('subscription.billing.pay.pick_method_hint') }}
    </p>
    <p v-if="error" class="text-xs font-bold text-rose-600 dark:text-rose-400" role="alert">{{ error }}</p>
  </div>
</template>
