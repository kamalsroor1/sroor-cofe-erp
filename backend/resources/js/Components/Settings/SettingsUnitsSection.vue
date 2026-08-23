<template>
  <div class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 shadow-sm dark:shadow-xl space-y-6 font-tajawal">
    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 flex items-center justify-center">
          <Package class="w-5 h-5" />
        </div>
        <div>
          <h2 class="text-base font-black text-slate-900 dark:text-white">{{ $t('settings.sec_units_label') }}</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('settings.sec_units_subtitle') }}</p>
        </div>
      </div>
    </div>

    <!-- Active Units Chips -->
    <div class="space-y-3">
      <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
        {{ $t('settings.active_units_label') }}
      </label>

      <div class="flex flex-wrap gap-2 p-3 bg-slate-50 dark:bg-slate-900/60 rounded-2xl border border-slate-200 dark:border-slate-800 min-h-[52px]">
        <div
          v-for="(u, idx) in activeUnitsList"
          :key="idx"
          class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold flex items-center gap-2 shadow-xs"
        >
          <span>{{ u }}</span>
          <button
            type="button"
            @click="$emit('remove-unit', idx)"
            class="text-slate-400 hover:text-rose-500 transition cursor-pointer"
          >
            ✕
          </button>
        </div>
      </div>
    </div>

    <!-- Add Custom Unit Input -->
    <div class="flex items-center gap-2">
      <input
        :value="newUnitInput"
        @input="$emit('update:new-unit', $event.target.value)"
        @keyup.enter="$emit('add-unit')"
        type="text"
        :placeholder="$t('settings.new_unit_placeholder')"
        class="flex-1 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-theme-primary font-tajawal"
      />
      <button
        type="button"
        @click="$emit('add-unit')"
        class="px-5 py-2.5 bg-theme-primary hover:bg-theme-hover text-white text-xs font-bold rounded-xl transition shadow-xs cursor-pointer active:scale-95"
      >
        {{ $t('settings.add_unit_btn') }}
      </button>
    </div>

    <!-- Preset Units Selection -->
    <div class="space-y-2 pt-2 border-t border-slate-200 dark:border-slate-800">
      <label class="block text-xs font-bold text-slate-500 dark:text-slate-400">
        {{ $t('settings.preset_units_label') }}
      </label>
      <div class="flex flex-wrap gap-1.5">
        <button
          v-for="preset in defaultPresets"
          :key="preset"
          type="button"
          @click="$emit('add-preset', preset)"
          class="px-2.5 py-1 rounded-lg text-xs font-medium transition cursor-pointer"
          :class="activeUnitsList.includes(preset) ? 'bg-slate-200 dark:bg-slate-800 text-slate-400 cursor-not-allowed' : 'bg-slate-100 dark:bg-slate-800/60 hover:bg-theme-light hover:text-theme-primary text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700'"
          :disabled="activeUnitsList.includes(preset)"
        >
          + {{ preset }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Package } from 'lucide-vue-next';

defineProps({
  activeUnitsList: { type: Array, default: () => [] },
  defaultPresets: { type: Array, default: () => [] },
  newUnitInput: { type: String, default: '' },
});

defineEmits(['remove-unit', 'add-unit', 'add-preset', 'update:new-unit']);
</script>
