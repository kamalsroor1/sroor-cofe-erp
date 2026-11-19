import Swal from 'sweetalert2';
import { trans } from '../helpers/trans';
import { useAudioFeedback } from './useAudioFeedback';

/**
 * SHOP-02: user-facing side of the counting screen actions (toasts, confirmations, scan feedback)
 * around useStockCountSheet, so the view stays a thin orchestrator.
 */
export function useStockCountActions(sheet) {
    const audio = safeAudio();

    const toast = (icon, text) =>
        Swal.fire({ toast: true, position: 'top', icon, text, timer: 1600, showConfirmButton: false });

    const fail = (err, fallbackKey = 'common.error_occurred') =>
        Swal.fire({ icon: 'error', title: trans('common.error'), text: err?.userMessage || trans(fallbackKey) });

    const scan = (code) => {
        const result = sheet.scan(code);
        if (result.status === 'not_found') {
            audio?.playErrorTone?.();
            toast('warning', trans('stock_counts.scan_not_found', { code: result.code }));
            return;
        }
        if (result.status === 'counted') {
            audio?.playScanBeep?.();
            requestAnimationFrame(() => {
                document
                    .querySelector(`[data-testid="stock-count-line-${CSS.escape(result.line.item_code)}"]`)
                    ?.scrollIntoView({ block: 'nearest' });
            });
        }
    };

    const save = async () => {
        try {
            await sheet.save();
            toast('success', trans('stock_counts.saved'));
        } catch (err) {
            fail(err);
        }
    };

    const post = async (zeroUncounted) => {
        try {
            const message = await sheet.post(zeroUncounted);
            toast('success', message || trans('stock_counts.saved'));
            return true;
        } catch (err) {
            fail(err);
            return false;
        }
    };

    const cancel = async () => {
        const number = sheet.count.value?.count_number;
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            title: trans('stock_counts.cancel_confirm_title', { number }),
            text: trans('stock_counts.cancel_confirm_text'),
            showCancelButton: true,
            confirmButtonText: trans('stock_counts.cancel_confirm_button'),
            cancelButtonText: trans('common.cancel'),
            confirmButtonColor: 'var(--color-danger-solid)',
        });
        if (!isConfirmed) return;
        try {
            const message = await sheet.cancel();
            toast('success', message);
        } catch (err) {
            fail(err);
        }
    };

    const leaveDialog = () => ({
        icon: 'warning',
        text: trans('stock_counts.leave_unsaved'),
        showCancelButton: true,
        confirmButtonText: trans('common.confirm'),
        cancelButtonText: trans('common.cancel'),
    });

    return { scan, save, post, cancel, leaveDialog };
}

function safeAudio() {
    try {
        return useAudioFeedback();
    } catch {
        return null;
    }
}
