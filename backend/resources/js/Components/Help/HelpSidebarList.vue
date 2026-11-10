<template>
  <aside
    class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 sm:p-5 space-y-4 shadow-xs font-tajawal"
  >
    <!-- Search Input -->
    <div class="relative">
      <Search class="w-4 h-4 text-slate-400 absolute top-1/2 -translate-y-1/2 start-3.5 pointer-events-none" />
      <input
        type="search"
        data-testid="help-search-input"
        :value="searchQuery"
        @input="$emit('update:searchQuery', $event.target.value)"
        :placeholder="$t('help.search_placeholder')"
        class="w-full min-h-[44px] ps-10 pe-9 py-2.5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/80 text-xs sm:text-sm font-bold text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-hidden focus:border-theme-primary transition"
      />
      <button
        v-if="searchQuery"
        type="button"
        @click="$emit('update:searchQuery', '')"
        :aria-label="$t('help.search_clear')"
        class="min-h-[36px] min-w-[36px] absolute top-1/2 -translate-y-1/2 end-1.5 flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl cursor-pointer"
      >
        <X class="w-4 h-4" />
      </button>
    </div>

    <div class="flex items-center justify-between px-1">
      <h2 class="text-xs font-black text-slate-500 dark:text-slate-400">
        {{ $t('help.articles_list_title') }}
      </h2>
      <span
        class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300"
      >
        {{ $t('help.articles_count', { count: articles.length }) }}
      </span>
    </div>

    <!-- Articles Navigation List -->
    <nav v-if="articles.length > 0" class="space-y-1.5" data-testid="help-articles-list">
      <button
        v-for="article in articles"
        :key="article.slug"
        type="button"
        :data-testid="`help-article-item-${article.slug}`"
        @click="$emit('select', article.slug)"
        class="w-full min-h-[44px] px-3.5 py-3 rounded-2xl text-start flex items-center justify-between gap-3 border transition cursor-pointer"
        :class="
          activeSlug === article.slug
            ? 'border-theme-primary bg-theme-light text-theme-primary font-black shadow-2xs'
            : 'border-transparent bg-slate-50/70 dark:bg-slate-800/40 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold'
        "
      >
        <div class="min-w-0">
          <div class="text-xs sm:text-sm truncate">{{ article.title }}</div>
          <div class="text-[11px] text-slate-400 dark:text-slate-500 truncate mt-0.5">
            {{ article.categoryLabel }}
          </div>
        </div>
        <BookOpen class="w-4 h-4 shrink-0 opacity-70" />
      </button>
    </nav>
  </aside>
</template>

<script setup>
import { Search, X, BookOpen } from 'lucide-vue-next';

defineProps({
  articles: { type: Array, default: () => [] },
  activeSlug: { type: String, default: '' },
  searchQuery: { type: String, default: '' },
});

defineEmits(['select', 'update:searchQuery']);
</script>
