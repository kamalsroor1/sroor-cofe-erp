<script setup>
import { KeyRound, Zap } from 'lucide-vue-next';

defineProps({
  modelValue: {
    type: String,
    default: 'password',
    validator: (value) => ['password', 'quick'].includes(value),
  },
});

const emit = defineEmits(['update:modelValue']);

const tabs = [
  { value: 'password', icon: KeyRound, label: 'auth.password_login_tab' },
  { value: 'quick', icon: Zap, label: 'auth.quick_login_tab' },
];
</script>

<template>
  <div
    role="tablist"
    :aria-label="$t('auth.login_method')"
    class="grid grid-cols-2 gap-1 p-1 rounded-2xl bg-slate-100 dark:bg-slate-800/70 border border-slate-200 dark:border-slate-700/60"
  >
    <button
      v-for="tab in tabs"
      :key="tab.value"
      type="button"
      role="tab"
      :data-testid="`login-method-${tab.value}`"
      :aria-selected="modelValue === tab.value"
      class="min-h-11 px-3 rounded-xl text-xs font-black font-tajawal flex items-center justify-center gap-2 transition cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
      :class="
        modelValue === tab.value
          ? 'bg-white dark:bg-slate-900 text-theme-primary shadow-sm border border-theme-border'
          : 'text-slate-500 dark:text-slate-400 border border-transparent active:bg-white/60 dark:active:bg-slate-900/60'
      "
      @click="emit('update:modelValue', tab.value)"
    >
      <component :is="tab.icon" class="w-4 h-4 shrink-0" />
      <span>{{ $t(tab.label) }}</span>
    </button>
  </div>
</template>
