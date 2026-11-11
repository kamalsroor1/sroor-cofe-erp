<script setup>
import { ref } from 'vue';
import { Languages } from 'lucide-vue-next';
import { useLocale } from '../../Composables/useLocale';

defineProps({
  /** text = short code in the header; pill = bordered button (mobile menu); name = full language name (footer). */
  variant: { type: String, default: 'text', validator: (value) => ['text', 'pill', 'name'].includes(value) },
});

const { nextLocale, setLocale } = useLocale();
const isSwitching = ref(false);

const toggle = async () => {
  if (isSwitching.value) return;
  isSwitching.value = true;
  try {
    await setLocale(nextLocale.value);
  } finally {
    isSwitching.value = false;
  }
};
</script>

<template>
  <button
    type="button"
    :disabled="isSwitching"
    :lang="nextLocale"
    :aria-label="$t('locale.switch_to', { language: $t(`locale.names.${nextLocale}`) })"
    :title="$t('locale.switch_to', { language: $t(`locale.names.${nextLocale}`) })"
    class="inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 font-bold transition focus-visible:outline-2 focus-visible:outline-mk-primary active:scale-95 disabled:cursor-wait disabled:opacity-60"
    :class="{
      'min-w-11 rounded-[10px] text-sm text-mk-muted hover:text-mk-ink': variant === 'text',
      'rounded-full border border-mk-line bg-mk-surface px-4 text-sm text-mk-ink hover:bg-mk-surface-2':
        variant === 'pill',
      'rounded-lg px-1 text-sm font-medium text-mk-faint hover:text-mk-ink': variant === 'name',
    }"
    @click="toggle"
  >
    <Languages v-if="variant !== 'text'" class="size-4 shrink-0" aria-hidden="true" />
    <span>{{
      variant === 'name' ? $t(`locale.names.${nextLocale}`) : $t(`landing.nav.locale_short.${nextLocale}`)
    }}</span>
  </button>
</template>
