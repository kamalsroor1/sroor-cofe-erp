<script setup>
import LandingFooter from './LandingFooter.vue';
import LandingNav from './LandingNav.vue';

defineProps({
  /** brand block of GET /public/plans (name, support_phone, support_email…); empty while loading. */
  brand: { type: Object, default: () => ({}) },
});
</script>

<template>
  <!-- `.marketing` scopes the --mk-* design tokens (resources/css/app.css); dark follows html.dark. -->
  <div class="marketing min-h-0 flex-1 overflow-y-auto font-sans antialiased">
    <LandingNav :brand-name="brand.name || ''" />
    <main id="landing-main">
      <slot />
    </main>
    <LandingFooter :brand="brand" />
  </div>
</template>
