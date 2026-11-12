<template>
  <section
    class="rounded-2xl border border-theme-border bg-white dark:bg-slate-900 shadow-xs font-tajawal"
    data-testid="setup-checklist"
  >
    <div class="flex items-center gap-1 p-1.5">
      <button
        type="button"
        class="min-h-[44px] flex-1 min-w-0 flex items-center gap-3 px-2.5 rounded-xl text-start cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60 transition"
        data-testid="setup-checklist-toggle"
        :aria-expanded="expanded"
        @click="expanded = !expanded"
      >
        <span class="min-w-0 flex-1 space-y-1.5">
          <span class="block text-xs sm:text-sm font-black text-slate-900 dark:text-white truncate">
            {{ $t('onboarding.collapsed_title', { completed: progress.completed, total: progress.total }) }}
          </span>
          <span class="block h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
            <span
              class="block h-full rounded-full transition-all duration-500"
              style="background-color: var(--color-primary)"
              :style="{ width: progress.percent + '%' }"
            ></span>
          </span>
        </span>
        <ChevronDown class="w-4 h-4 shrink-0 text-slate-400 transition-transform" :class="{ 'rotate-180': expanded }" />
      </button>
      <button
        type="button"
        class="shrink-0 w-11 h-11 rounded-xl flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
        :aria-label="$t('onboarding.dismiss')"
        @click="$emit('dismiss')"
      >
        <X class="w-5 h-5" />
      </button>
    </div>

    <div v-if="expanded" class="px-3 pb-3 sm:px-4 sm:pb-4 space-y-3" data-testid="setup-checklist-steps">
      <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('onboarding.subtitle') }}</p>
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
    </div>
  </section>
</template>

<script setup>
import { ref } from 'vue';
import { Check, ChevronDown, ChevronLeft, ChevronRight, CircleHelp, X } from 'lucide-vue-next';

defineProps({
  steps: { type: Array, default: () => [] },
  progress: { type: Object, required: true },
});

defineEmits(['dismiss']);

// One line by default; the steps open on tap.
const expanded = ref(false);
</script>
