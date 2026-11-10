<script setup>
import { AlertTriangle, FileText, RotateCw } from 'lucide-vue-next';

defineProps({
  blocks: { type: Array, default: () => [] },
  version: { type: String, default: '' },
  isDraft: { type: Boolean, default: true },
  isLoading: { type: Boolean, default: false },
  hasError: { type: Boolean, default: false },
});

defineEmits(['retry']);
</script>

<template>
  <div class="space-y-6">
    <!-- Legal draft warning banner -->
    <div
      v-if="isDraft"
      role="status"
      data-testid="legal-draft-banner"
      class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-xs font-bold text-amber-800 dark:text-amber-300"
    >
      <div class="flex items-center gap-2">
        <AlertTriangle class="h-4 w-4 shrink-0" />
        <span>{{ $t('landing.legal.draft_badge') }}</span>
      </div>
      <div v-if="version" class="flex items-center gap-1.5 font-mono text-[11px]">
        <span>{{ $t('landing.legal.version_label') }}</span>
        <span>{{ version }}</span>
      </div>
    </div>

    <!-- Skeleton shimmer loader -->
    <div
      v-if="isLoading"
      class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900 sm:p-8"
    >
      <div class="h-7 w-2/3 animate-pulse rounded-xl bg-slate-200 dark:bg-slate-800" />
      <div class="h-4 w-1/3 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800/70" />
      <div class="space-y-2 pt-3">
        <div class="h-4 w-full animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800/70" />
        <div class="h-4 w-11/12 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800/70" />
        <div class="h-4 w-4/5 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800/70" />
      </div>
      <div class="space-y-2 pt-4">
        <div class="h-5 w-1/2 animate-pulse rounded-lg bg-slate-200 dark:bg-slate-800" />
        <div class="h-4 w-full animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800/70" />
        <div class="h-4 w-5/6 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800/70" />
      </div>
    </div>

    <!-- Error state -->
    <div
      v-else-if="hasError"
      role="alert"
      class="flex flex-col items-center justify-center gap-4 rounded-3xl border border-slate-200 bg-white p-8 text-center dark:border-slate-800 dark:bg-slate-900"
    >
      <FileText class="h-10 w-10 text-slate-400" />
      <p class="text-sm font-bold text-slate-600 dark:text-slate-300">{{ $t('landing.legal.unavailable') }}</p>
      <button
        type="button"
        class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-200 px-4 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
        @click="$emit('retry')"
      >
        <RotateCw class="h-4 w-4" />
        <span>{{ $t('landing.legal.retry') }}</span>
      </button>
    </div>

    <!-- Document blocks (safe text interpolation only, never v-html) -->
    <article
      v-else
      data-testid="legal-document-article"
      class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900 sm:p-8"
    >
      <template v-for="(block, idx) in blocks" :key="idx">
        <h1 v-if="block.type === 'h1'" class="text-xl font-black text-slate-900 dark:text-white sm:text-2xl">
          {{ block.text }}
        </h1>
        <h2 v-else-if="block.type === 'h2'" class="pt-3 text-base font-black text-slate-900 dark:text-white sm:text-lg">
          {{ block.text }}
        </h2>
        <h3 v-else-if="block.type === 'h3'" class="pt-2 text-sm font-black text-slate-800 dark:text-slate-100">
          {{ block.text }}
        </h3>
        <blockquote
          v-else-if="block.type === 'quote'"
          class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-bold text-slate-700 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-200"
        >
          {{ block.text }}
        </blockquote>
        <ul
          v-else-if="block.type === 'list'"
          class="list-inside list-disc space-y-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300"
        >
          <li v-for="(item, itemIdx) in block.items" :key="itemIdx">{{ item }}</li>
        </ul>
        <p v-else class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
          {{ block.text }}
        </p>
      </template>
    </article>

    <!-- Switch between the three legal documents -->
    <nav class="flex flex-wrap items-center justify-between gap-3 pt-2 text-xs font-bold">
      <div class="flex flex-wrap items-center gap-2">
        <RouterLink
          :to="{ name: 'landing.terms' }"
          class="inline-flex min-h-11 items-center rounded-xl border border-slate-200 bg-white px-3.5 text-slate-700 hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
        >
          {{ $t('landing.nav.terms') }}
        </RouterLink>
        <RouterLink
          :to="{ name: 'landing.privacy' }"
          class="inline-flex min-h-11 items-center rounded-xl border border-slate-200 bg-white px-3.5 text-slate-700 hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
        >
          {{ $t('landing.nav.privacy') }}
        </RouterLink>
        <RouterLink
          :to="{ name: 'landing.dpa' }"
          class="inline-flex min-h-11 items-center rounded-xl border border-slate-200 bg-white px-3.5 text-slate-700 hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
        >
          {{ $t('landing.nav.dpa') }}
        </RouterLink>
      </div>
      <RouterLink
        :to="{ name: 'landing.signup' }"
        class="inline-flex min-h-11 items-center rounded-xl px-4 text-white"
        style="background-color: var(--color-primary)"
      >
        {{ $t('landing.legal.back_signup') }}
      </RouterLink>
    </nav>
  </div>
</template>
