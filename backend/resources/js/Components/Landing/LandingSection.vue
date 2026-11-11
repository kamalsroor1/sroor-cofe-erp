<script setup>
defineProps({
  id: { type: String, required: true },
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
});
</script>

<template>
  <section :id="id" :aria-labelledby="`${id}-title`" tabindex="-1" class="scroll-mt-24 space-y-7 focus:outline-none">
    <header class="mx-auto max-w-2xl space-y-2 text-center">
      <h2 :id="`${id}-title`" class="text-2xl font-black text-slate-900 dark:text-white sm:text-3xl">{{ title }}</h2>
      <p v-if="subtitle" class="text-sm leading-relaxed text-slate-500 dark:text-slate-400 sm:text-base">
        {{ subtitle }}
      </p>
    </header>
    <slot />
  </section>
</template>
