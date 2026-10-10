import { ref, computed, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useTrans } from './useTrans';

export const SETTINGS_REGISTRY = [
    // 1. General Tab Settings
    {
        id: 'company_name',
        key: 'company.name',
        labelKey: 'settings.company_name',
        descKey: 'settings.company_name_placeholder',
        tabId: 'general',
        keywords: ['اسم', 'المنشأة', 'المحل', 'الشركة', 'company', 'name', 'branding'],
        isAdvanced: false,
    },
    {
        id: 'company_subtitle',
        key: 'company.subtitle',
        labelKey: 'settings.company_subtitle',
        descKey: 'settings.company_subtitle_placeholder',
        tabId: 'general',
        keywords: ['وصف', 'نشاط', 'فرعي', 'subtitle'],
        isAdvanced: false,
    },
    {
        id: 'company_phone',
        key: 'company.phone',
        labelKey: 'settings.company_phone',
        descKey: 'settings.company_phone_placeholder',
        tabId: 'general',
        keywords: ['هاتف', 'تليفون', 'موبايل', 'اتصال', 'phone'],
        isAdvanced: false,
    },
    {
        id: 'company_address',
        key: 'company.address',
        labelKey: 'settings.company_address',
        descKey: 'settings.company_address_placeholder',
        tabId: 'general',
        keywords: ['عنوان', 'مقر', 'مكان', 'فرع', 'address'],
        isAdvanced: false,
    },
    {
        id: 'currency',
        key: 'finance.currency',
        labelKey: 'settings.currency',
        descKey: 'settings.currency',
        tabId: 'general',
        keywords: ['عملة', 'جنيه', 'ريال', 'دولار', 'currency', 'EGP', 'SAR', 'AED', 'KWD', 'QAR', 'USD'],
        isAdvanced: false,
    },
    {
        id: 'timezone',
        key: 'locale.timezone',
        labelKey: 'settings.timezone',
        descKey: 'settings.timezone',
        tabId: 'general',
        keywords: ['توقيت', 'منطقة زمنية', 'ساعة', 'timezone', 'Cairo', 'Riyadh'],
        isAdvanced: false,
    },
    {
        id: 'business_day_cutoff',
        key: 'locale.business_day_cutoff',
        labelKey: 'settings.business_day_cutoff',
        descKey: 'settings.business_day_cutoff_invalid',
        tabId: 'general',
        keywords: ['نهاية اليوم', 'بداية اليوم', 'إغلاق اليوم', 'توقيت الإغلاق', 'وردية', 'cutoff', 'business_day'],
        isAdvanced: true,
    },
    {
        id: 'commercial_register',
        key: 'company.commercial_register',
        labelKey: 'settings.commercial_register',
        descKey: 'settings.commercial_register',
        tabId: 'general',
        keywords: ['سجل تجاري', 'سجل', 'commercial_register', 'cr'],
        isAdvanced: false,
    },
    {
        id: 'tax_registration_no',
        key: 'tax.registration_no',
        labelKey: 'settings.tax_registration_no',
        descKey: 'settings.tax_registration_no',
        tabId: 'general',
        keywords: ['رقم ضريبي', 'تسجيل ضريبي', 'ضريبة', 'tax', 'vat'],
        isAdvanced: false,
    },

    // 2. Invoices & Printing Tab Settings
    {
        id: 'show_print_company_name',
        key: 'receipt.show_company_name',
        labelKey: 'settings.print_company_name_toggle',
        descKey: 'settings.print_company_name_desc',
        tabId: 'printing',
        keywords: ['طباعة اسم', 'رأس الفاتورة', 'إيصال'],
        isAdvanced: false,
    },
    {
        id: 'show_print_subtitle',
        key: 'receipt.show_subtitle',
        labelKey: 'settings.print_subtitle_toggle',
        descKey: 'settings.print_subtitle_desc',
        tabId: 'printing',
        keywords: ['طباعة الوصف', 'وصف فرعي', 'إيصال'],
        isAdvanced: false,
    },
    {
        id: 'show_print_logo',
        key: 'receipt.show_logo',
        labelKey: 'settings.show_print_logo',
        descKey: 'settings.logo_hint',
        tabId: 'printing',
        keywords: ['شعار', 'لوجو', 'logo', 'طباعة اللوجو'],
        isAdvanced: false,
    },
    {
        id: 'thermal_show_customer_balance',
        key: 'receipt.show.customer_balance',
        labelKey: 'settings.thermal_balance_toggle',
        descKey: 'settings.thermal_balance_desc',
        tabId: 'printing',
        keywords: ['رصيد العميل', 'مديونية', 'آجل', 'balance'],
        isAdvanced: false,
    },
    {
        id: 'print_show_qr',
        key: 'receipt.qr.mode',
        labelKey: 'settings.print_qr_toggle',
        descKey: 'settings.print_qr_desc',
        tabId: 'printing',
        keywords: ['qr', 'رمز الاستجابة', 'باركود', 'qr code'],
        isAdvanced: false,
    },
    {
        id: 'invoice_footer_note',
        key: 'receipt.footer_text',
        labelKey: 'settings.invoice_footer_note_label',
        descKey: 'settings.invoice_footer_placeholder',
        tabId: 'printing',
        keywords: ['تذييل', 'ملاحظة الفاتورة', 'footer', 'استرجاع', 'شكر'],
        isAdvanced: false,
    },

    // 3. Inventory Tab Settings
    {
        id: 'inventory_units',
        key: 'inventory.units',
        labelKey: 'settings.inventory_units',
        descKey: 'settings.sec_units_desc',
        tabId: 'inventory',
        keywords: ['وحدات', 'وحدة', 'قياس', 'مخزون', 'units', 'measurements'],
        isAdvanced: false,
    },
    {
        id: 'low_stock_default_threshold',
        key: 'inventory.low_stock.default_threshold',
        labelKey: 'settings.low_stock_default_threshold',
        descKey: 'settings.low_stock_default_threshold',
        tabId: 'inventory',
        keywords: ['نواقص', 'تنبيه النواقص', 'حد النقص', 'threshold', 'low_stock'],
        isAdvanced: false,
    },

    // 4. Branding Tab Settings
    {
        id: 'logo_light',
        key: 'branding.logo.light',
        labelKey: 'settings.company_logo_light',
        descKey: 'settings.logo_light_hint',
        tabId: 'branding',
        keywords: ['شعار', 'لوجو', 'فاتح', 'نهار', 'logo', 'light'],
        isAdvanced: false,
    },
    {
        id: 'logo_dark',
        key: 'branding.logo.dark',
        labelKey: 'settings.company_logo_dark',
        descKey: 'settings.logo_dark_hint',
        tabId: 'branding',
        keywords: ['شعار', 'لوجو', 'داكن', 'ليلي', 'logo', 'dark'],
        isAdvanced: false,
    },
    {
        id: 'receipt_header_lines',
        key: 'receipt.header_lines',
        labelKey: 'branding.attributes.receipt_header_lines',
        descKey: 'branding.receipt_header_too_many_lines',
        tabId: 'branding',
        keywords: ['ترويسة', 'رأس', 'إيصال', 'فاتورة', 'header', 'receipt'],
        isAdvanced: false,
    },
    {
        id: 'receipt_footer_text',
        key: 'receipt.footer_text',
        labelKey: 'branding.attributes.receipt_footer_text',
        descKey: 'branding.receipt_text_no_markup',
        tabId: 'branding',
        keywords: ['تذييل', 'ختام', 'إيصال', 'فاتورة', 'footer', 'receipt'],
        isAdvanced: false,
    },
    {
        id: 'system_theme_color',
        key: 'system.theme_color',
        labelKey: 'branding.attributes.system_theme_color',
        descKey: 'branding.theme_color_invalid',
        tabId: 'branding',
        keywords: ['لون', 'ثيم', 'مظهر', 'theme', 'color'],
        isAdvanced: false,
    },

    // 4. Notifications Tab Settings
    {
        id: 'telegram_notifications_enabled',
        key: 'notifications.telegram.enabled',
        labelKey: 'settings.telegram_enable_toggle',
        descKey: 'settings.telegram_enable_desc',
        tabId: 'notifications',
        keywords: ['تلجرام', 'إشعارات', 'تنبيهات', 'telegram', 'bot'],
        isAdvanced: false,
    },
    {
        id: 'telegram_bot_token',
        key: 'notifications.telegram.bot_token',
        labelKey: 'settings.bot_token',
        descKey: 'settings.bot_token_placeholder',
        tabId: 'notifications',
        keywords: ['توكن', 'token', 'bot token'],
        isAdvanced: true,
    },
    {
        id: 'telegram_chat_id',
        key: 'notifications.telegram.chat_id',
        labelKey: 'settings.chat_id',
        descKey: 'settings.chat_id_input_placeholder',
        tabId: 'notifications',
        keywords: ['chat_id', 'محادثة', 'جروب'],
        isAdvanced: false,
    },

    // 5. Device & System Tab Settings
    {
        id: 'system_theme_color',
        key: 'device.theme_color',
        labelKey: 'settings.sec_appearance_label',
        descKey: 'settings.sec_appearance_desc',
        tabId: 'device',
        keywords: ['ألوان', 'ثيم', 'مظهر', 'theme', 'color', 'palette'],
        isAdvanced: false,
    },
    {
        id: 'theme_mode',
        key: 'device.theme_mode',
        labelKey: 'settings.theme_mode_label',
        descKey: 'settings.theme_mode_label',
        tabId: 'device',
        keywords: ['داكن', 'فاتح', 'ليلي', 'نهاري', 'dark', 'light'],
        isAdvanced: false,
    },
    {
        id: 'database_backup',
        key: 'device.backup',
        labelKey: 'settings.backup_title',
        descKey: 'settings.backup_sub',
        tabId: 'device',
        keywords: ['نسخ احتياطي', 'تحميل قاعدة البيانات', 'backup', 'sql'],
        isAdvanced: false,
    },
];

export function useSettingsSearch(options = {}) {
    const { onSelectTab } = options;
    const { t } = useTrans();
    const route = useRoute();
    const router = useRouter();

    const searchQuery = ref('');
    const isSearchOpen = ref(false);

    const searchResults = computed(() => {
        const q = searchQuery.value.trim().toLowerCase();
        if (!q) return [];

        return SETTINGS_REGISTRY.map((item) => {
            const label = t(item.labelKey);
            const description = t(item.descKey);
            return {
                ...item,
                label,
                description,
            };
        }).filter((item) => {
            return (
                item.label.toLowerCase().includes(q) ||
                item.description.toLowerCase().includes(q) ||
                item.key.toLowerCase().includes(q) ||
                item.id.toLowerCase().includes(q) ||
                item.keywords.some((kw) => kw.toLowerCase().includes(q))
            );
        });
    });

    /**
     * Momentarily pulse and scroll to the target setting element
     */
    const highlightElement = (targetKeyOrId) => {
        if (!targetKeyOrId || typeof document === 'undefined') return;

        const sanitized = String(targetKeyOrId).replace(/[^a-zA-Z0-9_-]/g, '-');
        const candidateIds = [
            `setting-${targetKeyOrId}`,
            `setting-${sanitized}`,
            `setting-${targetKeyOrId.split('.').pop()}`,
            targetKeyOrId,
        ];

        let el = null;
        for (const cid of candidateIds) {
            el = document.getElementById(cid);
            if (el) break;
        }

        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.add('ring-4', 'ring-theme-primary', 'ring-offset-2', 'transition-all', 'duration-500');
            setTimeout(() => {
                el.classList.remove('ring-4', 'ring-theme-primary', 'ring-offset-2');
            }, 2500);
        }
    };

    /**
     * Selecting a search result: switches tab, clears search, highlights field
     */
    const selectSetting = async (item) => {
        if (onSelectTab) {
            onSelectTab(item.tabId);
        }
        searchQuery.value = '';
        isSearchOpen.value = false;

        // Optionally update router query without navigation
        if (router && route) {
            try {
                router.replace({ query: { ...route.query, k: item.key } });
            } catch {
                // Ignore route replace errors
            }
        }

        await nextTick();
        setTimeout(() => {
            highlightElement(item.id);
        }, 150);
    };

    /**
     * Check query parameter 'k' and deep link into matching setting
     */
    const handleDeepLink = async () => {
        const k = route?.query?.k;
        if (!k || typeof k !== 'string') return;

        const needle = k.toLowerCase().trim();
        const found = SETTINGS_REGISTRY.find(
            (item) =>
                item.key.toLowerCase() === needle ||
                item.id.toLowerCase() === needle ||
                item.key.toLowerCase().endsWith('.' + needle) ||
                item.key.toLowerCase().includes(needle)
        );

        if (found) {
            if (onSelectTab) {
                onSelectTab(found.tabId);
            }
            await nextTick();
            setTimeout(() => {
                highlightElement(found.id);
            }, 250);
        }
    };

    return {
        searchQuery,
        searchResults,
        isSearchOpen,
        selectSetting,
        highlightElement,
        handleDeepLink,
        registry: SETTINGS_REGISTRY,
    };
}
