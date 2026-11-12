import { ref, computed, nextTick, watch } from 'vue';
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
        keywordsKey: 'settings.search_keywords.company_name',
        isAdvanced: false,
    },
    {
        id: 'company_subtitle',
        key: 'company.subtitle',
        labelKey: 'settings.company_subtitle',
        descKey: 'settings.company_subtitle_placeholder',
        tabId: 'general',
        keywordsKey: 'settings.search_keywords.company_subtitle',
        isAdvanced: false,
    },
    {
        id: 'company_phone',
        key: 'company.phone',
        labelKey: 'settings.company_phone',
        descKey: 'settings.company_phone_placeholder',
        tabId: 'general',
        keywordsKey: 'settings.search_keywords.company_phone',
        isAdvanced: false,
    },
    {
        id: 'company_address',
        key: 'company.address',
        labelKey: 'settings.company_address',
        descKey: 'settings.company_address_placeholder',
        tabId: 'general',
        keywordsKey: 'settings.search_keywords.company_address',
        isAdvanced: false,
    },
    {
        id: 'currency',
        key: 'finance.currency',
        labelKey: 'settings.currency',
        descKey: 'settings.currency',
        tabId: 'general',
        keywordsKey: 'settings.search_keywords.currency',
        isAdvanced: false,
    },
    {
        id: 'timezone',
        key: 'locale.timezone',
        labelKey: 'settings.timezone',
        descKey: 'settings.timezone',
        tabId: 'general',
        keywordsKey: 'settings.search_keywords.timezone',
        isAdvanced: false,
    },
    {
        id: 'business_day_cutoff',
        key: 'locale.business_day_cutoff',
        labelKey: 'settings.business_day_cutoff',
        descKey: 'settings.business_day_cutoff_hint',
        tabId: 'general',
        keywordsKey: 'settings.search_keywords.business_day_cutoff',
        isAdvanced: true,
    },
    {
        id: 'commercial_register',
        key: 'company.commercial_register',
        labelKey: 'settings.commercial_register',
        descKey: 'settings.commercial_register',
        tabId: 'general',
        keywordsKey: 'settings.search_keywords.commercial_register',
        isAdvanced: false,
    },
    {
        id: 'tax_registration_no',
        key: 'tax.registration_no',
        labelKey: 'settings.tax_registration_no',
        descKey: 'settings.tax_registration_no',
        tabId: 'general',
        keywordsKey: 'settings.search_keywords.tax_registration_no',
        isAdvanced: false,
    },

    // 2. Invoices & Printing Tab Settings
    {
        id: 'show_print_company_name',
        key: 'receipt.show_company_name',
        labelKey: 'settings.print_company_name_toggle',
        descKey: 'settings.print_company_name_desc',
        tabId: 'printing',
        keywordsKey: 'settings.search_keywords.show_print_company_name',
        isAdvanced: false,
    },
    {
        id: 'show_print_subtitle',
        key: 'receipt.show_subtitle',
        labelKey: 'settings.print_subtitle_toggle',
        descKey: 'settings.print_subtitle_desc',
        tabId: 'printing',
        keywordsKey: 'settings.search_keywords.show_print_subtitle',
        isAdvanced: false,
    },
    {
        id: 'show_print_logo',
        key: 'receipt.show_logo',
        labelKey: 'settings.show_print_logo',
        descKey: 'settings.logo_hint',
        tabId: 'printing',
        keywordsKey: 'settings.search_keywords.show_print_logo',
        isAdvanced: false,
    },
    {
        id: 'thermal_show_customer_balance',
        key: 'receipt.show.customer_balance',
        labelKey: 'settings.thermal_balance_toggle',
        descKey: 'settings.thermal_balance_desc',
        tabId: 'printing',
        keywordsKey: 'settings.search_keywords.thermal_show_customer_balance',
        isAdvanced: false,
    },
    {
        id: 'print_show_qr',
        key: 'receipt.qr.mode',
        labelKey: 'settings.print_qr_toggle',
        descKey: 'settings.print_qr_desc',
        tabId: 'printing',
        keywordsKey: 'settings.search_keywords.print_show_qr',
        isAdvanced: false,
    },
    {
        id: 'invoice_footer_note',
        key: 'printing.invoice_footer',
        labelKey: 'settings.invoice_footer_note_label',
        descKey: 'settings.invoice_footer_placeholder',
        tabId: 'printing',
        keywordsKey: 'settings.search_keywords.invoice_footer_note',
        isAdvanced: false,
    },

    // 3. Inventory Tab Settings
    {
        id: 'inventory_units',
        key: 'inventory.units',
        labelKey: 'settings.inventory_units',
        descKey: 'settings.sec_units_desc',
        tabId: 'inventory',
        keywordsKey: 'settings.search_keywords.inventory_units',
        isAdvanced: false,
    },
    {
        id: 'low_stock_default_threshold',
        key: 'inventory.low_stock.default_threshold',
        labelKey: 'settings.low_stock_default_threshold',
        descKey: 'settings.low_stock_default_threshold',
        tabId: 'inventory',
        keywordsKey: 'settings.search_keywords.low_stock_default_threshold',
        isAdvanced: false,
    },
    {
        id: 'allow_negative_stock',
        key: 'inventory.allow_negative_stock',
        labelKey: 'settings.allow_negative_stock',
        descKey: 'settings.allow_negative_stock_desc',
        tabId: 'inventory',
        keywordsKey: 'settings.search_keywords.allow_negative_stock',
        isAdvanced: false,
    },

    // 3b. POS & Returns Tab Settings
    {
        id: 'returns_max_days',
        key: 'returns.max_days',
        labelKey: 'settings.returns_max_days',
        descKey: 'settings.returns_max_days_desc',
        tabId: 'pos',
        keywordsKey: 'settings.search_keywords.returns_max_days',
        isAdvanced: false,
    },
    {
        id: 'pos_idle_lock_minutes',
        key: 'pos.idle_lock_minutes',
        labelKey: 'settings.pos_idle_lock_minutes',
        descKey: 'settings.pos_idle_lock_minutes_desc',
        tabId: 'pos',
        keywordsKey: 'settings.search_keywords.pos_idle_lock_minutes',
        isAdvanced: false,
    },

    {
        id: 'pos_sale_settings',
        key: 'pos.sale_settings',
        labelKey: 'settings.sale_settings_title',
        descKey: 'settings.sale_settings_sub',
        tabId: 'pos',
        keywordsKey: 'settings.search_keywords.pos_sale_settings',
        isAdvanced: false,
    },

    // 4. Branding Tab Settings
    {
        id: 'logo_light',
        key: 'branding.logo.light',
        labelKey: 'settings.company_logo_light',
        descKey: 'settings.logo_light_hint',
        tabId: 'branding',
        keywordsKey: 'settings.search_keywords.logo_light',
        isAdvanced: false,
    },
    {
        id: 'logo_dark',
        key: 'branding.logo.dark',
        labelKey: 'settings.company_logo_dark',
        descKey: 'settings.logo_dark_hint',
        tabId: 'branding',
        keywordsKey: 'settings.search_keywords.logo_dark',
        isAdvanced: false,
    },
    {
        id: 'receipt_header_lines',
        key: 'receipt.header_lines',
        labelKey: 'branding.attributes.receipt_header_lines',
        descKey: 'settings.receipt_header_hint',
        tabId: 'branding',
        keywordsKey: 'settings.search_keywords.receipt_header_lines',
        isAdvanced: false,
    },
    {
        id: 'receipt_footer_text',
        key: 'receipt.footer_text',
        labelKey: 'branding.attributes.receipt_footer_text',
        descKey: 'settings.receipt_footer_hint',
        tabId: 'branding',
        keywordsKey: 'settings.search_keywords.receipt_footer_text',
        isAdvanced: false,
    },
    {
        id: 'system_theme_color',
        key: 'system.theme_color',
        labelKey: 'branding.attributes.system_theme_color',
        descKey: 'settings.sec_appearance_desc',
        tabId: 'branding',
        keywordsKey: 'settings.search_keywords.system_theme_color',
        isAdvanced: false,
    },

    // 5. Notifications Tab Settings
    {
        id: 'telegram_notifications_enabled',
        key: 'notifications.telegram.enabled',
        labelKey: 'settings.telegram_enable_toggle',
        descKey: 'settings.telegram_enable_desc',
        tabId: 'notifications',
        keywordsKey: 'settings.search_keywords.telegram_notifications_enabled',
        isAdvanced: false,
    },
    {
        id: 'telegram_bot_token',
        key: 'notifications.telegram.bot_token',
        labelKey: 'settings.bot_token',
        descKey: 'settings.bot_token_placeholder',
        tabId: 'notifications',
        keywordsKey: 'settings.search_keywords.telegram_bot_token',
        isAdvanced: true,
    },
    {
        id: 'telegram_chat_id',
        key: 'notifications.telegram.chat_id',
        labelKey: 'settings.chat_id',
        descKey: 'settings.chat_id_input_placeholder',
        tabId: 'notifications',
        keywordsKey: 'settings.search_keywords.telegram_chat_id',
        isAdvanced: false,
    },

    // 6. Device & System Tab Settings
    {
        id: 'theme_mode',
        key: 'device.theme_mode',
        labelKey: 'settings.theme_mode_label',
        descKey: 'settings.theme_mode_label',
        tabId: 'device',
        keywordsKey: 'settings.search_keywords.theme_mode',
        isAdvanced: false,
    },
];

export function useSettingsSearch(options = {}) {
    const { onSelectTab, isLoading } = options;
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
            const keywords = t(item.keywordsKey)
                .split(',')
                .map((kw) => kw.trim())
                .filter(Boolean);
            return {
                ...item,
                label,
                description,
                keywords,
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
            }, 4500);
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
     * Check query parameter 'k' and deep link into matching setting.
     * Waits until loading completes to prevent race conditions.
     */
    const handleDeepLink = async () => {
        const k = route?.query?.k || route?.query?.tab;
        if (!k || typeof k !== 'string') return;

        const needle = k.toLowerCase().trim();
        const validTabs = ['general', 'printing', 'inventory', 'branding', 'notifications', 'device'];
        if (validTabs.includes(needle)) {
            if (onSelectTab) {
                onSelectTab(needle);
            }
            return;
        }

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

            const doHighlight = () => {
                nextTick().then(() => {
                    setTimeout(() => {
                        highlightElement(found.id);
                    }, 150);
                });
            };

            if (!isLoading || !isLoading.value) {
                doHighlight();
            } else {
                const unwatch = watch(
                    () => isLoading.value,
                    (loading) => {
                        if (!loading) {
                            unwatch();
                            doHighlight();
                        }
                    }
                );
            }
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
