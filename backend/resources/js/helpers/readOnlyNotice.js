// SELL-06: the 423 `subscription.read_only` answer carries the reason, the next step and the
// support contacts. This turns it into what the shared lifecycle alert shows.

export const READ_ONLY_ERROR_CODE = 'subscription.read_only';

const ACTIONS = Object.freeze(['pay_invoice', 'request_invoice']);
// The operator froze the account: nothing to pay or request, the only step is to call support.
export const CONTACT_SUPPORT_ACTION = 'contact_support';

/** Only same-app paths are followed (never an absolute or protocol-relative URL). */
export function safeActionPath(path) {
    return typeof path === 'string' && /^\/(?!\/)[\w\-/?=&.%]*$/.test(path) ? path : null;
}

const escapeHtml = (value) =>
    String(value).replace(
        /[&<>"']/g,
        (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]
    );

/**
 * @param {object} data  response body of the 423
 * `canNavigate` is false when the server named no actionable step (`contact_support`, or a caller
 * without a valid token who only gets `details.status`): the alert then shows the server message only.
 * @returns {{actionKey:string|null, actionPath:string|null, supportParams:{email:string,phone:string}|null,
 *   contactSupport:boolean, canNavigate:boolean}}
 */
export function describeReadOnly(data) {
    if (data?.error_code !== READ_ONLY_ERROR_CODE) {
        return { actionKey: null, actionPath: null, supportParams: null, contactSupport: false, canNavigate: true };
    }
    const details = data.details || {};
    const support = details.support || {};
    const email = support.email ? String(support.email) : '';
    const phone = support.phone ? String(support.phone) : '';
    const actionKey = ACTIONS.includes(details.action) ? details.action : null;
    return {
        actionKey,
        contactSupport: details.action === CONTACT_SUPPORT_ACTION,
        canNavigate: actionKey !== null,
        actionPath: safeActionPath(details.action_path),
        supportParams: email || phone ? { email, phone } : null,
    };
}

/** Support line for the alert footer; values are HTML-escaped (the footer renders HTML). */
export function supportFooter(supportParams, translate) {
    if (!supportParams) return '';
    const contact = [supportParams.email, supportParams.phone].filter(Boolean).map(escapeHtml).join(' · ');
    return `<span dir="auto">${escapeHtml(translate('subscription.alerts.support_contact'))} ${contact}</span>`;
}

/** Body of the alert when support is the only next step: the message, then the contacts in large type. */
export function contactSupportHtml(message, supportParams, translate) {
    const contact = supportParams
        ? [supportParams.email, supportParams.phone].filter(Boolean).map(escapeHtml).join(' · ')
        : '';
    const line = contact
        ? `<p dir="auto" style="margin-top:1rem;font-size:1.125rem;font-weight:900">${escapeHtml(translate('subscription.alerts.support_contact'))} ${contact}</p>`
        : '';
    return `<p dir="auto">${escapeHtml(message)}</p>${line}`;
}
