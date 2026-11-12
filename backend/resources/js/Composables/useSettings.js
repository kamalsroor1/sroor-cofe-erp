import { ref, computed, onMounted, onUnmounted } from 'vue';
import api from '../Services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';
import { useAppConfigStore } from '../stores/appConfig';
import { useAuthStore } from '../stores/auth';
import { useUnits } from './useUnits';
import { normalize } from '../helpers/decimal';
import { toWesternDigits } from '../helpers/digits';
import { Building2, Palette, Printer, Bot, Package } from 'lucide-vue-next';

export function useSettings() {
    const { t } = useTrans();
    const appConfigStore = useAppConfigStore();

    const windowWidth = ref(window.innerWidth);
    const isMobileView = computed(() => windowWidth.value < 1024);

    const selectedSection = ref(isMobileView.value ? null : 'branding');
    const isLoading = ref(false);
    const loadError = ref(false);
    const loadErrorMessage = ref('');
    const isSaving = ref(false);
    // The stored Telegram bot token can no longer be decrypted (key change): the owner must type it again.
    const telegramNeedsReentry = ref(false);
    const isTestingTelegram = ref(false);

    const colorPalettes = [
        { id: 'ibraa', labelKey: 'settings.palette_ibraa', hex: '#0F6E5E' },
        { id: 'amber', labelKey: 'settings.palette_amber', hex: '#f59e0b' },
        { id: 'emerald', labelKey: 'settings.palette_emerald', hex: '#10b981' },
        { id: 'blue', labelKey: 'settings.palette_blue', hex: '#3b82f6' },
        { id: 'purple', labelKey: 'settings.palette_purple', hex: '#a855f7' },
        { id: 'rose', labelKey: 'settings.palette_rose', hex: '#f43f5e' },
        { id: 'orange', labelKey: 'settings.palette_orange', hex: '#f97316' },
        { id: 'teal', labelKey: 'settings.palette_teal', hex: '#14b8a6' },
        { id: 'indigo', labelKey: 'settings.palette_indigo', hex: '#6366f1' },
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
            badge: t('settings.sec_branding_badge'),
        },
        {
            id: 'appearance',
            label: t('settings.sec_appearance_label'),
            subtitle: t('settings.sec_appearance_subtitle'),
            description: t('settings.sec_appearance_desc'),
            icon: Palette,
            iconBg: 'bg-purple-500/10 border border-purple-500/20',
            iconColor: 'text-purple-500 dark:text-purple-400',
            badge: t('settings.sec_appearance_badge'),
        },
        {
            id: 'printing',
            label: t('settings.sec_printing_label'),
            subtitle: t('settings.sec_printing_subtitle'),
            description: t('settings.sec_printing_desc'),
            icon: Printer,
            iconBg: 'bg-blue-500/10 border border-blue-500/20',
            iconColor: 'text-blue-500 dark:text-blue-400',
            badge: t('settings.sec_printing_badge'),
        },
        {
            id: 'telegram',
            label: t('settings.sec_telegram_label'),
            subtitle: t('settings.sec_telegram_subtitle'),
            description: t('settings.sec_telegram_desc'),
            icon: Bot,
            iconBg: 'bg-cyan-500/10 border border-cyan-500/20',
            iconColor: 'text-cyan-500 dark:text-cyan-400',
            badge: t('settings.sec_telegram_badge'),
        },
        {
            id: 'units',
            label: t('settings.sec_units_label'),
            subtitle: t('settings.sec_units_subtitle'),
            description: t('settings.sec_units_desc'),
            icon: Package,
            iconBg: 'bg-emerald-500/10 border border-emerald-500/20',
            iconColor: 'text-emerald-500 dark:text-emerald-400',
            badge: t('settings.sec_units_badge'),
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

    const authStore = useAuthStore();
    const canManage = computed(() => authStore.can('settings.manage') || authStore.isAdmin);

    const { units: systemUnits } = useUnits();

    const errors = ref({});
    const initialForm = ref(null);

    const logo_light_url = ref(null);
    const logo_dark_url = ref(null);
    const logoLightError = ref(null);
    const logoDarkError = ref(null);
    const isUploadingLogoLight = ref(false);
    const isUploadingLogoDark = ref(false);

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
        system_theme_color: 'ibraa',
        inventory_units: systemUnits.value.join(','),
        telegram_notifications_enabled: true,
        telegram_bot_token: '',
        telegram_chat_id: '',
        currency: 'EGP',
        timezone: 'Africa/Cairo',
        business_day_cutoff: '00:00',
        commercial_register: '',
        tax_registration_no: '',
        low_stock_default_threshold: '5.000',
        allow_negative_stock: false,
        receipt_header_lines: '',
        receipt_footer_text: '',
        returns_max_days: '',
        pos_idle_lock_minutes: '0',
    });

    const newUnitInput = ref('');
    const defaultPresets = [];

    const uploadLogo = async (variant, file) => {
        const isLight = variant === 'light';
        if (isLight) {
            isUploadingLogoLight.value = true;
            logoLightError.value = null;
        } else {
            isUploadingLogoDark.value = true;
            logoDarkError.value = null;
        }

        const formData = new FormData();
        formData.append('file', file);

        try {
            const res = await api.post(`/settings/branding/logo/${variant}`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            if (res.data?.success && res.data?.data?.logos) {
                if (isLight) {
                    logo_light_url.value = res.data.data.logos.light;
                } else {
                    logo_dark_url.value = res.data.data.logos.dark;
                }
                Swal.fire({
                    icon: 'success',
                    title: t('common.success'),
                    text: res.data.message || t('branding.logo_uploaded'),
                    timer: 1500,
                    showConfirmButton: false,
                });
            }
            return res.data;
        } catch (err) {
            const errData = err.response?.data;
            const validationErr = errData?.errors?.file?.[0] || errData?.message || t('common.error_occurred');
            if (isLight) {
                logoLightError.value = validationErr;
            } else {
                logoDarkError.value = validationErr;
            }
            return false;
        } finally {
            if (isLight) {
                isUploadingLogoLight.value = false;
            } else {
                isUploadingLogoDark.value = false;
            }
        }
    };

    const removeLogo = async (variant) => {
        const isLight = variant === 'light';
        if (isLight) {
            isUploadingLogoLight.value = true;
            logoLightError.value = null;
        } else {
            isUploadingLogoDark.value = true;
            logoDarkError.value = null;
        }

        try {
            const res = await api.delete(`/settings/branding/logo/${variant}`);
            if (isLight) {
                logo_light_url.value = null;
            } else {
                logo_dark_url.value = null;
            }
            Swal.fire({
                icon: 'success',
                title: t('common.success'),
                text: res.data?.message || t('branding.logo_deleted'),
                timer: 1500,
                showConfirmButton: false,
            });
            return res.data;
        } catch (err) {
            const errMsg = err.response?.data?.message || t('common.error_occurred');
            if (isLight) {
                logoLightError.value = errMsg;
            } else {
                logoDarkError.value = errMsg;
            }
            return false;
        } finally {
            if (isLight) {
                isUploadingLogoLight.value = false;
            } else {
                isUploadingLogoDark.value = false;
            }
        }
    };

    const activeUnitsList = computed(() => {
        if (!form.value.inventory_units) return [];
        return form.value.inventory_units
            .split(',')
            .map((u) => u.trim())
            .filter(Boolean);
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
            } catch {
                // EyeDropper cancelled or unsupported by user agent
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

    const reorderUnits = (newUnitsList) => {
        form.value.inventory_units = newUnitsList.join(',');
    };

    const moveUnitUp = (idx) => {
        if (idx <= 0) return;
        const current = [...activeUnitsList.value];
        const temp = current[idx];
        current[idx] = current[idx - 1];
        current[idx - 1] = temp;
        form.value.inventory_units = current.join(',');
    };

    const moveUnitDown = (idx) => {
        const current = [...activeUnitsList.value];
        if (idx >= current.length - 1) return;
        const temp = current[idx];
        current[idx] = current[idx + 1];
        current[idx + 1] = temp;
        form.value.inventory_units = current.join(',');
    };

    const DEFAULTS = {
        currency: 'EGP',
        timezone: 'Africa/Cairo',
        business_day_cutoff: '00:00',
        commercial_register: '',
        tax_registration_no: '',
        low_stock_default_threshold: '5.000',
        allow_negative_stock: false,
        show_print_company_name: true,
        show_print_subtitle: true,
        show_print_logo: true,
        thermal_show_customer_balance: true,
        print_show_qr: true,
        system_theme_color: 'ibraa',
        telegram_notifications_enabled: true,
        receipt_header_lines: '',
        receipt_footer_text: '',
        returns_max_days: '',
        pos_idle_lock_minutes: '0',
    };

    const isFieldModified = (field) => {
        if (DEFAULTS[field] === undefined) return false;
        if (field === 'low_stock_default_threshold') {
            return normalize(form.value[field] || '0') !== normalize(DEFAULTS[field]);
        }
        return String(form.value[field] ?? '') !== String(DEFAULTS[field] ?? '');
    };

    const resetFieldToDefault = (field) => {
        if (DEFAULTS[field] !== undefined) {
            form.value[field] = DEFAULTS[field];
            if (errors.value && errors.value[field]) {
                delete errors.value[field];
            }
        }
    };

    const fetchSettings = async () => {
        isLoading.value = true;
        loadError.value = false;
        loadErrorMessage.value = '';
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
                system_theme_color: s.system_theme_color || 'ibraa',
                inventory_units: s.inventory_units != null ? s.inventory_units : systemUnits.value.join(','),
                telegram_notifications_enabled: !!s.telegram_notifications_enabled,
                telegram_bot_token: s.telegram_bot_token || '',
                telegram_chat_id: s.telegram_chat_id || '',
                currency: s.currency || 'EGP',
                timezone: s.timezone || 'Africa/Cairo',
                business_day_cutoff: s.business_day_cutoff || '00:00',
                commercial_register: s.commercial_register || '',
                tax_registration_no: s.tax_registration_no || '',
                low_stock_default_threshold:
                    s.low_stock_default_threshold != null ? normalize(s.low_stock_default_threshold) : '5.000',
                allow_negative_stock: !!s.allow_negative_stock,
                receipt_header_lines: s.receipt_header_lines || '',
                receipt_footer_text: s.receipt_footer_text || '',
                returns_max_days: s.returns_max_days == null ? '' : String(s.returns_max_days),
                pos_idle_lock_minutes: String(s.pos_idle_lock_minutes ?? 0),
            };
            telegramNeedsReentry.value = s.telegram_bot_token_needs_reentry === true;
            logo_light_url.value = s.logo_light_url || null;
            logo_dark_url.value = s.logo_dark_url || null;
            initialForm.value = JSON.parse(JSON.stringify(form.value));
            if (s.system_theme_color) {
                if (s.system_theme_color.startsWith('#')) customHexColor.value = s.system_theme_color;
                appConfigStore.setThemeColor(s.system_theme_color);
            }
        } catch (e) {
            loadError.value = true;
            loadErrorMessage.value = e.response?.data?.message || t('settings.load_error_desc');
        } finally {
            isLoading.value = false;
        }
    };

    /** SET-02: whole digits only (Arabic-Indic digits accepted); anything else is left for the validator to reject. */
    const policyDigits = (value) => toWesternDigits(value).trim();

    const validatePolicies = () => {
        const found = {};
        const days = policyDigits(form.value.returns_max_days);
        if (days !== '' && !(/^\d+$/.test(days) && Number(days) >= 1 && Number(days) <= 3650)) {
            found.returns_max_days = [t('settings.returns_max_days_invalid')];
        }
        const minutes = policyDigits(form.value.pos_idle_lock_minutes);
        if (minutes !== '' && !(/^\d+$/.test(minutes) && Number(minutes) <= 240)) {
            found.pos_idle_lock_minutes = [t('settings.pos_idle_lock_minutes_invalid')];
        }
        return found;
    };

    /** The form as the API wants it: empty window = null (no limit), empty idle lock = 0 (off), both as integers. */
    const settingsPayload = () => {
        const days = policyDigits(form.value.returns_max_days);
        const minutes = policyDigits(form.value.pos_idle_lock_minutes);
        return {
            ...form.value,
            returns_max_days: days === '' ? null : Number(days),
            pos_idle_lock_minutes: minutes === '' ? 0 : Number(minutes),
        };
    };

    const saveSettings = async () => {
        if (!initialForm.value || loadError.value) {
            return false;
        }
        const policyErrors = validatePolicies();
        if (Object.keys(policyErrors).length > 0) {
            errors.value = policyErrors;
            return false;
        }
        isSaving.value = true;
        errors.value = {};
        try {
            const payload = settingsPayload();
            await api.post('/settings', payload);
            initialForm.value = JSON.parse(JSON.stringify(form.value));
            // Everyone signed in reads these two from /system/context: keep this session in step without a reload.
            appConfigStore.system = {
                ...appConfigStore.system,
                returns_max_days: payload.returns_max_days,
                pos_idle_lock_minutes: payload.pos_idle_lock_minutes,
            };
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
            return true;
        } catch (e) {
            if (e.response?.status === 422 && e.response?.data?.errors) {
                errors.value = e.response.data.errors;
            }
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: e.response?.data?.message || t('settings.settings_save_failed'),
            });
            return false;
        } finally {
            isSaving.value = false;
        }
    };

    const saveSection = async (_sectionName) => {
        return saveSettings();
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
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: e.response?.data?.message || t('settings.test_send_failed'),
            });
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
        loadError,
        loadErrorMessage,
        fetchSettings,
        isSaving,
        isTestingTelegram,
        telegramNeedsReentry,
        colorPalettes,
        sections,
        currentSectionTitle,
        currentSectionSubtitle,
        form,
        initialForm,
        errors,
        newUnitInput,
        defaultPresets,
        activeUnitsList,
        customHexColor,
        addCustomUnit,
        addPresetUnit,
        removeUnit,
        reorderUnits,
        onCustomColorChange,
        pickFromScreen,
        selectThemeColor,
        updateFormField,
        canManage,
        isFieldModified,
        moveUnitUp,
        moveUnitDown,
        DEFAULTS,
        saveSettings,
        saveSection,
        resetFieldToDefault,
        sendTestTelegram,
        logo_light_url,
        logo_dark_url,
        logoLightError,
        logoDarkError,
        isUploadingLogoLight,
        isUploadingLogoDark,
        uploadLogo,
        removeLogo,
    };
}
