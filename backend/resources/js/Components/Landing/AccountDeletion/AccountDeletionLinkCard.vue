<script setup>
import { Trash2 } from 'lucide-vue-next';
</script>

<template>
  <RouterLink
    :to="{ name: 'landing.account_deletion' }"
    data-testid="account-deletion-link"
    class="flex min-h-11 items-center gap-4 rounded-3xl border border-mk-line bg-mk-surface p-5 transition hover:bg-mk-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mk-primary"
  >
    <span
      class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-rose-500/10 text-rose-600 dark:text-rose-400"
    >
      <Trash2 class="size-5" aria-hidden="true" />
    </span>
    <span class="flex flex-col gap-0.5">
      <span class="text-base font-bold text-mk-ink">{{ $t('account_deletion.related.title') }}</span>
      <span class="text-sm text-mk-muted">{{ $t('account_deletion.related.body') }}</span>
      <span class="text-sm font-bold text-mk-primary underline underline-offset-4">{{
        $t('account_deletion.related.link')
      }}</span>
    </span>
  </RouterLink>
</template>
