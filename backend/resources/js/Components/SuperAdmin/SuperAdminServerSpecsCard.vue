<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm dark:shadow-lg space-y-4 font-tajawal">
    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
      <h2 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-500 flex items-center justify-center">
          <Cpu class="w-4.5 h-4.5" />
        </div>
        <span>معلومات بيئة السيرفر والنظام المركزي (Central Server Specs)</span>
      </h2>
      <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-[10px] font-bold">
        ● متصل ويعمل بكفاءة
      </span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="p-3.5 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-500 flex items-center justify-center">
            <Code2 class="w-4 h-4" />
          </div>
          <div>
            <span class="text-slate-500 dark:text-slate-400 text-[11px] block">إصدار PHP</span>
            <span class="text-slate-900 dark:text-white font-bold font-mono text-xs">{{ systemInfo.php_version || '8.3+' }}</span>
          </div>
        </div>
        <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-[10px] font-bold font-mono">Active</span>
      </div>

      <div class="p-3.5 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-rose-500/10 text-rose-500 flex items-center justify-center">
            <Layers class="w-4 h-4" />
          </div>
          <div>
            <span class="text-slate-500 dark:text-slate-400 text-[11px] block">إصدار Laravel</span>
            <span class="text-slate-900 dark:text-white font-bold font-mono text-xs">{{ systemInfo.laravel_version || '11.x' }}</span>
          </div>
        </div>
        <span class="px-2 py-0.5 rounded bg-purple-500/10 text-purple-600 dark:text-purple-400 text-[10px] font-bold font-mono">SaaS</span>
      </div>

      <div class="p-3.5 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-cyan-500/10 text-cyan-500 flex items-center justify-center">
            <Database class="w-4 h-4" />
          </div>
          <div>
            <span class="text-slate-500 dark:text-slate-400 text-[11px] block">قاعدة البيانات (DB Engine)</span>
            <span class="text-cyan-600 dark:text-cyan-400 font-bold font-mono text-xs">{{ systemInfo.db_driver || 'MySQL' }} ({{ systemInfo.mysql_version || '8.0' }})</span>
          </div>
        </div>
      </div>

      <div class="p-3.5 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-theme-light text-theme-primary flex items-center justify-center">
            <Server class="w-4 h-4" />
          </div>
          <div>
            <span class="text-slate-500 dark:text-slate-400 text-[11px] block">بيئة التشغيل (Environment)</span>
            <span class="text-theme-primary font-bold font-mono text-xs">{{ systemInfo.environment || 'Production' }}</span>
          </div>
        </div>
        <span class="px-2 py-0.5 rounded bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[10px] font-mono font-bold">LIVE</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Cpu, Code2, Layers, Database, Server } from 'lucide-vue-next';

defineProps({
  systemInfo: { type: Object, default: () => ({}) },
});
</script>
