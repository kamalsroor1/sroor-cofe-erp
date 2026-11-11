<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  /** Id of the heading, for the section's aria-labelledby. */
  id: { type: String, default: undefined },
  align: { type: String, default: 'center', validator: (value) => ['center', 'start'].includes(value) },
  /** lg = homepage section heads (44px), md = inner-page section heads (38px). */
  size: { type: String, default: 'lg', validator: (value) => ['lg', 'md'].includes(value) },
  tag: { type: String, default: 'h2' },
});
</script>

<template>
  <header
    class="flex flex-col gap-2.5"
    :class="align === 'center' ? 'items-center text-center' : 'items-start text-start'"
  >
    <component
      :is="tag"
      :id="id"
      class="max-w-3xl leading-[1.3] font-extrabold text-mk-ink"
      :class="size === 'lg' ? 'text-[30px] lg:text-[44px]' : 'text-[26px] lg:text-[38px]'"
    >
      {{ title }}
    </component>
    <p
      v-if="subtitle"
      class="max-w-2xl leading-[1.6] text-mk-muted"
      :class="size === 'lg' ? 'text-[17px] lg:text-xl' : 'text-base lg:text-[19px]'"
    >
      {{ subtitle }}
    </p>
    <slot />
  </header>
</template>
