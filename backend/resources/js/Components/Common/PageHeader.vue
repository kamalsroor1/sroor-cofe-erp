<script setup>
import { ArrowRight, CircleHelp } from 'lucide-vue-next';
import DynamicIcon from './DynamicIcon.vue';

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
    type: [Object, Function, String],
    default: null,
  },
  badge: {
    type: String,
    default: '',
  },
  backHref: {
    type: String,
    default: '',
  },
  helpSlug: {
    type: String,
    default: '',
  },
});
</script>

<template>
  <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 font-tajawal" data-testid="page-header">
    <div class="space-y-1 min-w-0 flex-1 basis-72">
      <div class="flex items-center gap-2.5 flex-wrap">
        <!-- Back button -->
        <router-link
          v-if="backHref"
          :to="backHref"
          class="w-10 h-10 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center font-bold text-sm transition active:scale-90 shadow-xs border border-slate-200 dark:border-transparent shrink-0"
        >
          <ArrowRight class="ltr:rotate-180 w-4 h-4" />
        </router-link>

        <div
          v-else-if="icon"
          class="w-10 h-10 rounded-2xl bg-theme-light border border-theme-primary/20 flex items-center justify-center text-theme-primary shadow-xs shrink-0"
        >
          <DynamicIcon :name="icon" class="w-5 h-5 text-theme-primary" />
        </div>

        <h1 class="text-xl sm:text-2xl font-black text-balance text-slate-900 dark:text-white tracking-tight">
          {{ title }}
        </h1>

        <span
          v-if="badge"
          class="px-2.5 py-0.5 rounded-full text-xs font-black bg-theme-light text-theme-primary border border-theme-primary/30"
        >
          {{ badge }}
        </span>

        <router-link
          v-if="helpSlug"
          :to="{ path: '/help', query: { article: helpSlug } }"
          data-testid="contextual-help-btn"
          :title="$t('help.contextual_button_label')"
          :aria-label="$t('help.contextual_button_label')"
          class="min-h-[44px] min-w-[44px] rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 hover:text-theme-primary border border-slate-200 dark:border-slate-700 flex items-center justify-center transition active:scale-95 shrink-0"
        >
          <CircleHelp class="w-4 h-4" />
        </router-link>
      </div>

      <p v-if="subtitle" class="text-xs text-slate-500 dark:text-slate-400 font-bold leading-relaxed">
        {{ subtitle }}
      </p>
    </div>

    <!-- Action Buttons Slot -->
    <div
      v-if="$slots.actions || $slots.default"
      class="flex items-center gap-2 flex-wrap max-w-full justify-start sm:justify-end"
    >
      <slot name="actions">
        <slot />
      </slot>
    </div>
  </div>
</template>
