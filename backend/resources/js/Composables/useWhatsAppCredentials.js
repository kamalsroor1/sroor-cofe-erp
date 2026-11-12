import { ref } from 'vue';
import { buildWhatsAppUrl } from '../helpers/whatsappLink';
import { arabicMessage, preloadArabicDictionary } from '../helpers/whatsappShare';

/**
 * "Send on WhatsApp" for a temporary password. The shop owner reads Arabic whatever the operator's
 * language is, so the text comes from the Arabic dictionary (loaded ahead so the click stays
 * synchronous and the popup is not blocked). The URL, which contains the password, is built only
 * inside `send()` on the operator's click and is never rendered in the DOM.
 */
export function useWhatsAppCredentials() {
    const isReady = ref(false);

    const prepare = async () => {
        try {
            await preloadArabicDictionary();
            isReady.value = true;
        } catch {
            isReady.value = false;
        }
    };

    const send = ({ phone, login, password }) => {
        const text = arabicMessage('common.temporary_password.whatsapp_message', { login, password });
        if (!text) return;
        window.open(buildWhatsAppUrl(phone, text), '_blank', 'noopener,noreferrer');
    };

    return { isReady, prepare, send };
}
