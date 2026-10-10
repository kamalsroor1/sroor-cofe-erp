<template>
  <header
    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xs font-tajawal transition-colors"
  >
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
      <!-- Title & Navigation Controls -->
      <div class="flex items-center gap-3">
        <!-- Mobile Back Button (to Hub) -->
        <button
          v-if="isMobile && hasSelectedTab"
          type="button"
          @click="$emit('back')"
          class="min-h-[44px] min-w-[44px] px-3 py-2 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 flex items-center justify-center gap-1.5 text-xs font-bold transition active:scale-95 cursor-pointer shadow-xs"
          :title="$t('settings.back_to_hub')"
        >
          <ArrowRight class="w-4 h-4 rtl:rotate-0 ltr:rotate-180" />
          <span class="hidden sm:inline">{{ $t('settings.back_to_hub') }}</span>
        </button>

        <div class="space-y-0.5">
          <div class="flex items-center gap-2">
            <h1 class="text-base sm:text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
              <span>⚙️</span>
              <span>{{ title }}</span>
            </h1>

            <!-- Unsaved Changes Dot -->
            <span
              v-if="hasUnsavedChanges"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 animate-pulse"
              :title="$t('settings.unsaved_changes')"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
              <span>{{ $t('settings.unsaved_changes') }}</span>
            </span>

            <!-- Read-Only Badge -->
            <span
              v-if="!canManage"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700"
              :title="$t('settings.read_only_notice')"
            >
              <Lock class="w-3 h-3" />
              <span>{{ $t('settings.read_only_mode') }}</span>
            </span>
          </div>

          <p v-if="subtitle" class="text-xs text-slate-500 dark:text-slate-400 line-clamp-1">
            {{ subtitle }}
          </p>
        </div>
      </div>

      <!-- Action & Controls Bar -->
      <div class="flex flex-wrap items-center gap-2 sm:gap-3 w-full lg:w-auto justify-between lg:justify-end">
        <!-- Embedded Search Bar -->
        <div class="w-full sm:w-auto flex-1 sm:flex-initial sm:min-w-[280px]">
          <SettingsSearchBar
            :model-value="searchQuery"
            :results="searchResults"
            @update:model-value="$emit('update:searchQuery', $event)"
            @select="$emit('select-search-result', $event)"
          />
        </div>

        <!-- Advanced Settings Toggle Button -->
        <button
          type="button"
          @click="$emit('toggle-advanced')"
          class="min-h-[44px] px-3.5 py-2 rounded-2xl border text-xs font-bold flex items-center gap-2 transition active:scale-95 cursor-pointer shadow-xs select-none"
          :class="
            showAdvanced
              ? 'bg-theme-light border-theme-primary/30 text-theme-primary'
              : 'bg-slate-50 dark:bg-slate-800/80 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'
          "
          :title="showAdvanced ? $t('settings.hide_advanced') : $t('settings.show_advanced')"
        >
          <SlidersHorizontal class="w-4 h-4" />
          <span class="hidden md:inline">
            {{ showAdvanced ? $t('settings.hide_advanced') : $t('settings.show_advanced') }}
          </span>
          <span
            class="w-2 h-2 rounded-full"
            :class="showAdvanced ? 'bg-theme-primary' : 'bg-slate-300 dark:bg-slate-600'"
          ></span>
        </button>

        <!-- Save Button with Spinner & Unsaved changes indicator -->
        <button
          v-if="canManage"
          type="button"
          @click="$emit('save')"
          :disabled="isSaving"
          class="min-h-[44px] px-5 py-2.5 rounded-2xl bg-theme-primary hover:opacity-90 text-white font-black text-xs sm:text-sm flex items-center gap-2 transition shadow-lg shadow-theme-primary/20 active:scale-95 disabled:opacity-50 cursor-pointer select-none"
        >
          <Loader2 v-if="isSaving" class="w-4 h-4 animate-spin" />
          <Save v-else class="w-4 h-4" />
          <span>{{ isSaving ? $t('common.loading') : $t('profile.save_changes') }}</span>
        </button>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ArrowRight, SlidersHorizontal, Save, Loader2, Lock } from 'lucide-vue-next';
import SettingsSearchBar from './SettingsSearchBar.vue';

defineProps({
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
  isMobile: {
    type: Boolean,
    default: false,
  },
  hasSelectedTab: {
    type: Boolean,
    default: false,
  },
  showAdvanced: {
    type: Boolean,
    default: false,
  },
  hasUnsavedChanges: {
    type: Boolean,
    default: false,
  },
  isSaving: {
    type: Boolean,
    default: false,
  },
  canManage: {
    type: Boolean,
    default: true,
  },
  searchQuery: {
    type: String,
    default: '',
  },
  searchResults: {
    type: Array,
    default: () => [],
  },
});

defineEmits(['back', 'toggle-advanced', 'save', 'update:searchQuery', 'select-search-result']);
</script>
