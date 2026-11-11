<script setup>
defineProps({
  /** faint = hero / page header / bands; soft = the final CTA gradient. */
  strength: { type: String, default: 'faint', validator: (value) => ['faint', 'soft'].includes(value) },
});
</script>

<template>
  <!-- The Ibraa mark drawn huge and thin as a background watermark on dark bands (same paths as BrandMark). -->
  <svg
    viewBox="0 0 100 100"
    fill="none"
    stroke="currentColor"
    stroke-linecap="round"
    stroke-linejoin="round"
    class="pointer-events-none text-white"
    aria-hidden="true"
    focusable="false"
  >
    <g :stroke-opacity="strength === 'soft' ? 0.08 : 0.05">
      <path d="M50 14 A36 36 0 1 1 20 30" stroke-width="5.4" />
      <polyline points="12,22 20,30 30,24" stroke-width="4.2" />
    </g>
    <polyline points="36,51 46,61 66,40" stroke-width="4.2" :stroke-opacity="strength === 'soft' ? 0.06 : 0.04" />
  </svg>
</template>
