<template>
  <article
    v-if="article"
    data-testid="help-article-viewer"
    class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 sm:p-8 shadow-xs space-y-6 font-tajawal"
  >
    <!-- Article Header -->
    <header class="border-b border-slate-200 dark:border-slate-800 pb-5 space-y-2">
      <span
        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-theme-light text-theme-primary border border-theme-border"
      >
        <BookOpen class="w-3.5 h-3.5" />
        <span>{{ article.categoryLabel }}</span>
      </span>
      <h2 class="text-lg sm:text-2xl font-black text-slate-900 dark:text-white leading-snug">
        {{ article.title }}
      </h2>
      <p
        v-for="(lineTokens, idx) in article.intro"
        :key="'intro-' + idx"
        class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 font-medium leading-relaxed"
      >
        <template v-for="(tok, tIdx) in lineTokens" :key="tIdx">
          <strong v-if="tok.type === 'bold'" class="font-black text-slate-900 dark:text-white">{{ tok.value }}</strong>
          <code
            v-else-if="tok.type === 'code'"
            dir="ltr"
            class="px-1.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 font-mono text-xs text-theme-primary"
            >{{ tok.value }}</code
          >
          <span v-else>{{ tok.value }}</span>
        </template>
      </p>
    </header>

    <!-- Article Sections -->
    <div class="space-y-6">
      <section
        v-for="(sec, sIdx) in article.sections"
        :key="'sec-' + sIdx"
        class="p-4 sm:p-5 rounded-2xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800 space-y-3"
      >
        <h3 v-if="sec.heading" class="text-sm sm:text-base font-black text-slate-900 dark:text-white">
          {{ sec.heading }}
        </h3>

        <p
          v-for="(pTokens, pIdx) in sec.paragraphs"
          :key="'p-' + pIdx"
          class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed"
        >
          <template v-for="(tok, tIdx) in pTokens" :key="tIdx">
            <strong v-if="tok.type === 'bold'" class="font-black text-slate-900 dark:text-white">{{
              tok.value
            }}</strong>
            <code
              v-else-if="tok.type === 'code'"
              dir="ltr"
              class="px-1.5 py-0.5 rounded-md bg-slate-200/70 dark:bg-slate-900 font-mono text-xs text-theme-primary"
              >{{ tok.value }}</code
            >
            <span v-else>{{ tok.value }}</span>
          </template>
        </p>

        <ul v-if="sec.bullets.length > 0" class="space-y-2 ps-1">
          <li
            v-for="(bTokens, bIdx) in sec.bullets"
            :key="'b-' + bIdx"
            class="flex items-start gap-2.5 text-xs sm:text-sm text-slate-700 dark:text-slate-300 leading-relaxed"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-theme-primary mt-2 shrink-0"></span>
            <span>
              <template v-for="(tok, tIdx) in bTokens" :key="tIdx">
                <strong v-if="tok.type === 'bold'" class="font-black text-slate-900 dark:text-white">{{
                  tok.value
                }}</strong>
                <code
                  v-else-if="tok.type === 'code'"
                  dir="ltr"
                  class="px-1.5 py-0.5 rounded-md bg-slate-200/70 dark:bg-slate-900 font-mono text-xs text-theme-primary"
                  >{{ tok.value }}</code
                >
                <span v-else>{{ tok.value }}</span>
              </template>
            </span>
          </li>
        </ul>
      </section>
    </div>

    <!-- Support Footer Banner -->
    <footer
      class="pt-4 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4"
    >
      <div>
        <h4 class="text-xs sm:text-sm font-black text-slate-900 dark:text-white">
          {{ $t('help.support_card_title') }}
        </h4>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          {{ supportHref ? $t('help.support_card_desc') : $t('help.support_not_configured') }}
        </p>
      </div>

      <a
        v-if="supportHref"
        :href="supportHref"
        target="_blank"
        rel="noopener noreferrer"
        data-testid="help-article-support-link"
        class="min-h-[44px] px-4 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs flex items-center justify-center gap-2 shadow-sm transition active:scale-95 shrink-0"
      >
        <MessageCircle class="w-4 h-4" />
        <span>{{ $t('help.contact_support_whatsapp') }}</span>
      </a>
    </footer>
  </article>
</template>

<script setup>
import { BookOpen, MessageCircle } from 'lucide-vue-next';

defineProps({
  article: { type: Object, default: null },
  supportHref: { type: String, default: '' },
});
</script>
