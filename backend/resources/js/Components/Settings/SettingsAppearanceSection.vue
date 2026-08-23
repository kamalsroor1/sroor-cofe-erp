<template>
  <div class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 shadow-sm dark:shadow-xl space-y-6 font-tajawal">
    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-500 flex items-center justify-center">
          <Palette class="w-5 h-5" />
        </div>
        <div>
          <h2 class="text-base font-black text-slate-900 dark:text-white">{{ $t('settings.sec_appearance_label') }}</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('settings.sec_appearance_subtitle') }}</p>
        </div>
      </div>
    </div>

    <!-- Theme Color Palette Grid -->
    <div class="space-y-3">
      <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
        {{ $t('settings.preset_palettes_label') }}
      </label>
      
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <button
          v-for="color in colorPalettes"
          :key="color.id"
          type="button"
          @click="$emit('select-color', color.id)"
          class="p-3.5 rounded-2xl border transition-all flex flex-col items-center gap-2.5 cursor-pointer relative active:scale-95 select-none"
          :class="themeColor === color.id ? 'border-theme-primary bg-theme-light ring-2 ring-theme-primary shadow-sm' : 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/60 hover:border-slate-300 dark:hover:border-slate-700'"
        >
          <div class="w-8 h-8 rounded-full shadow-md flex items-center justify-center" :style="{ backgroundColor: color.hex }">
            <span v-if="themeColor === color.id" class="text-white text-xs font-black">✓</span>
          </div>
          <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ color.name }}</span>
        </button>
      </div>

      <!-- Custom Color Picker -->
      <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mt-3">
        <div class="flex items-center gap-3">
          <div class="relative flex items-center justify-center">
            <input
              type="color"
              :value="customColor"
              @input="$emit('update:custom-color', $event.target.value)"
              class="w-11 h-11 rounded-2xl cursor-pointer border-2 border-slate-300 dark:border-slate-700 p-0.5 bg-transparent overflow-hidden shadow-md"
              title="اختر لون مخصص"
            />
          </div>
          <div>
            <div class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <span>{{ $t('settings.custom_color_title') }}</span>
              <span v-if="themeColor.startsWith('#')" class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-theme-light border border-theme-border text-theme-primary">
                {{ $t('settings.active_badge') }}
              </span>
            </div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400">
              {{ $t('settings.custom_color_desc') }}
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto">
          <div class="relative flex-1 sm:w-32">
            <input
              type="text"
              :value="customColor"
              @input="$emit('update:custom-color', $event.target.value)"
              placeholder="#10b981"
              maxlength="7"
              dir="ltr"
              class="w-full h-10 px-3 font-mono font-bold text-xs bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-center text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
            />
          </div>

          <button
            type="button"
            @click="$emit('pick-screen')"
            class="px-3.5 h-10 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer whitespace-nowrap active:scale-95"
            :title="$t('settings.eyedropper_title')"
          >
            <Pipette class="w-4 h-4 text-theme-primary" />
            <span class="hidden sm:inline">{{ $t('settings.eyedropper_btn') }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Light / Dark Mode Toggle -->
    <div class="pt-4 border-t border-slate-200 dark:border-slate-800">
      <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">
        {{ $t('settings.theme_mode_label') }}
      </label>

      <div class="grid grid-cols-2 gap-3">
        <button
          type="button"
          @click="$emit('set-theme', 'dark')"
          class="p-4 rounded-2xl border transition-all flex items-center justify-center gap-2.5 cursor-pointer active:scale-95 select-none"
          :class="isDark ? 'border-theme-primary bg-slate-900 text-white font-bold ring-1 ring-theme-primary' : 'border-slate-200 dark:border-slate-800 bg-slate-50 text-slate-600'"
        >
          <Moon class="w-5 h-5 text-theme-primary" />
          <span>{{ $t('settings.dark_mode') }}</span>
        </button>

        <button
          type="button"
          @click="$emit('set-theme', 'light')"
          class="p-4 rounded-2xl border transition-all flex items-center justify-center gap-2.5 cursor-pointer active:scale-95 select-none"
          :class="!isDark ? 'border-theme-primary bg-white text-slate-900 font-bold shadow-md ring-1 ring-theme-primary' : 'border-slate-200 dark:border-slate-800 bg-slate-900/40 text-slate-400'"
        >
          <Sun class="w-5 h-5 text-theme-primary" />
          <span>{{ $t('settings.light_mode') }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Palette, Pipette, Sun, Moon } from 'lucide-vue-next';

defineProps({
  themeColor: { type: String, default: 'amber' },
  customColor: { type: String, default: '#10b981' },
  colorPalettes: { type: Array, default: () => [] },
  isDark: { type: Boolean, default: false },
});

defineEmits(['select-color', 'update:custom-color', 'pick-screen', 'set-theme']);
</script>
