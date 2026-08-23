<template>
  <div class="p-4 bg-purple-500/10 border border-purple-500/20 rounded-2xl flex items-center gap-3 text-xs text-purple-600 dark:text-purple-300 font-tajawal">
    <ShieldAlert class="w-5 h-5 shrink-0" />
    <span>{{ $t('roles.admin_role_notice') }}</span>
  </div>
</template>

<script setup>
import { ShieldAlert } from 'lucide-vue-next';
</script>
