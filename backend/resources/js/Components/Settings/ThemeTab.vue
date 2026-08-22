<script setup>
import { ref, computed } from 'vue';
import { Palette, Paintbrush, Check, Eye, Zap, CheckCircle2 } from 'lucide-vue-next';
import { useTheme } from '@/Composables/useTheme';
import { trans } from '@/helpers/trans';

const props = defineProps({
    form: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['save']);

const { applyColorTheme } = useTheme();

const palettes = computed(() => [
    { id: 'amber', name: trans('settings.palette_amber_name'), sub: trans('settings.palette_amber_sub'), hex: '#f59e0b', ring: 'ring-amber-500', bg: 'bg-amber-500', icon: '🌟' },
    { id: 'emerald', name: trans('settings.palette_emerald_name'), sub: trans('settings.palette_emerald_sub'), hex: '#10b981', ring: 'ring-emerald-500', bg: 'bg-emerald-500', icon: '🌿' },
    { id: 'blue', name: trans('settings.palette_blue_name'), sub: trans('settings.palette_blue_sub'), hex: '#3b82f6', ring: 'ring-blue-500', bg: 'bg-blue-500', icon: '🔵' },
    { id: 'purple', name: trans('settings.palette_purple_name'), sub: trans('settings.palette_purple_sub'), hex: '#a855f7', ring: 'ring-purple-500', bg: 'bg-purple-500', icon: '🟣' },
    { id: 'rose', name: trans('settings.palette_rose_name'), sub: trans('settings.palette_rose_sub'), hex: '#f43f5e', ring: 'ring-rose-500', bg: 'bg-rose-500', icon: '🌹' },
    { id: 'orange', name: trans('settings.palette_orange_name'), sub: trans('settings.palette_orange_sub'), hex: '#f97316', ring: 'ring-orange-500', bg: 'bg-orange-500', icon: '☕' },
    { id: 'teal', name: trans('settings.palette_teal_name'), sub: trans('settings.palette_teal_sub'), hex: '#14b8a6', ring: 'ring-teal-500', bg: 'bg-teal-500', icon: '🌊' },
    { id: 'indigo', name: trans('settings.palette_indigo_name'), sub: trans('settings.palette_indigo_sub'), hex: '#6366f1', ring: 'ring-indigo-500', bg: 'bg-indigo-500', icon: '🌌' },
]);

const extendedSwatches = [
    { hex: '#06b6d4', name: 'Cyan' },
    { hex: '#84cc16', name: 'Lime' },
    { hex: '#ec4899', name: 'Pink' },
    { hex: '#e11d48', name: 'Crimson' },
    { hex: '#8b5cf6', name: 'Violet' },
    { hex: '#0ea5e9', name: 'Sky' },
    { hex: '#10b981', name: 'Emerald' },
    { hex: '#eab308', name: 'Yellow' },
    { hex: '#d97706', name: 'Bronze' },
    { hex: '#64748b', name: 'Slate' },
    { hex: '#14b8a6', name: 'Mint' },
    { hex: '#f97316', name: 'Coral' },
];

const isPreset = computed(() => {
    return palettes.value.some(p => p.id === props.form.system_theme_color);
});

const activeHexColor = computed(() => {
    const preset = palettes.value.find(p => p.id === props.form.system_theme_color);
    if (preset) return preset.hex;
    if (props.form.system_theme_color && props.form.system_theme_color.startsWith('#')) return props.form.system_theme_color;
    return '#f59e0b';
});

const customPickerColor = ref(activeHexColor.value);

const selectPalette = (paletteId) => {
    props.form.system_theme_color = paletteId;
    const preset = palettes.value.find(p => p.id === paletteId);
    if (preset) customPickerColor.value = preset.hex;
    applyColorTheme(paletteId);
};

const onCustomColorInput = (e) => {
    const newHex = e.target.value;
    customPickerColor.value = newHex;
    props.form.system_theme_color = newHex;
    applyColorTheme(newHex);
};

const onHexTextInput = (val) => {
    if (!val) return;
    let hex = val.trim();
    if (!hex.startsWith('#')) hex = `#${hex}`;
    customPickerColor.value = hex;
    props.form.system_theme_color = hex;
    applyColorTheme(hex);
};
</script>

<template>
    <div class="space-y-6 font-tajawal">
        <form @submit.prevent="$emit('save')" class="space-y-6">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-6">
                <div class="border-b border-slate-200 dark:border-slate-800 pb-4">
                    <h2 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🎨</span>
                        <span>{{ $t('settings.theme_title') }}</span>
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $t('settings.theme_sub') }}</p>
                </div>

                <!-- Palettes Grid -->
                <div class="space-y-3">
                    <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('settings.palette_select_title') }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                        <div
                            v-for="p in palettes"
                            :key="p.id"
                            @click="selectPalette(p.id)"
                            class="p-4 rounded-2xl border-2 cursor-pointer transition-all duration-200 relative group flex flex-col justify-between gap-3 shadow-xs"
                            :style="form.system_theme_color === p.id ? { borderColor: p.hex, backgroundColor: `${p.hex}15`, boxShadow: `0 0 0 2px ${p.hex}30` } : {}"
                            :class="form.system_theme_color === p.id
                                ? ''
                                : 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/60 hover:border-slate-300 dark:hover:border-slate-700'"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <span class="text-xl">{{ p.icon }}</span>
                                    <div class="w-6 h-6 rounded-full shadow-sm border border-white/20 shrink-0" :style="{ backgroundColor: p.hex }"></div>
                                </div>
                                <span v-if="form.system_theme_color === p.id" class="w-5 h-5 rounded-full text-white font-black text-xs flex items-center justify-center shadow-xs" :style="{ backgroundColor: p.hex }">
                                    ✓
                                </span>
                            </div>

                            <div>
                                <h4 class="font-black text-xs text-slate-900 dark:text-white">{{ p.name }}</h4>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-tight">{{ p.sub }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 🎨 Custom Color Picker & Color Swatches -->
                <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-3">
                        <div>
                            <h3 class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-2">
                                <Paintbrush class="w-4 h-4 text-theme-primary" />
                                <span>{{ $t('settings.custom_color_title') }}</span>
                            </h3>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $t('settings.custom_color_sub') }}</p>
                        </div>
                        <span v-if="!isPreset" class="px-2.5 py-1 rounded-xl text-[11px] font-black bg-theme-light text-theme-primary border border-theme-light self-start sm:self-auto flex items-center gap-1">
                            <Check class="w-3.5 h-3.5" />
                            <span>{{ $t('settings.custom_color_badge') }}</span>
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <!-- Interactive Color Wheel / Native Input -->
                        <div class="flex items-center gap-3 bg-white dark:bg-slate-900 p-2.5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
                            <div class="relative w-10 h-10 rounded-xl overflow-hidden shadow-xs cursor-pointer border border-slate-300 dark:border-slate-700 flex items-center justify-center">
                                <input
                                    type="color"
                                    :value="activeHexColor"
                                    @input="onCustomColorInput"
                                    class="absolute -inset-4 w-20 h-20 opacity-0 cursor-pointer"
                                    :title="$t('settings.custom_color_label')"
                                >
                                <div class="w-full h-full rounded-xl transition-transform hover:scale-110" :style="{ backgroundColor: activeHexColor }"></div>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-500 dark:text-slate-400 font-bold block">{{ $t('settings.custom_color_label') }}</span>
                                <span class="text-xs font-mono font-black text-slate-900 dark:text-white">{{ activeHexColor.toUpperCase() }}</span>
                            </div>
                        </div>

                        <!-- Direct HEX Input Field -->
                        <div class="flex items-center gap-2 bg-white dark:bg-slate-900 px-3 py-2 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
                            <span class="text-xs font-bold text-slate-400">#</span>
                            <input
                                type="text"
                                :value="activeHexColor.replace('#', '')"
                                @input="onHexTextInput($event.target.value)"
                                maxlength="7"
                                placeholder="3B82F6"
                                class="w-24 bg-transparent text-xs font-mono font-black text-slate-900 dark:text-white uppercase focus:outline-none"
                            >
                        </div>
                    </div>

                    <!-- Extended Quick Swatches -->
                    <div class="space-y-2 pt-2 border-t border-slate-200 dark:border-slate-800/80">
                        <span class="text-[11px] font-bold text-slate-600 dark:text-slate-400 block">{{ $t('settings.quick_swatches') }}</span>
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                v-for="swatch in extendedSwatches"
                                :key="swatch.hex"
                                type="button"
                                @click="onHexTextInput(swatch.hex)"
                                class="w-7 h-7 rounded-xl transition transform hover:scale-125 cursor-pointer shadow-xs border border-white/20 relative flex items-center justify-center"
                                :style="{ backgroundColor: swatch.hex }"
                                :title="swatch.name + ' (' + swatch.hex + ')'"
                            >
                                <span v-if="activeHexColor.toLowerCase() === swatch.hex.toLowerCase()" class="text-white text-[10px] font-black">✓</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Live Real-Time Preview Card -->
                <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 space-y-4">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <Eye class="w-4 h-4 text-theme-primary" />
                        <span>{{ $t('settings.live_preview_title') }}</span>
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-center">
                        <!-- Sample KPI Card -->
                        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-2 shadow-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('settings.preview_kpi_sales') }}</span>
                                <Zap class="w-4 h-4 text-theme-primary" />
                            </div>
                            <div class="text-2xl font-black font-mono text-theme-primary">
                                24,850.00 <span class="text-xs font-bold text-slate-500">{{ $t('common.currency') }}</span>
                            </div>
                        </div>

                        <!-- Sample Action Button -->
                        <button
                            type="button"
                            class="h-12 px-5 rounded-2xl btn-primary-theme font-black text-xs flex items-center justify-center gap-2 transition transform active:scale-95 cursor-pointer"
                        >
                            <Zap class="w-4 h-4 fill-current" />
                            <span>{{ $t('settings.preview_button_active') }} (F2)</span>
                        </button>

                        <!-- Sample Badge & Store Chip -->
                        <div class="flex flex-col items-center sm:items-start gap-2">
                            <span class="px-3 py-1.5 rounded-xl text-xs font-black badge-theme flex items-center gap-1.5 shadow-xs">
                                <CheckCircle2 class="w-3.5 h-3.5" />
                                <span>{{ $t('settings.preview_active_branch_sample') }}</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex justify-end">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="h-12 px-8 rounded-2xl btn-primary-theme font-black text-xs transition transform active:scale-95 cursor-pointer disabled:opacity-50 flex items-center gap-2"
                    >
                        <Palette class="w-4 h-4" />
                        <span>{{ form.processing ? $t('common.save') + '...' : $t('settings.save_theme_btn') }}</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>
