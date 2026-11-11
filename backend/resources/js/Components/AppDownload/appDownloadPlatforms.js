import { Monitor, Smartphone } from 'lucide-vue-next';

/** Per-platform icon and copy keys of the download cards. */
export const DOWNLOAD_PLATFORMS = Object.freeze({
    android: {
        icon: Smartphone,
        titleKey: 'app_update.download_page.android_title',
        bodyKey: 'marketing.download.platforms.android.body',
        ctaKey: 'marketing.download.platforms.android.cta',
        installHintKey: 'app_update.download_page.android_install_hint',
        // Shell command, not prose: shown verbatim so users can paste it.
        verifyCommand: 'sha256sum <file>',
    },
    windows: {
        icon: Monitor,
        titleKey: 'app_update.download_page.windows_title',
        bodyKey: 'marketing.download.platforms.windows.body',
        ctaKey: 'marketing.download.platforms.windows.cta',
        installHintKey: 'app_update.download_page.windows_install_hint',
        verifyCommand: 'certutil -hashfile <file> SHA256',
    },
});

export const downloadPlatform = (platform) => DOWNLOAD_PLATFORMS[platform] ?? DOWNLOAD_PLATFORMS.android;
