<template>
  <section
    class="rounded-3xl border border-theme-border bg-white dark:bg-slate-900 p-4 sm:p-5 space-y-4 shadow-sm font-tajawal"
    data-testid="setup-checklist"
  >
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <h2 class="text-sm sm:text-base font-black text-slate-900 dark:text-white">{{ $t('onboarding.title') }}</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $t('onboarding.subtitle') }}</p>
      </div>
      <button
        type="button"
        class="shrink-0 w-11 h-11 -mt-2 -me-2 rounded-2xl flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
        :aria-label="$t('onboarding.dismiss')"
        @click="$emit('dismiss')"
      >
        <X class="w-5 h-5" />
      </button>
    </div>

    <div class="space-y-1.5">
      <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
        {{ $t('onboarding.progress', { completed: progress.completed, total: progress.total }) }}
      </div>
      <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
        <div
          class="h-full rounded-full transition-all duration-500"
          style="background-color: var(--color-primary)"
          :style="{ width: progress.percent + '%' }"
        ></div>
      </div>
    </div>

    <ul class="grid grid-cols-1 md:grid-cols-2 gap-2">
      <li
        v-for="step in steps"
        :key="step.key"
        class="flex items-stretch gap-1.5 rounded-2xl border p-1.5 transition"
        :class="
          step.done
            ? 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40'
            : 'border-theme-border bg-slate-50 dark:bg-slate-800/70 hover:bg-theme-light dark:hover:bg-slate-800'
        "
      >
        <component
          :is="step.done ? 'div' : 'router-link'"
          :to="step.done ? undefined : step.to"
          class="min-h-[44px] flex-1 flex items-center gap-3 px-2 py-1.5 rounded-xl transition"
          :class="step.done ? '' : 'cursor-pointer active:scale-[0.99]'"
        >
          <span
            class="w-6 h-6 shrink-0 rounded-full flex items-center justify-center"
            :class="step.done ? 'bg-emerald-500 text-white' : 'border-2 border-slate-300 dark:border-slate-600'"
          >
            <Check v-if="step.done" class="w-4 h-4" />
          </span>
          <span class="min-w-0 flex-1">
            <span
              class="block text-xs sm:text-sm font-black"
              :class="step.done ? 'text-slate-400 line-through' : 'text-slate-900 dark:text-white'"
            >
              {{ $t('onboarding.steps.' + step.key) }}
            </span>
            <span v-if="!step.done" class="block text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
              {{ $t('onboarding.steps.' + step.key + '_hint') }}
            </span>
          </span>
          <ChevronLeft v-if="!step.done" class="w-4 h-4 shrink-0 text-theme-primary rtl:block ltr:hidden" />
          <ChevronRight v-if="!step.done" class="w-4 h-4 shrink-0 text-theme-primary ltr:block rtl:hidden" />
        </component>

        <router-link
          v-if="step.helpSlug"
          :to="{ path: '/help', query: { article: step.helpSlug } }"
          :data-testid="`checklist-help-${step.key}`"
          :title="$t('help.checklist_help_label')"
          :aria-label="$t('help.checklist_help_label')"
          class="min-h-[44px] min-w-[44px] shrink-0 rounded-xl flex items-center justify-center text-slate-400 hover:text-theme-primary hover:bg-white dark:hover:bg-slate-900 transition"
        >
          <CircleHelp class="w-4 h-4" />
        </router-link>
      </li>
    </ul>
  </section>
</template>

<script setup>
import { Check, ChevronLeft, ChevronRight, CircleHelp, X } from 'lucide-vue-next';

defineProps({
  steps: { type: Array, default: () => [] },
  progress: { type: Object, required: true },
});

defineEmits(['dismiss']);
</script>
