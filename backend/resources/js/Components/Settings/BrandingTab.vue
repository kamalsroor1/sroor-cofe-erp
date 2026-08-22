<script setup>
import { ref } from 'vue';
import { Building2, Sun, Moon, Save } from 'lucide-vue-next';

const props = defineProps({
    form: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['save']);

const logoLightPreview = ref(null);
const logoDarkPreview = ref(null);

const handleLogoLightChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        props.form.logo_light_file = file;
        logoLightPreview.value = URL.createObjectURL(file);
    }
};

const handleLogoDarkChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        props.form.logo_dark_file = file;
        logoDarkPreview.value = URL.createObjectURL(file);
    }
};
</script>

<template>
    <div class="space-y-6 font-tajawal">
        <form @submit.prevent="$emit('save')" class="space-y-6">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-6">
                <!-- Dual Logo Upload Section (Light Mode & Dark Mode) -->
                <div class="pb-6 border-b border-slate-200 dark:border-slate-800 space-y-4">
                    <div>
                        <h3 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <Building2 class="w-4 h-4 text-theme-primary" />
                            <span>{{ $t('settings.company_logo') }}</span>
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $t('settings.logo_hint') }}</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- ☀️ Light Mode Logo Card -->
                        <div class="p-4 rounded-3xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 flex items-center gap-4 shadow-xs">
                            <div class="w-20 h-20 rounded-2xl bg-white border border-slate-200 p-2 flex items-center justify-center overflow-hidden shadow-xs shrink-0">
                                <img
                                    :src="logoLightPreview || '/logo-light.png'"
                                    :alt="$t('settings.company_logo_light')"
                                    class="w-full h-full object-contain"
                                >
                            </div>

                            <div class="space-y-1.5 flex-1 min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <Sun class="w-4 h-4 text-theme-primary" />
                                    <h4 class="text-xs font-black text-slate-900 dark:text-white truncate">{{ $t('settings.company_logo_light') }}</h4>
                                </div>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-tight">{{ $t('settings.logo_light_hint') }}</p>
                                <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold border border-slate-200 dark:border-slate-700 cursor-pointer transition shadow-xs">
                                    <span>{{ $t('settings.choose_logo_light') }}</span>
                                    <input type="file" accept="image/*" @change="handleLogoLightChange" class="hidden">
                                </label>
                            </div>
                        </div>

                        <!-- 🌙 Dark Mode Logo Card -->
                        <div class="p-4 rounded-3xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 flex items-center gap-4 shadow-xs">
                            <div class="w-20 h-20 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-2 flex items-center justify-center overflow-hidden shadow-xs shrink-0">
                                <img
                                    :src="logoDarkPreview || '/logo-dark.png'"
                                    :alt="$t('settings.company_logo_dark')"
                                    class="w-full h-full object-contain"
                                >
                            </div>

                            <div class="space-y-1.5 flex-1 min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <Moon class="w-4 h-4 text-indigo-400" />
                                    <h4 class="text-xs font-black text-slate-900 dark:text-white truncate">{{ $t('settings.company_logo_dark') }}</h4>
                                </div>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-tight">{{ $t('settings.logo_dark_hint') }}</p>
                                <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold border border-slate-200 dark:border-slate-700 cursor-pointer transition shadow-xs">
                                    <span>{{ $t('settings.choose_logo_dark') }}</span>
                                    <input type="file" accept="image/*" @change="handleLogoDarkChange" class="hidden">
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Basic Information -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('settings.company_name') }}</label>
                        <input
                            v-model="form.company_name"
                            type="text"
                            required
                            class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-theme-primary focus:outline-none shadow-inner"
                        >
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('settings.company_subtitle') }}</label>
                        <input
                            v-model="form.company_subtitle"
                            type="text"
                            class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-theme-primary focus:outline-none shadow-inner"
                        >
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('settings.company_phone') }}</label>
                        <input
                            v-model="form.company_phone"
                            type="text"
                            class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white font-mono placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-theme-primary focus:outline-none shadow-inner"
                        >
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('settings.company_address') }}</label>
                        <input
                            v-model="form.company_address"
                            type="text"
                            class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-theme-primary focus:outline-none shadow-inner"
                        >
                    </div>

                    <div class="sm:col-span-2 space-y-1.5">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('settings.invoice_footer') }}</label>
                        <textarea
                            v-model="form.invoice_footer_note"
                            rows="2"
                            class="w-full p-4 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-theme-primary focus:outline-none shadow-inner"
                        ></textarea>
                    </div>
                </div>

                <!-- Printing Toggles Matrix -->
                <div class="space-y-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <h3 class="text-xs font-black text-theme-primary">{{ $t('settings.print_options_title') }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="flex items-center gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-theme-primary cursor-pointer transition active:scale-98 min-h-[48px]">
                            <input type="checkbox" v-model="form.show_print_logo" class="w-4 h-4 rounded accent-theme-primary focus:ring-0">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('settings.show_print_logo') }}</span>
                        </label>

                        <label class="flex items-center gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-theme-primary cursor-pointer transition active:scale-98 min-h-[48px]">
                            <input type="checkbox" v-model="form.show_print_company_name" class="w-4 h-4 rounded accent-theme-primary focus:ring-0">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('settings.show_print_name') }}</span>
                        </label>

                        <label class="flex items-center gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-theme-primary cursor-pointer transition active:scale-98 min-h-[48px]">
                            <input type="checkbox" v-model="form.thermal_show_customer_balance" class="w-4 h-4 rounded accent-theme-primary focus:ring-0">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('settings.show_thermal_balance') }}</span>
                        </label>

                        <label class="flex items-center gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-theme-primary cursor-pointer transition active:scale-98 min-h-[48px]">
                            <input type="checkbox" v-model="form.print_show_qr" class="w-4 h-4 rounded accent-theme-primary focus:ring-0">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('settings.show_qr') }}</span>
                        </label>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end pt-4 border-t border-slate-200 dark:border-slate-800">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full sm:w-auto h-12 px-8 rounded-2xl btn-primary-theme font-black text-xs sm:text-sm transition transform active:scale-95 cursor-pointer disabled:opacity-50 flex items-center justify-center gap-2 shadow-theme-primary"
                    >
                        <Save class="w-4 h-4" />
                        <span>{{ form.processing ? $t('common.save') : $t('settings.save_branding_btn') }}</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>
