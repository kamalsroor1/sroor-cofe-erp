<template>
  <div class="bg-white dark:bg-slate-900/90 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm dark:shadow-xl space-y-4 text-xs font-tajawal">
    <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-1.5">
      <span>🎨</span>
      <span>{{ $t('profile.theme_pref') }}</span>
    </h2>

    <div class="grid grid-cols-2 gap-4">
      <button
        type="button"
        @click="$emit('update:field', 'theme_preference', 'dark')"
        class="min-h-[70px] p-4 rounded-xl border text-center transition cursor-pointer active:scale-95 select-none"
        :class="form.theme_preference === 'dark' ? 'bg-theme-light border-theme-primary text-theme-primary font-black shadow-sm' : 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400'"
      >
        <div class="text-lg mb-1">🌙</div>
        <div class="font-bold">{{ $t('profile.theme_dark_slate') }}</div>
      </button>

      <button
        type="button"
        @click="$emit('update:field', 'theme_preference', 'light')"
        class="min-h-[70px] p-4 rounded-xl border text-center transition cursor-pointer active:scale-95 select-none"
        :class="form.theme_preference === 'light' ? 'bg-theme-light border-theme-primary text-theme-primary font-black shadow-sm' : 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400'"
      >
        <div class="text-lg mb-1">☀️</div>
        <div class="font-bold">{{ $t('profile.theme_light_shell') }}</div>
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  form: { type: Object, default: () => ({}) },
});

defineEmits(['update:field']);
</script>
