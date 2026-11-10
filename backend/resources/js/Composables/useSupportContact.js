import { computed } from 'vue';
import { useAppConfigStore } from '../stores/appConfig';
import { useTrans } from './useTrans';

/**
 * Resolves the platform support contact (WhatsApp URL / email) from central platform
 * branding (`appConfigStore.branding.platform`), never from a hardcoded phone number.
 */
export function useSupportContact() {
    const appConfigStore = useAppConfigStore();
    const { t } = useTrans();

    const supportPhone = computed(() => String(appConfigStore.branding?.platform?.support_phone ?? '').trim());
    const supportEmail = computed(() => String(appConfigStore.branding?.platform?.support_email ?? '').trim());

    const whatsappUrl = computed(() => {
        const raw = supportPhone.value;
        if (!raw) return '';

        const defaultText = encodeURIComponent(
            t('help.whatsapp_default_message', { company: appConfigStore.companyName })
        );

        if (raw.startsWith('https://') || raw.startsWith('http://')) {
            const separator = raw.includes('?') ? '&' : '?';
            return `${raw}${separator}text=${defaultText}`;
        }

        const digits = raw.replace(/[^\d]/g, '');
        if (!digits) return '';

        return `https://wa.me/${digits}?text=${defaultText}`;
    });

    const supportHref = computed(() => {
        if (whatsappUrl.value) return whatsappUrl.value;
        if (supportEmail.value) return `mailto:${supportEmail.value}`;
        return '';
    });

    const hasSupportContact = computed(() => Boolean(supportHref.value));

    return {
        supportPhone,
        supportEmail,
        whatsappUrl,
        supportHref,
        hasSupportContact,
    };
}
