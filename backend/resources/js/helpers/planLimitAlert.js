import Swal from 'sweetalert2';
import { trans } from './trans';

// The plan-limit / locked-feature refusal (403 subscription.*): a clear alert with an "Upgrade" button
// that opens the billing page. Only users who may manage billing get the button; the others are told
// to ask the shop owner. Shared by the axios interceptor and the POS pre-checkout guard.

const goToBilling = () => {
    if (window.spaRouter) window.spaRouter.push({ name: 'billing.index' });
    else window.location.assign('/billing');
};

async function canUpgrade() {
    if (window.location.pathname.startsWith('/billing')) return false;
    const { useAuthStore } = await import('../stores/auth');
    return useAuthStore().hasPermission('billing.manage');
}

/**
 * @param {{titleKey: string, message: string}} options  translation key of the title and the server's text
 *   (which already names the resource and the limit)
 */
export async function showUpgradeAlert({ titleKey, message }) {
    const upgrade = await canUpgrade();
    // Another dialog may be open: replace it rather than leave the user without the message.
    const isDark = document.documentElement.classList.contains('dark');
    const result = await Swal.fire({
        icon: 'warning',
        title: trans(titleKey),
        text: message,
        ...(upgrade ? {} : { footer: trans('subscription.alerts.ask_owner_to_upgrade') }),
        confirmButtonText: upgrade ? trans('subscription.alerts.upgrade_plan') : trans('common.ok'),
        confirmButtonColor: 'var(--color-primary)',
        showCancelButton: upgrade,
        cancelButtonText: trans('common.close'),
        reverseButtons: true,
        background: isDark ? '#0f172a' : '#ffffff',
        color: isDark ? '#f8fafc' : '#0f172a',
        customClass: { confirmButton: 'min-h-11', cancelButton: 'min-h-11' },
    });
    if (upgrade && result.isConfirmed) goToBilling();
}
