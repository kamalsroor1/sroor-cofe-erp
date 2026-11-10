import { computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAppConfigStore } from '../stores/appConfig';
import { SUPPORTED_LOCALES, trans, translationsVersion } from '../helpers/trans';
import { applyDocumentLocale } from '../helpers/locale';
import { notifyError } from '../helpers/alert';

/**
 * Single place for the active language: exposes locale / dir, switches it, and (once, from App.vue
 * via `useLocaleSync`) keeps <html lang dir> and document.title in step with it.
 */
export function useLocale() {
    const appConfig = useAppConfigStore();

    const locale = computed(() => appConfig.locale);
    const dir = computed(() => appConfig.dir);
    const isRtl = computed(() => appConfig.isRtl);
    const nextLocale = computed(() => SUPPORTED_LOCALES.find((code) => code !== appConfig.locale) || appConfig.locale);

    const setLocale = async (code) => {
        const saved = await appConfig.switchLocale(code);
        if (!saved) notifyError(trans('locale.switch_failed'));
    };

    return { locale, dir, isRtl, nextLocale, locales: SUPPORTED_LOCALES, setLocale };
}

export function useLocaleSync() {
    const appConfig = useAppConfigStore();
    const route = useRoute();

    watch(() => appConfig.locale, applyDocumentLocale, { immediate: true });

    // The router guards set the title on navigation; redo it when only the language changed.
    watch([translationsVersion, () => route.meta.titleKey], () => {
        const meta = route.meta || {};
        const isSuper = route.path.startsWith('/super-admin') || meta.isSuperAdmin;
        const appName = isSuper
            ? trans('super.platform_title')
            : appConfig.platformName || trans('nav.page_titles.default_app_name');
        document.title = meta.titleKey ? `${trans(meta.titleKey)} - ${appName}` : appName;
    });
}
