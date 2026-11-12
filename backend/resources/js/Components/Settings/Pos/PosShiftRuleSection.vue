<script setup>
import { onMounted } from 'vue';
import { AlertCircle, Clock } from 'lucide-vue-next';
import BaseButton from '../../Common/BaseButton.vue';
import { useBranchShiftRules } from '../../../Composables/useBranchShiftRules';
import { useAuthStore } from '../../../stores/auth';

const authStore = useAuthStore();
const { available, branches, isLoading, loadFailed, load, setRequired } = useBranchShiftRules();

onMounted(load);
</script>

<template>
  <div
    v-if="available()"
    class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 shadow-xs dark:shadow-xl space-y-5 font-tajawal transition-colors"
    data-testid="settings-shift-rule"
  >
    <div class="flex items-center gap-3 border-b border-slate-200 dark:border-slate-800 pb-4">
      <div
        class="w-10 h-10 rounded-2xl bg-theme-light border border-theme-border text-theme-primary flex items-center justify-center shrink-0"
      >
        <Clock class="w-5 h-5" aria-hidden="true" />
      </div>
      <div class="min-w-0 flex-1">
        <h2 class="text-base font-black text-slate-900 dark:text-white">{{ $t('settings.shift_rule_title') }}</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('settings.shift_rule_sub') }}</p>
      </div>
      <router-link
        v-if="authStore.can('stores.manage')"
        to="/stores"
        class="inline-flex min-h-[44px] shrink-0 items-center px-3 text-xs font-bold text-theme-primary"
        data-testid="settings-shift-rule-stores-link"
      >
        {{ $t('settings.shift_rule_manage_branches') }}
      </router-link>
    </div>

    <div v-if="isLoading" class="space-y-3" aria-hidden="true">
      <div v-for="n in 2" :key="n" class="h-[68px] rounded-2xl bg-slate-100 dark:bg-slate-800 animate-pulse"></div>
    </div>

    <div v-else-if="loadFailed" role="alert" class="flex flex-wrap items-center gap-3 text-xs font-bold text-rose-600">
      <span>{{ $t('settings.shift_rule_load_failed') }}</span>
      <BaseButton type="button" variant="outline" size="sm" class="min-h-[44px]" @click="load">
        {{ $t('common.retry') }}
      </BaseButton>
    </div>

    <p v-else-if="branches.length === 0" class="text-xs font-medium text-slate-500 dark:text-slate-400">
      {{ $t('settings.shift_rule_no_branches') }}
    </p>

    <ul v-else class="space-y-3">
      <li v-for="branch in branches" :key="branch.id" :data-testid="`shift-rule-row-${branch.id}`">
        <div
          class="flex min-h-[44px] items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700/80 dark:bg-slate-800/70"
        >
          <div class="min-w-0">
            <p class="truncate text-sm font-black text-slate-900 dark:text-white">{{ branch.name }}</p>
            <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">
              {{
                branch.unavailable
                  ? $t('settings.shift_rule_branch_unavailable')
                  : $t('settings.shift_rule_branch_desc')
              }}
            </p>
          </div>
          <button
            type="button"
            role="switch"
            :aria-checked="branch.required"
            :aria-label="$t('settings.shift_rule_switch_label', { name: branch.name })"
            :disabled="branch.isSaving || branch.unavailable"
            :data-testid="`shift-rule-switch-${branch.id}`"
            class="relative flex h-11 w-14 shrink-0 cursor-pointer items-center justify-center disabled:cursor-not-allowed disabled:opacity-50"
            @click="setRequired(branch, !branch.required)"
          >
            <span
              class="relative block h-6 w-12 rounded-full p-0.5 transition-colors"
              :class="branch.required ? 'bg-theme-primary' : 'bg-slate-300 dark:bg-slate-700'"
            >
              <span
                class="block h-5 w-5 rounded-full bg-white shadow-md transition-transform"
                :class="branch.required ? 'ltr:translate-x-6 rtl:-translate-x-6' : 'translate-x-0'"
              ></span>
            </span>
          </button>
        </div>
        <p
          v-if="branch.error"
          role="alert"
          :data-testid="`shift-rule-error-${branch.id}`"
          class="mt-1.5 flex items-start gap-1.5 text-xs font-bold text-rose-500 dark:text-rose-400"
        >
          <AlertCircle class="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
          <span>{{ branch.error }}</span>
        </p>
      </li>
    </ul>
  </div>
</template>
