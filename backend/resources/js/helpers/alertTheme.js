export function isDarkTheme(doc = globalThis.document) {
    return Boolean(doc?.documentElement?.classList?.contains('dark'));
}

/**
 * UI-04: theme-aware SweetAlert2 surface & text colors.
 * Evaluated per call so toggling html.dark updates subsequent alerts without reload.
 */
export function getSwalThemeColors(doc = globalThis.document) {
    if (isDarkTheme(doc)) {
        return {
            background: '#090e1a',
            toastBackground: '#0f172a',
            color: '#f8fafc',
            confirmButtonColor: 'var(--color-primary)',
        };
    }

    return {
        background: '#ffffff',
        toastBackground: '#ffffff',
        color: '#0f172a',
        confirmButtonColor: 'var(--color-primary)',
    };
}
