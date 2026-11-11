<script setup>
import { computed } from 'vue';

const props = defineProps({
  /** Router target; renders a <router-link>. */
  to: { type: [String, Object], default: null },
  /** External / tel / mailto target; renders an <a>. */
  href: { type: String, default: '' },
  type: { type: String, default: 'button' },
  /** accent = amber with dark ink (main CTA); primary = brand green; ghost-dark = on dark bands; outline = neutral. */
  variant: {
    type: String,
    default: 'accent',
    validator: (value) => ['accent', 'primary', 'ghost-dark', 'outline'].includes(value),
  },
  size: { type: String, default: 'md', validator: (value) => ['sm', 'md', 'lg'].includes(value) },
  /** Trailing lucide icon. */
  icon: { type: [Object, Function], default: null },
  /** Mirror the trailing icon in LTR (arrows drawn for RTL). */
  flipIcon: { type: Boolean, default: true },
  block: { type: Boolean, default: false },
});

defineEmits(['click']);

const tag = computed(() => {
  if (props.to) return 'router-link';
  return props.href ? 'a' : 'button';
});

const SIZES = {
  sm: 'min-h-11 px-3.5 text-sm gap-2',
  md: 'min-h-[46px] px-5 text-base gap-2.5',
  lg: 'min-h-14 px-7 text-lg gap-2.5',
};
const ICON_SIZES = { sm: 'size-4', md: 'size-5', lg: 'size-5' };
const VARIANTS = {
  accent: 'bg-mk-accent text-mk-accent-ink mk-shadow-accent hover:bg-mk-accent-hover',
  primary: 'bg-mk-primary text-mk-primary-ink hover:opacity-90',
  'ghost-dark': 'border-[1.5px] border-white/25 bg-white/8 text-white hover:bg-white/15',
  outline: 'border-[1.5px] border-mk-line bg-mk-surface text-mk-ink hover:bg-mk-surface-2',
};
</script>

<template>
  <component
    :is="tag"
    :to="to || undefined"
    :href="tag === 'a' ? href : undefined"
    :type="tag === 'button' ? type : undefined"
    class="inline-flex cursor-pointer items-center justify-center rounded-[14px] font-bold whitespace-nowrap transition select-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mk-accent active:scale-[0.98]"
    :class="[SIZES[size], VARIANTS[variant], { 'w-full': block }]"
    @click="$emit('click', $event)"
  >
    <span class="truncate"><slot /></span>
    <component :is="icon" v-if="icon" class="shrink-0" :class="[ICON_SIZES[size], { 'ltr:rotate-180': flipIcon }]" />
  </component>
</template>
