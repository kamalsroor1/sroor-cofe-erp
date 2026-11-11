<script setup>
defineProps({
  modelValue: { type: String, default: 'monthly' },
  /** Translucent variant for the dark page header. */
  onDark: { type: Boolean, default: false },
  /** Best yearly saving of the catalog (0 hides the chip); computed from the API prices. */
  savePercent: { type: Number, default: 0 },
  /** Plans save different percentages: the chip reads "up to". */
  saveUpTo: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

const options = ['monthly', 'yearly'];
</script>

<template>
  <div
    role="group"
    :aria-label="$t('landing.pricing.billing_toggle')"
    class="inline-flex items-center gap-1 rounded-full border p-1"
    :class="onDark ? 'border-white/15 bg-white/8' : 'border-mk-line bg-mk-surface'"
  >
    <button
      v-for="option in options"
      :key="option"
      type="button"
      :aria-pressed="modelValue === option"
      class="min-h-11 min-w-20 cursor-pointer rounded-full px-4 text-sm font-medium transition focus-visible:outline-2 active:scale-95"
      :class="[
        onDark ? 'focus-visible:outline-mk-accent' : 'focus-visible:outline-mk-primary',
        modelValue === option
          ? onDark
            ? 'bg-white font-bold text-mk-hero'
            : 'bg-mk-primary font-bold text-mk-primary-ink'
          : onDark
            ? 'text-white/80 hover:text-white'
            : 'text-mk-muted hover:text-mk-ink',
      ]"
      @click="$emit('update:modelValue', option)"
    >
      {{ $t(option === 'monthly' ? 'landing.billing_monthly' : 'landing.billing_yearly') }}
    </button>
    <span
      v-if="savePercent > 0"
      class="me-1 inline-flex h-[26px] items-center rounded-full bg-mk-accent px-2.5 text-xs font-bold whitespace-nowrap text-mk-accent-ink"
    >
      {{ $t(saveUpTo ? 'landing.pricing.save_up_to' : 'landing.pricing.save_short', { percent: savePercent }) }}
    </span>
  </div>
</template>
