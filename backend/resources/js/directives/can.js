import { watchEffect } from 'vue';
import { useAuthStore } from '../stores/auth';

/**
 * v-can="'invoices.cancel'" or v-can="['invoices.cancel', 'pos.access']" (any-of).
 * Hides the element (display:none) when the user lacks the permission and re-evaluates reactively
 * when the permissions change (login, profile refresh). Use v-if when the element must not render at all.
 * Admin bypass lives in authStore.hasPermission.
 */
export const vCan = {
    mounted(el, binding) {
        el.__canValue = binding.value;
        const authStore = useAuthStore();
        el.__canStop = watchEffect(() => {
            el.style.display = authStore.hasPermission(el.__canValue) ? '' : 'none';
        });
    },
    updated(el, binding) {
        if (binding.value !== binding.oldValue) {
            el.__canValue = binding.value;
            el.style.display = useAuthStore().hasPermission(binding.value) ? '' : 'none';
        }
    },
    unmounted(el) {
        el.__canStop?.();
    },
};
