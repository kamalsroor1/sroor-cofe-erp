/**
 * Super-admin "change tenant status" form (POST /super-admin/tenants/{id}/toggle-status).
 *
 * Mirrors ToggleTenantStatusRequest + ToggleTenantStatusAction (IDEN-3.3):
 *  - a super-admin may target trial, read_only, suspended or cancelled. `active` is
 *    refused (409): only a verified payment (Billing) activates a tenant, so it is not offered;
 *  - `suspended` requires a reason (TenantSuspensionReason), `cancelled` accepts one;
 *  - `extend_days` is only sent with `trial` (0..3650);
 *  - `note` is optional (max 500) and is kept on the lifecycle event and the audit.
 * The server stays the judge of the transition itself (403/409/422 with error_code).
 *
 * Pure module (no Vue, no translations): validation returns translation KEYS.
 */

export const TENANT_STATUS_TARGETS = Object.freeze(['trial', 'read_only', 'suspended', 'cancelled']);

export const TENANT_SUSPENSION_REASONS = Object.freeze(['non_payment', 'violation', 'customer_request', 'other']);

export const TRIAL_EXTEND_MAX_DAYS = 3650;
export const STATUS_NOTE_MAX_LENGTH = 500;

const FIELDS = ['status', 'extend_days', 'reason', 'note'];

export function emptyTenantStatusForm(currentStatus = null) {
    return {
        status: TENANT_STATUS_TARGETS.includes(currentStatus) ? currentStatus : '',
        extend_days: 0,
        reason: '',
        note: '',
    };
}

export function statusAcceptsReason(status) {
    return status === 'suspended' || status === 'cancelled';
}

export function statusRequiresReason(status) {
    return status === 'suspended';
}

export function statusAcceptsExtendDays(status) {
    return status === 'trial';
}

function toDays(value) {
    const days = Number.parseInt(String(value ?? '').trim() || '0', 10);
    return Number.isNaN(days) ? NaN : days;
}

/** @returns {Record<string, string>} field => translation key (empty object when valid) */
export function validateTenantStatusForm(form) {
    const errors = {};

    if (!TENANT_STATUS_TARGETS.includes(form?.status)) {
        errors.status = 'super.tenant_status.status_required';
        return errors;
    }

    if (statusRequiresReason(form.status) && !TENANT_SUSPENSION_REASONS.includes(form.reason)) {
        errors.reason = 'subscription.errors.suspension_reason_required';
    }

    if (statusAcceptsExtendDays(form.status)) {
        const days = toDays(form.extend_days);
        if (Number.isNaN(days) || days < 0 || days > TRIAL_EXTEND_MAX_DAYS) {
            errors.extend_days = 'super.tenant_status.extend_days_invalid';
        }
    }

    if (String(form.note ?? '').trim().length > STATUS_NOTE_MAX_LENGTH) {
        errors.note = 'super.tenant_status.note_too_long';
    }

    return errors;
}

export function buildTenantStatusPayload(form) {
    const payload = { status: form.status };

    if (statusAcceptsExtendDays(form.status)) {
        payload.extend_days = Math.max(0, toDays(form.extend_days) || 0);
    }

    if (statusAcceptsReason(form.status) && TENANT_SUSPENSION_REASONS.includes(form.reason)) {
        payload.reason = form.reason;
    }

    const note = String(form.note ?? '').trim();
    if (note) payload.note = note;

    return payload;
}

/**
 * Maps a refused toggle-status response to per-field messages (already translated by the
 * server via X-Locale). Returns an empty object when the error does not belong to a field
 * (network, 5xx, 403 permission…): the caller shows a toast instead.
 *
 * @returns {Record<string, string>}
 */
export function mapTenantStatusErrors(error) {
    const status = error?.response?.status;
    const data = error?.response?.data || {};
    const message = data.message || error?.userMessage || '';

    if (status === 422 && data.errors && typeof data.errors === 'object') {
        const mapped = {};
        for (const [field, messages] of Object.entries(data.errors)) {
            const first = Array.isArray(messages) ? messages[0] : messages;
            if (FIELDS.includes(field) && first) mapped[field] = String(first);
        }
        if (Object.keys(mapped).length) return mapped;
    }

    const code = typeof data.error_code === 'string' ? data.error_code : '';
    if (!code.startsWith('subscription.') || !message) return {};

    if (code === 'subscription.suspension_reason_required') return { reason: message };

    const details = data.details || {};
    if (code === 'subscription.invalid_transition' && details.to === 'trial' && details.from === 'trial') {
        return { extend_days: message };
    }

    return { status: message };
}
