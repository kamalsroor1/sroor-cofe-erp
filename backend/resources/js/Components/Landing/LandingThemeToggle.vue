<script setup>
import { computed } from 'vue';
import { Moon, Sun } from 'lucide-vue-next';
import { useAppConfigStore } from '../../stores/appConfig';
import { useTrans } from '../../Composables/useTrans';

defineProps({
  /** icon = square header button; pill = labelled button in the mobile menu. */
  variant: { type: String, default: 'icon', validator: (value) => ['icon', 'pill'].includes(value) },
});

const appConfigStore = useAppConfigStore();
const { t } = useTrans();

const isDark = computed(() => appConfigStore.theme === 'dark');
const label = computed(() => (isDark.value ? t('nav.layout.light_mode') : t('nav.layout.dark_mode')));

const toggle = () => appConfigStore.setTheme(isDark.value ? 'light' : 'dark');
</script>

<template>
  <button
    type="button"
    :aria-label="variant === 'icon' ? label : undefined"
    :title="label"
    class="inline-flex min-h-11 cursor-pointer items-center justify-center border border-mk-line text-mk-muted transition hover:bg-mk-surface-2 hover:text-mk-ink focus-visible:outline-2 focus-visible:outline-mk-primary active:scale-95"
    :class="
      variant === 'icon'
        ? 'min-w-11 rounded-[10px]'
        : 'gap-2 rounded-full bg-mk-surface px-4 text-sm font-medium text-mk-ink'
    "
    @click="toggle"
  >
    <Sun v-if="isDark" class="size-[18px] shrink-0" aria-hidden="true" />
    <Moon v-else class="size-[18px] shrink-0" aria-hidden="true" />
    <span v-if="variant === 'pill'">{{ label }}</span>
  </button>
</template>
