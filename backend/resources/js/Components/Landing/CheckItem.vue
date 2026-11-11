<script setup>
import { Check } from 'lucide-vue-next';

defineProps({
  /** sm = compact plan cards, md = cards / mobile, lg = desktop feature rows. */
  size: { type: String, default: 'md', validator: (value) => ['sm', 'md', 'lg'].includes(value) },
  /** primary = on light surfaces; hero = on the dark hero / bands. */
  tone: { type: String, default: 'primary', validator: (value) => ['primary', 'hero'].includes(value) },
  tag: { type: String, default: 'li' },
});

const TEXT = { sm: 'text-sm', md: 'text-[15px] sm:text-base', lg: 'text-base lg:text-[17px]' };
</script>

<template>
  <component
    :is="tag"
    class="flex items-start gap-3 font-medium"
    :class="[TEXT[size], tone === 'hero' ? 'text-mk-hero-muted' : 'text-mk-ink']"
  >
    <span
      class="mt-px flex size-[22px] shrink-0 items-center justify-center rounded-full"
      :class="tone === 'hero' ? 'bg-white/13 text-white' : 'bg-mk-primary text-mk-primary-ink'"
      aria-hidden="true"
    >
      <Check class="size-3.5" :stroke-width="3" />
    </span>
    <span class="leading-relaxed"><slot /></span>
  </component>
</template>
