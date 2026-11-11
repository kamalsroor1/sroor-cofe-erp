<script setup>
import { computed } from 'vue';
import { Moon, Sun } from 'lucide-vue-next';
import { useAppConfigStore } from '../../stores/appConfig';
import { useTrans } from '../../Composables/useTrans';

const appConfigStore = useAppConfigStore();
const { t } = useTrans();

const isDark = computed(() => appConfigStore.theme === 'dark');
const label = computed(() => (isDark.value ? t('nav.layout.light_mode') : t('nav.layout.dark_mode')));

const toggle = () => appConfigStore.setTheme(isDark.value ? 'light' : 'dark');
</script>

<template>
  <button
    type="button"
    :aria-label="label"
    :title="label"
    class="flex min-h-11 min-w-11 cursor-pointer items-center justify-center rounded-xl border border-slate-300 bg-slate-100 text-slate-700 transition hover:bg-slate-200 active:scale-95 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
    @click="toggle"
  >
    <Sun v-if="isDark" class="h-4 w-4 text-theme-primary" />
    <Moon v-else class="h-4 w-4 text-theme-primary" />
  </button>
</template>
