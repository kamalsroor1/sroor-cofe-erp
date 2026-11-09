<script setup>
import { ref } from 'vue';
import { Languages } from 'lucide-vue-next';
import { useLocale } from '../../Composables/useLocale';

defineProps({
  /** 'pill' = compact bordered button (headers, login); 'menu' = full-width row inside a dropdown. */
  variant: { type: String, default: 'pill', validator: (value) => ['pill', 'menu'].includes(value) },
});
const emit = defineEmits(['switched']);

const { nextLocale, setLocale } = useLocale();
const isSwitching = ref(false);

const toggle = async () => {
  if (isSwitching.value) return;
  isSwitching.value = true;
  try {
    await setLocale(nextLocale.value);
    emit('switched');
  } finally {
    isSwitching.value = false;
  }
};
</script>

<template>
  <button
    type="button"
    :disabled="isSwitching"
    :title="$t('locale.switch_to', { language: $t(`locale.names.${nextLocale}`) })"
    :aria-label="$t('locale.switch_to', { language: $t(`locale.names.${nextLocale}`) })"
    :class="[
      variant === 'menu'
        ? 'w-full min-h-[44px] flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900 rounded-xl text-start'
        : 'min-h-[44px] min-w-[44px] px-3 py-1.5 gap-1.5 justify-center bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-800 dark:text-slate-200',
      'flex items-center transition cursor-pointer active:scale-95 disabled:opacity-60 disabled:cursor-wait',
    ]"
    @click="toggle"
  >
    <Languages class="w-4 h-4 shrink-0" :class="variant === 'menu' ? 'text-slate-400' : 'text-theme-primary'" />
    <span>{{ $t(`locale.names.${nextLocale}`) }}</span>
  </button>
</template>
