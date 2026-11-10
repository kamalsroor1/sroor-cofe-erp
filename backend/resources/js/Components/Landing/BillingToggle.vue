<script setup>
defineProps({
  modelValue: { type: String, default: 'monthly' },
});

defineEmits(['update:modelValue']);

const options = ['monthly', 'yearly'];
</script>

<template>
  <div
    role="group"
    :aria-label="$t('landing.pricing.billing_toggle')"
    class="inline-flex rounded-2xl border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-900"
  >
    <button
      v-for="option in options"
      :key="option"
      type="button"
      :aria-pressed="modelValue === option"
      class="min-h-11 min-w-24 cursor-pointer rounded-xl px-4 text-xs font-black transition active:scale-95"
      :class="
        modelValue === option
          ? 'bg-theme-primary text-slate-950 shadow-xs'
          : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
      "
      @click="$emit('update:modelValue', option)"
    >
      {{ $t(option === 'monthly' ? 'landing.billing_monthly' : 'landing.billing_yearly') }}
    </button>
  </div>
</template>
