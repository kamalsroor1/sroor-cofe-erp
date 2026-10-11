import Swal from 'sweetalert2';
import { Capacitor } from '@capacitor/core';
import { Haptics, NotificationType } from '@capacitor/haptics';
import { trans } from './trans';

const triggerToastHaptic = async (type = 'success') => {
    try {
        if (Capacitor.isNativePlatform()) {
            await Haptics.notification({
                type: type === 'error' ? NotificationType.Error : NotificationType.Success,
            });
        } else if (typeof navigator !== 'undefined' && navigator.vibrate) {
            navigator.vibrate(type === 'error' ? [40, 60, 40] : 30);
        }
    } catch (e) {}
};

// 🎨 Base ERP SweetAlert2 Dark Configuration
const DarkSwal = Swal.mixin({
    background: '#090e1a',
    color: '#f8fafc',
    allowOutsideClick: true,
    allowEscapeKey: true,
    showCloseButton: true,
    customClass: {
        popup: 'font-tajawal',
        title: 'font-tajawal',
        htmlContainer: 'font-tajawal',
        confirmButton: 'font-tajawal',
        cancelButton: 'font-tajawal',
        denyButton: 'font-tajawal',
    },
});

// 🍞 Toast Notification Engine
export const Toast = Swal.mixin({
    toast: true,
    position: 'top-start',
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true,
    background: '#0f172a',
    color: '#f8fafc',
    customClass: {
        popup: 'rounded-2xl border border-slate-800 shadow-2xl font-tajawal text-xs',
        title: 'text-xs font-black text-white font-tajawal',
    },
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

/**
 * Show Success Toast / Alert
 */
export const notifySuccess = (title, message = '') => {
    triggerToastHaptic('success');
    return Toast.fire({
        icon: 'success',
        title: title,
        text: message,
    });
};

/**
 * Show Error Toast / Alert
 */
export const notifyError = (title, message = '') => {
    triggerToastHaptic('error');
    return Toast.fire({
        icon: 'error',
        title: title,
        text: message,
    });
};

/**
 * Show Warning Toast / Alert
 */
export const notifyWarning = (title, message = '') => {
    return Toast.fire({
        icon: 'warning',
        title: title,
        text: message,
    });
};

/**
 * Theme classes of a destructive confirm button (the `isDanger` look of confirmDialog). Use through
 * `customClass.confirmButton` instead of a hardcoded `confirmButtonColor` hex.
 */
export const DANGER_CONFIRM_BUTTON_CLASS =
    'px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-black text-xs shadow-lg shadow-rose-600/30 transition cursor-pointer mx-1.5 font-tajawal';

/**
 * Show Modern Interactive Confirmation Dialog
 * Returns Promise<boolean> (true if confirmed, false otherwise)
 */
export const confirmDialog = async ({
    title = trans('common.confirm_dialog.title'),
    text = trans('common.confirm_dialog.text'),
    confirmButtonText = trans('common.confirm_dialog.confirm'),
    cancelButtonText = trans('common.confirm_dialog.cancel'),
    icon = 'warning',
    isDanger = false,
} = {}) => {
    const result = await DarkSwal.fire({
        title,
        text,
        icon,
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText,
        reverseButtons: true,
        customClass: {
            ...DarkSwal.params.customClass,
            confirmButton: isDanger
                ? DANGER_CONFIRM_BUTTON_CLASS
                : 'px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs shadow-lg shadow-emerald-600/30 transition cursor-pointer mx-1.5 font-tajawal',
        },
    });

    return result.isConfirmed;
};

/**
 * Specialized Confirmation for Delete Operations
 */
export const confirmDelete = async (itemName = trans('common.confirm_dialog.this_item'), customWarning = '') => {
    return confirmDialog({
        title: trans('common.confirm_dialog.delete_title', { name: itemName }),
        text: customWarning || trans('common.confirm_dialog.delete_text'),
        confirmButtonText: trans('common.confirm_dialog.delete_confirm'),
        cancelButtonText: trans('common.confirm_dialog.delete_cancel'),
        icon: 'warning',
        isDanger: true,
    });
};

export default DarkSwal;
