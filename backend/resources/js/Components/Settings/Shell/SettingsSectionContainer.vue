<template>
  <section
    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-7 shadow-xs space-y-6 font-tajawal transition-colors"
  >
    <!-- Header -->
    <header
      class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800 gap-3"
    >
      <div class="flex items-center gap-3.5">
        <div
          v-if="icon"
          class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0"
          :class="iconBg || 'bg-theme-light border border-theme-border'"
        >
          <component :is="icon" class="w-5 h-5" :class="iconColor || 'text-theme-primary'" />
        </div>

        <div>
          <div class="flex items-center gap-2">
            <h2 class="text-base sm:text-lg font-black text-slate-900 dark:text-white">
              {{ title }}
            </h2>
            <span
              v-if="badge"
              class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400"
            >
              {{ badge }}
            </span>
          </div>

          <p v-if="subtitle" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            {{ subtitle }}
          </p>
        </div>
      </div>

      <!-- Action items in header: Modified badge, Reset to default, Section save -->
      <div class="flex items-center gap-2 self-end sm:self-auto">
        <!-- Modified-from-default badge & restore button -->
        <div v-if="isModified" class="flex items-center gap-1.5">
          <span
            class="px-2.5 py-1 rounded-xl text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 flex items-center gap-1"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
            <span>{{ $t('settings.modified_from_default') }}</span>
          </span>

          <button
            v-if="canManage"
            type="button"
            @click="$emit('reset-default')"
            class="min-h-[36px] px-2.5 py-1 rounded-xl text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-bold transition active:scale-95 cursor-pointer"
            :title="$t('settings.reset_to_default')"
          >
            <RotateCcw class="w-3.5 h-3.5 inline me-1" />
            <span>{{ $t('settings.reset_to_default') }}</span>
          </button>
        </div>

        <!-- Section Save Button -->
        <button
          v-if="showSave && canManage"
          type="button"
          @click="$emit('save')"
          :disabled="isSaving"
          class="min-h-[44px] px-4 py-2 rounded-2xl bg-theme-primary hover:opacity-90 text-white font-bold text-xs flex items-center gap-2 transition shadow-md shadow-theme-primary/20 active:scale-95 disabled:opacity-50 cursor-pointer select-none"
        >
          <Loader2 v-if="isSaving" class="w-3.5 h-3.5 animate-spin" />
          <Save v-else class="w-3.5 h-3.5" />
          <span>{{ isSaving ? $t('common.loading') : $t('profile.save_changes') }}</span>
        </button>
      </div>
    </header>

    <!-- Slot for Section Form Fields -->
    <div class="space-y-6">
      <slot />
    </div>
  </section>
</template>

<script setup>
import { Save, Loader2, RotateCcw } from 'lucide-vue-next';

defineProps({
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
  icon: {
    type: [Object, Function],
    default: null,
  },
  iconBg: {
    type: String,
    default: '',
  },
  iconColor: {
    type: String,
    default: '',
  },
  badge: {
    type: String,
    default: '',
  },
  isModified: {
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
  showSave: {
    type: Boolean,
    default: true,
  },
});

defineEmits(['save', 'reset-default']);
</script>
