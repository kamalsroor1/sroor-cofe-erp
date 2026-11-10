<template>
  <div class="space-y-6 max-w-6xl mx-auto font-tajawal pb-12" data-testid="help-view">
    <PageHeader :title="$t('help.title')" :subtitle="$t('help.subtitle')" :icon="LifeBuoy">
      <template #actions>
        <a
          v-if="supportHref"
          :href="supportHref"
          target="_blank"
          rel="noopener noreferrer"
          data-testid="contact-support-btn"
          class="min-h-[44px] px-4 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs flex items-center justify-center gap-2 shadow-sm transition active:scale-95"
        >
          <MessageCircle class="w-4 h-4" />
          <span>{{ $t('help.contact_support') }}</span>
        </a>
        <span
          v-else
          data-testid="contact-support-btn"
          class="min-h-[44px] px-4 py-2.5 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700 font-bold text-xs flex items-center justify-center gap-2"
          :title="$t('help.support_not_configured')"
        >
          <MessageCircle class="w-4 h-4" />
          <span>{{ $t('help.contact_support') }}</span>
        </span>
      </template>
    </PageHeader>

    <HelpSkeleton v-if="isLoading" />

    <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
      <div class="lg:col-span-4">
        <HelpSidebarList
          :articles="filteredArticles"
          :active-slug="activeArticle?.slug || activeSlug"
          v-model:search-query="searchQuery"
          @select="selectArticle"
        />
      </div>

      <div class="lg:col-span-8">
        <HelpArticleViewer v-if="activeArticle" :article="activeArticle" :support-href="supportHref" />
        <EmptyState
          v-else
          :icon="LifeBuoy"
          :title="$t('help.no_articles_title')"
          :description="$t('help.no_articles_desc')"
          :action-label="$t('help.search_clear')"
          @action="searchQuery = ''"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { LifeBuoy, MessageCircle } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import EmptyState from '../../Components/Common/EmptyState.vue';
import HelpSkeleton from '../../Components/Help/HelpSkeleton.vue';
import HelpSidebarList from '../../Components/Help/HelpSidebarList.vue';
import HelpArticleViewer from '../../Components/Help/HelpArticleViewer.vue';
import { useHelpCenter } from '../../Composables/useHelpCenter';
import { useSupportContact } from '../../Composables/useSupportContact';

const { isLoading, searchQuery, filteredArticles, activeSlug, activeArticle, selectArticle } = useHelpCenter();
const { supportHref } = useSupportContact();
</script>
