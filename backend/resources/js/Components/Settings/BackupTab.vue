<script setup>
import { HardDrive, Send } from 'lucide-vue-next';

defineEmits(['send-backup-telegram']);
</script>

<template>
    <div class="space-y-6 font-tajawal">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xs space-y-6">
            <div class="border-b border-slate-200 dark:border-slate-800 pb-4">
                <h2 class="text-sm sm:text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <HardDrive class="w-4 h-4 text-theme-primary" />
                    <span>{{ $t('settings.backup_title') }}</span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $t('settings.backup_sub') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                <div class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 sm:p-5 space-y-3 flex flex-col justify-between shadow-xs">
                    <div class="space-y-2">
                        <h3 class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <HardDrive class="w-4 h-4 text-indigo-500" />
                            <span>{{ $t('settings.direct_download_title') }}</span>
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            {{ $t('settings.direct_download_desc') }}
                        </p>
                    </div>

                    <a
                        href="/settings/backup/download"
                        class="w-full h-11 px-6 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-md shadow-indigo-600/30 transition active:scale-95 cursor-pointer mt-2"
                    >
                        <HardDrive class="w-4 h-4" />
                        <span>{{ $t('settings.download_sql_btn') }}</span>
                    </a>
                </div>

                <div class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 sm:p-5 space-y-3 flex flex-col justify-between shadow-xs">
                    <div class="space-y-2">
                        <h3 class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <Send class="w-4 h-4 text-sky-500" />
                            <span>{{ $t('settings.backup_to_telegram_title') }}</span>
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            {{ $t('settings.backup_to_telegram_desc') }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="w-full h-11 px-6 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-bold text-xs flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer shadow-xs mt-2"
                        @click="$emit('send-backup-telegram')"
                    >
                        <Send class="w-4 h-4 text-sky-500" />
                        <span>{{ $t('settings.send_to_telegram_btn') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
