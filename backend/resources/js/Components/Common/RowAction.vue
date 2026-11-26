<script setup>
import { computed } from 'vue';

// UX-235: a table/card row action that always shows its word next to the icon (phone and desktop alike).
// variant: 'primary' = the row's main action, 'danger' = delete/archive (always red), 'neutral' = the rest.
const props = defineProps({
  icon: { type: [Object, Function], required: true },
  label: { type: String, required: true },
  variant: { type: String, default: 'neutral', validator: (v) => ['neutral', 'primary', 'danger'].includes(v) },
  to: { type: [String, Object], default: null },
  title: { type: String, default: null },
});

defineEmits(['click']);

const variantClass = computed(
  () =>
    ({
      primary: 'bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 border-emerald-500/30',
      danger: 'bg-rose-500/5 hover:bg-rose-500/15 text-rose-600 dark:text-rose-400 border-rose-500/30',
      neutral:
        'bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
    })[props.variant]
);

const baseClass =
  'min-h-11 lg:min-h-9 px-2.5 inline-flex items-center justify-center gap-1.5 rounded-xl border text-sm lg:text-xs font-bold font-tajawal whitespace-nowrap transition cursor-pointer active:scale-95';
</script>

<template>
  <router-link v-if="to" :to="to" :class="[baseClass, variantClass]" :title="title || label">
    <component :is="icon" class="w-4 h-4 shrink-0" aria-hidden="true" />
    <span>{{ label }}</span>
  </router-link>
  <button
    v-else
    type="button"
    :class="[baseClass, variantClass]"
    :title="title || label"
    @click="$emit('click', $event)"
  >
    <component :is="icon" class="w-4 h-4 shrink-0" aria-hidden="true" />
    <span>{{ label }}</span>
  </button>
</template>
