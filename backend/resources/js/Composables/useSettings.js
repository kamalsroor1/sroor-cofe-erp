import { ref, computed, onMounted, onUnmounted } from 'vue';
import api from '../services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';
import { useAppConfigStore } from '../stores/appConfig';
import {
    Building2,
    Palette,
    Printer,
    Bot,
    Package
} from 'lucide-vue-next';

export function useSettings() {
    const { t } = useTrans();
    const appConfigStore = useAppConfigStore();

    const windowWidth = ref(window.innerWidth);
    const isMobileView = computed(() => windowWidth.value < 1024);

    const selectedSection = ref(isMobileView.value ? null : 'branding');
    const isLoading = ref(false);
    const isSaving = ref(false);
    const isTestingTelegram = ref(false);

    const colorPalettes = [
        { id: 'amber', name: 'عنبري ذهبي (Gold)', hex: '#f59e0b' },
        { id: 'emerald', name: 'أخضر زمردي (Emerald)', hex: '#10b981' },
        { id: 'blue', name: 'أزرق عصري (Sky Blue)', hex: '#3b82f6' },
        { id: 'purple', name: 'بنفسجي ملكي (Purple)', hex: '#a855f7' },
        { id: 'rose', name: 'وردي ياقوتي (Rose)', hex: '#f43f5e' },
        { id: 'orange', name: 'برتقالي مشرق (Orange)', hex: '#f97316' },
        { id: 'teal', name: 'فيروزي هادئ (Teal)', hex: '#14b8a6' },
        { id: 'indigo', name: 'نيلي داكن (Indigo)', hex: '#6366f1' },
    ];

    const sections = computed(() => [
        {
            id: 'branding',
            label: t('settings.sec_branding_label'),
            subtitle: t('settings.sec_branding_subtitle'),
            description: t('settings.sec_branding_desc'),
            icon: Building2,
            iconBg: 'bg-theme-light border border-theme-border',
            iconColor: 'text-theme-primary',
            badge: t('settings.sec_branding_badge')
        },
        {
            id: 'appearance',
            label: t('settings.sec_appearance_label'),
            subtitle: t('settings.sec_appearance_subtitle'),
            description: t('settings.sec_appearance_desc'),
            icon: Palette,
            iconBg: 'bg-purple-500/10 border border-purple-500/20',
            iconColor: 'text-purple-500 dark:text-purple-400',
            badge: t('settings.sec_appearance_badge')
        },
        {
            id: 'printing',
            label: t('settings.sec_printing_label'),
            subtitle: t('settings.sec_printing_subtitle'),
            description: t('settings.sec_printing_desc'),
            icon: Printer,
            iconBg: 'bg-blue-500/10 border border-blue-500/20',
            iconColor: 'text-blue-500 dark:text-blue-400',
            badge: t('settings.sec_printing_badge')
        },
        {
            id: 'telegram',
            label: t('settings.sec_telegram_label'),
            subtitle: t('settings.sec_telegram_subtitle'),
            description: t('settings.sec_telegram_desc'),
            icon: Bot,
            iconBg: 'bg-cyan-500/10 border border-cyan-500/20',
            iconColor: 'text-cyan-500 dark:text-cyan-400',
            badge: t('settings.sec_telegram_badge')
        },
        {
            id: 'units',
            label: t('settings.sec_units_label'),
            subtitle: t('settings.sec_units_subtitle'),
            description: t('settings.sec_units_desc'),
            icon: Package,
            iconBg: 'bg-emerald-500/10 border border-emerald-500/20',
            iconColor: 'text-emerald-500 dark:text-emerald-400',
            badge: t('settings.sec_units_badge')
        },
    ]);

    const currentSectionTitle = computed(() => {
        if (!selectedSection.value) return t('settings.hub_title');
        const found = sections.value.find((s) => s.id === selectedSection.value);
        return found ? found.label : t('settings.title');
    });

    const currentSectionSubtitle = computed(() => {
        if (!selectedSection.value) return t('settings.hub_subtitle');
        const found = sections.value.find((s) => s.id === selectedSection.value);
        return found ? found.subtitle : '';
    });

    const form = ref({
        company_name: '',
        company_subtitle: '',
        company_phone: '',
        company_address: '',
        invoice_footer_note: '',
        show_print_company_name: true,
        show_print_subtitle: true,
        show_print_logo: true,
        thermal_show_customer_balance: true,
        print_show_qr: true,
        system_theme_color: 'amber',
        inventory_units: 'قطعة,علبة,كرتونة,كجم,جرام,شيكارة,طرد,دستة,لتر',
        telegram_notifications_enabled: true,
        telegram_bot_token: '',
        telegram_chat_id: '',
    });

    const newUnitInput = ref('');
    const defaultPresets = ['قطعة', 'علبة', 'كرتونة', 'كجم', 'جرام', 'شيكارة', 'طرد', 'دستة', 'باكت', 'حبة', 'لتر', 'مل', 'متر', 'طقم', 'زوج', 'باليتة'];

    const activeUnitsList = computed(() => {
        if (!form.value.inventory_units) return [];
        return form.value.inventory_units.split(',').map(u => u.trim()).filter(Boolean);
    });

    const addCustomUnit = () => {
        const u = newUnitInput.value.trim();
        if (!u) return;
        const current = [...activeUnitsList.value];
        if (!current.includes(u)) {
            current.push(u);
            form.value.inventory_units = current.join(',');
        }
        newUnitInput.value = '';
    };

    const addPresetUnit = (preset) => {
        const current = [...activeUnitsList.value];
        if (!current.includes(preset)) {
            current.push(preset);
            form.value.inventory_units = current.join(',');
        }
    };

    const removeUnit = (idx) => {
        const current = [...activeUnitsList.value];
        current.splice(idx, 1);
        form.value.inventory_units = current.join(',');
    };

    const customHexColor = ref('#10b981');

    const onCustomColorChange = (newVal) => {
        let hex = typeof newVal === 'string' ? newVal : customHexColor.value;
        if (hex) {
            if (!hex.startsWith('#')) hex = '#' + hex;
            customHexColor.value = hex;
            if (/^#[0-9A-Fa-f]{6}$/.test(hex)) {
                form.value.system_theme_color = hex;
                appConfigStore.setThemeColor(hex);
            }
        }
    };

    const pickFromScreen = async () => {
        if ('EyeDropper' in window) {
            try {
                const eyeDropper = new window.EyeDropper();
                const result = await eyeDropper.open();
                if (result?.sRGBHex) {
                    onCustomColorChange(result.sRGBHex);
                }
            } catch (e) {
                console.log('EyeDropper cancelled or failed', e);
            }
        } else {
            Swal.fire({
                icon: 'info',
                title: t('settings.eyedropper_title'),
                text: t('settings.eyedropper_fallback_text'),
            });
        }
    };

    const selectThemeColor = (colorId) => {
        form.value.system_theme_color = colorId;
        appConfigStore.setThemeColor(colorId);
    };

    const updateFormField = (field, val) => {
        form.value[field] = val;
    };

    const onResize = () => {
        windowWidth.value = window.innerWidth;
        if (!isMobileView.value && !selectedSection.value) {
            selectedSection.value = 'branding';
        }
    };

    const fetchSettings = async () => {
        isLoading.value = true;
        try {
            const res = await api.get('/settings');
            const s = res.data?.settings || {};
            form.value = {
                company_name: s.company_name || appConfigStore.companyName || '',
                company_subtitle: s.company_subtitle || '',
                company_phone: s.company_phone || '',
                company_address: s.company_address || '',
                invoice_footer_note: s.invoice_footer_note || '',
                show_print_company_name: !!s.show_print_company_name,
                show_print_subtitle: !!s.show_print_subtitle,
                show_print_logo: !!s.show_print_logo,
                thermal_show_customer_balance: !!s.thermal_show_customer_balance,
                print_show_qr: !!s.print_show_qr,
                system_theme_color: s.system_theme_color || 'amber',
                inventory_units: s.inventory_units || 'قطعة,علبة,كرتونة,كجم,جرام,شيكارة,طرد,دستة,لتر',
                telegram_notifications_enabled: !!s.telegram_notifications_enabled,
                telegram_bot_token: s.telegram_bot_token || '',
                telegram_chat_id: s.telegram_chat_id || '',
            };
            if (s.system_theme_color) {
                if (s.system_theme_color.startsWith('#')) customHexColor.value = s.system_theme_color;
                appConfigStore.setThemeColor(s.system_theme_color);
            }
        } catch (e) {
            console.error('Failed to load settings:', e);
        } finally {
            isLoading.value = false;
        }
    };

    const saveSettings = async () => {
        isSaving.value = true;
        try {
            await api.post('/settings', form.value);
            if (form.value.system_theme_color) {
                appConfigStore.setThemeColor(form.value.system_theme_color);
            }
            Swal.fire({
                icon: 'success',
                title: t('common.success'),
                text: t('settings.settings_saved_success'),
                timer: 1500,
                showConfirmButton: false,
            });
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: e.response?.data?.message || t('settings.settings_save_failed'),
            });
        } finally {
            isSaving.value = false;
        }
    };

    const sendTestTelegram = async () => {
        isTestingTelegram.value = true;
        try {
            const res = await api.post('/settings/telegram/test', {
                bot_token: form.value.telegram_bot_token,
                chat_id: form.value.telegram_chat_id,
            });
            if (res.data?.success) {
                Swal.fire({ icon: 'success', title: t('settings.test_send_success'), text: res.data.message });
            } else {
                Swal.fire({ icon: 'error', title: t('settings.test_send_failed'), text: res.data.message });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: t('common.error'), text: e.response?.data?.message || t('settings.test_send_failed') });
        } finally {
            isTestingTelegram.value = false;
        }
    };

    onMounted(() => {
        window.addEventListener('resize', onResize);
        fetchSettings();
    });

    onUnmounted(() => {
        window.removeEventListener('resize', onResize);
    });

    return {
        appConfigStore,
        isMobileView,
        selectedSection,
        isLoading,
        isSaving,
        isTestingTelegram,
        colorPalettes,
        sections,
        currentSectionTitle,
        currentSectionSubtitle,
        form,
        newUnitInput,
        defaultPresets,
        activeUnitsList,
        customHexColor,
        addCustomUnit,
        addPresetUnit,
        removeUnit,
        onCustomColorChange,
        pickFromScreen,
        selectThemeColor,
        updateFormField,
        saveSettings,
        sendTestTelegram,
    };
}
