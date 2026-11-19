import api from '../Services/api';
import { useTrans } from './useTrans';
import { buildWhatsAppUrl } from '../helpers/whatsappLink';

/**
 * PRD-22: share an invoice on WhatsApp with a signed public receipt link.
 * POST /api/v1/invoices/{id}/receipt-link returns { url, expires_at } (valid 30 days by default).
 * When the link cannot be created (offline, permission, cancelled), the text is sent without it.
 */
export function useReceiptShare() {
    const { t } = useTrans();

    const fetchReceiptLink = async (invoiceId) => {
        if (!invoiceId) return null;
        try {
            const res = await api.post(`/invoices/${invoiceId}/receipt-link`);
            return res.data?.data?.url || null;
        } catch {
            return null;
        }
    };

    /** Message text plus the receipt link line (when a link was created). */
    const withReceiptLink = async (invoiceId, text) => {
        const url = await fetchReceiptLink(invoiceId);
        return url ? `${text}\n${t('invoices.share.receipt_link_line', { url })}` : text;
    };

    const shareOnWhatsApp = async ({ invoiceId, phone, text }) => {
        const message = await withReceiptLink(invoiceId, text);
        window.open(buildWhatsAppUrl(phone, message), '_blank');
    };

    return { fetchReceiptLink, withReceiptLink, shareOnWhatsApp };
}
