<script setup>
import { LANDING_SECTIONS, useLandingAnchors } from '../../Composables/useLandingAnchors';

defineProps({
  /** 'bar' = inline desktop links; 'menu' = stacked full-width rows in the mobile menu. */
  variant: { type: String, default: 'bar', validator: (value) => ['bar', 'menu'].includes(value) },
});

const emit = defineEmits(['navigate']);

const { sectionHref, goToSection } = useLandingAnchors();

const go = (id) => {
  emit('navigate');
  goToSection(id);
};
</script>

<template>
  <a
    v-for="id in LANDING_SECTIONS"
    :key="id"
    :href="sectionHref(id)"
    class="flex min-h-11 items-center rounded-xl px-3 text-sm font-bold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
    :class="{ 'w-full': variant === 'menu' }"
    @click.prevent="go(id)"
  >
    {{ $t(`landing.nav.${id}`) }}
  </a>
  <router-link
    :to="{ name: 'login' }"
    class="flex min-h-11 items-center rounded-xl px-3 text-sm font-bold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
    :class="{ 'w-full': variant === 'menu' }"
    @click="emit('navigate')"
  >
    {{ $t('landing.nav.login') }}
  </router-link>
</template>
