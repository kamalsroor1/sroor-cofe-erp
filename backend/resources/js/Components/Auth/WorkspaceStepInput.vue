<template>
  <div class="space-y-6 font-sans" dir="rtl">
    <!-- Header / Brand Icon -->
    <div class="text-center space-y-3">
      <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-gradient-to-tr from-amber-500/20 to-amber-600/10 border border-theme-border text-theme-primary shadow-2xl shadow-amber-500/10">
        <Store class="w-10 h-10" />
      </div>
      <div>
        <h1 class="text-2xl font-black text-slate-900 dark:text-white font-tajawal tracking-tight">
          {{ $t('auth.workspace_selection_title') }}
        </h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 font-bold mt-1 max-w-xs mx-auto leading-relaxed">
          {{ $t('auth.workspace_selection_subtitle') }}
        </p>
      </div>
    </div>

    <!-- Error Alert -->
    <div v-if="errorMessage" class="p-3.5 bg-rose-500/10 border border-rose-500/20 rounded-2xl text-xs text-rose-500 dark:text-rose-400 font-bold flex items-center gap-2">
      <AlertTriangle class="w-4 h-4 shrink-0" />
      <span>{{ errorMessage }}</span>
    </div>

    <!-- Input Form -->
    <form @submit.prevent="$emit('submit')" class="space-y-4">
      <div class="space-y-1.5">
        <label for="workspaceCode" class="block text-xs font-bold text-slate-700 dark:text-slate-300 font-tajawal text-right">
          {{ $t('auth.workspace_code') }}
        </label>
        <div class="relative">
          <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-400">
            <Building class="w-4 h-4" />
          </div>
          <input
            id="workspaceCode"
            type="text"
            :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)"
            :placeholder="$t('auth.workspace_code_placeholder')"
            :disabled="isLoading"
            autocomplete="off"
            autofocus
            dir="ltr"
            class="w-full h-12 pr-10 pl-4 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80 rounded-2xl text-slate-900 dark:text-white font-mono text-base font-bold placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-theme-primary focus:border-transparent transition-all uppercase tracking-wider text-center"
          />
        </div>
      </div>

      <!-- Quick Hint / Example -->
      <div class="flex items-center justify-between text-[11px] text-slate-400 px-1 font-tajawal">
        <span>{{ $t('auth.click_to_fill') }}:</span>
        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="$emit('update:modelValue', '2m')"
            class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-theme-light dark:hover:bg-theme-light text-slate-600 dark:text-slate-300 hover:text-theme-primary font-mono font-bold text-xs transition cursor-pointer"
          >
            2M
          </button>
        </div>
      </div>

      <!-- Submit Button -->
      <button
        type="submit"
        :disabled="isLoading || !modelValue.trim()"
        class="w-full h-12 bg-theme-gradient text-white font-black shadow-theme-primary text-sm rounded-2xl shadow-xl flex items-center justify-center gap-2 transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer font-tajawal"
      >
        <template v-if="isLoading">
          <div class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
          <span>{{ $t('auth.workspace_connecting') }}</span>
        </template>
        <template v-else>
          <ArrowLeft class="w-5 h-5" />
          <span>{{ $t('auth.workspace_continue') }}</span>
        </template>
      </button>
    </form>

  </div>
</template>

<script setup>
import {
    Store,
    Building,
    AlertTriangle,
    ArrowLeft,
} from 'lucide-vue-next';

defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    isLoading: {
        type: Boolean,
        default: false,
    },
    errorMessage: {
        type: String,
        default: '',
    },
});

defineEmits(['update:modelValue', 'submit', 'centralLogin']);
</script>
