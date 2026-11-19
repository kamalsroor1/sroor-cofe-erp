<script setup>
import { ShieldCheck, Trash2, ChevronLeft } from 'lucide-vue-next';

// MOB-05: the app settings link to the privacy policy and the account & data deletion page.
const LINKS = [
  { key: 'privacy', route: 'landing.privacy', icon: ShieldCheck, label: 'landing.nav.privacy' },
  { key: 'deletion', route: 'landing.account_deletion', icon: Trash2, label: 'landing.nav.account_deletion' },
];
</script>

<template>
  <section
    data-testid="settings-legal-links"
    class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
  >
    <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('account_deletion.settings.title') }}</h3>
    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('account_deletion.settings.body') }}</p>
    <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
      <RouterLink
        v-for="link in LINKS"
        :key="link.key"
        :to="{ name: link.route }"
        :data-testid="`settings-legal-link-${link.key}`"
        class="flex min-h-11 items-center gap-3 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
      >
        <component :is="link.icon" class="size-4 shrink-0 text-[var(--color-primary)]" aria-hidden="true" />
        <span class="flex-1">{{ $t(link.label) }}</span>
        <ChevronLeft class="size-4 shrink-0 text-slate-400 ltr:rotate-180" aria-hidden="true" />
      </RouterLink>
    </div>
  </section>
</template>
