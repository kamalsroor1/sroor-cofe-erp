import { ref, watch, onBeforeUnmount } from 'vue';
import centralApi from '../Services/centralApi';
import { notifyError } from '../helpers/alert';
import { useTrans } from './useTrans';

const ENDPOINT = '/super-admin/billing/payments';

/**
 * Receipt of the selected payment as a blob (GET .../receipt returns a signed 5-minute link that is minted and
 * consumed here, never exposed to the UI). The file is read once so the viewer can tell an image from a PDF by its
 * real content type and keep working after the link expires. Retry mints a fresh link.
 */
export function useBillingReceipt(payment) {
    const { t } = useTrans();
    const receipt = ref({ state: 'idle', objectUrl: '', kind: '' });
    let token = 0;

    const release = () => {
        if (receipt.value.objectUrl) URL.revokeObjectURL(receipt.value.objectUrl);
    };

    const reset = () => {
        token += 1;
        release();
        receipt.value = { state: 'idle', objectUrl: '', kind: '' };
    };

    const load = async () => {
        reset();
        const target = payment.value;
        if (!target?.has_receipt) return;
        const ticket = token;
        receipt.value = { state: 'loading', objectUrl: '', kind: '' };
        try {
            const link = await centralApi.get(`${ENDPOINT}/${target.id}/receipt`);
            const file = await fetch(link.data?.data?.url);
            if (!file.ok) throw new Error('receipt');
            const blob = await file.blob();
            const kind = blob.type === 'application/pdf' ? 'pdf' : blob.type.startsWith('image/') ? 'image' : '';
            if (ticket !== token) return;
            if (!kind) throw new Error('receipt');
            receipt.value = { state: 'ready', objectUrl: URL.createObjectURL(blob), kind };
        } catch (error) {
            if (ticket !== token) return;
            receipt.value = { state: 'error', objectUrl: '', kind: '' };
            if (error?.userMessage) notifyError(t('billing.console.receipt.load_failed'), error.userMessage);
        }
    };

    watch(() => payment.value?.id, load, { immediate: true });
    onBeforeUnmount(release);

    return { receipt, retry: load };
}
