/**
 * Pure helpers of the super-admin payment review workspace (#232 UI spec 4.1 / 4.2 / 6b).
 * No Vue, no network: the composables feed these with API payloads.
 */

export const REVIEW_CHECKS = Object.freeze(['amount', 'recipient', 'statement']);
export const REJECT_QUICK_REASONS = Object.freeze(['amount', 'missing', 'unclear', 'recipient']);
export const URGENT_SECONDS = 6 * 3600;
export const NOTIFICATIONS_PATH = '/super-admin/billing';

/** DECIMAL string -> milli units (3 decimals) as BigInt; null when not a plain decimal. */
function toMilli(value) {
    const text = String(value ?? '')
        .trim()
        .replace(/,/g, '');
    const match = /^(-?)(\d+)(?:\.(\d+))?$/.exec(text);
    if (!match) return null;
    const fraction = `${match[3] || ''}000`.slice(0, 3);
    const milli = BigInt(match[2]) * 1000n + BigInt(fraction);
    return match[1] === '-' ? -milli : milli;
}

/** -1 / 0 / 1 comparing two decimal strings; null when either is unreadable. */
export function compareMoney(a, b) {
    const left = toMilli(a);
    const right = toMilli(b);
    if (left === null || right === null) return null;
    if (left === right) return 0;
    return left > right ? 1 : -1;
}

/** True when the declared amount is a readable number that differs from the invoice amount due. */
export function amountsDiffer(declared, due) {
    const result = compareMoney(declared, due);
    return result !== null && result !== 0;
}

/** True when the decimal string is above zero (overpaid, remaining due). */
export function isPositiveMoney(value) {
    const milli = toMilli(value);
    return milli !== null && milli > 0n;
}

/**
 * How the queue row / detail shows the payment's review state:
 * grace (running), urgent (running, 6 h or less), ended (window over, still pending), manual
 * (not classified as a transfer receipt, no window), or none.
 */
export function reviewChip(payment, now = Date.now()) {
    if (payment?.status?.value !== 'pending') return { kind: 'none', hours: 0 };
    const window = payment.invoice_temporary_access || {};
    if (window.state === 'active') {
        const endsAt = Date.parse(window.ends_at || '');
        const seconds = Number.isNaN(endsAt)
            ? Number(window.seconds_left) || 0
            : Math.max(0, Math.floor((endsAt - now) / 1000));
        return {
            kind: seconds <= URGENT_SECONDS ? 'urgent' : 'grace',
            hours: Math.max(1, Math.ceil(seconds / 3600)),
        };
    }
    if (window.state === 'expired') return { kind: 'ended', hours: 0 };
    if (payment.receipt_classification?.value === 'not_receipt') return { kind: 'manual', hours: 0 };
    return { kind: 'none', hours: 0 };
}

export const CHIP_CLASSES = Object.freeze({
    grace: 'bg-amber-50 border-amber-500 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200',
    urgent: 'bg-rose-50 border-rose-500 text-rose-900 dark:bg-rose-500/10 dark:text-rose-200',
    ended: 'bg-slate-100 border-slate-400 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    manual: 'bg-slate-100 border-slate-400 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
});

/** Translation key (+ params) of the invoice type line of the detail facts. */
export function invoiceTypeLabel(payment) {
    const change = payment?.invoice_plan_change;
    switch (payment?.invoice_type) {
        case 'upgrade':
            return change?.from && change?.to
                ? { key: 'billing.console.verify.invoice_upgrade', params: { old: change.from, new: change.to } }
                : { key: 'billing.console.verify.invoice_upgrade_plain', params: {} };
        case 'renewal':
            return { key: 'billing.console.verify.invoice_renewal', params: {} };
        case 'plan':
            return change?.to
                ? { key: 'billing.console.verify.invoice_plan', params: { plan: change.to } }
                : { key: 'billing.console.verify.invoice_plan_plain', params: {} };
        case 'addon':
            return { key: 'billing.console.verify.invoice_addon', params: {} };
        case 'service':
            return { key: 'billing.console.verify.invoice_service', params: {} };
        default:
            return null;
    }
}

/** Approve needs the transfer time, every confirmation checked and no future time. */
export function canApprove({ transferredAt, checks, future = false }) {
    if (!transferredAt || future) return false;
    return REVIEW_CHECKS.every((key) => checks?.[key] === true);
}

/** True when the datetime-local string (browser local time) lies after `now`. */
export function isFutureLocal(local, now = Date.now()) {
    if (!local) return false;
    const time = new Date(local).getTime();
    return !Number.isNaN(time) && time > now;
}

export const emptyChecks = () => Object.fromEntries(REVIEW_CHECKS.map((key) => [key, false]));

/** The payment to show first after a decision: the next row of the list, else null. */
export function nextSelection(list, decidedId) {
    const index = list.findIndex((item) => item.id === decidedId);
    if (index === -1) return list[0] || null;
    return list[index + 1] || list[index - 1] || null;
}

/** The vue-router location a panel notification opens (the payment in the review workspace). */
export function notificationLocation(notification) {
    const paymentId = notification?.link?.query?.payment ?? notification?.billing_payment_id;
    return paymentId
        ? { path: NOTIFICATIONS_PATH, query: { payment: String(paymentId) } }
        : { path: NOTIFICATIONS_PATH };
}

/** `?payment=` query value -> positive integer id, else null. */
export function paymentIdFromQuery(value) {
    const raw = Array.isArray(value) ? value[0] : value;
    return /^\d+$/.test(String(raw ?? '')) && Number(raw) > 0 ? Number(raw) : null;
}

/** Notification severity -> status-colour classes of its dot. */
export function notificationDotClass(severity) {
    return severity === 'warning' ? 'bg-amber-500' : 'bg-theme-primary';
}

/** Which of the quick reasons text equals the textarea content (to mark the chip). */
export function quickReasonActive(text, reason) {
    return String(text ?? '').trim() === reason;
}

export const PAYMENT_METHODS = Object.freeze({
    vodafone_cash: Object.freeze({
        required: ['billing_vodafone_cash_number'],
        optional: [],
        note: 'billing_vodafone_cash_note',
    }),
    instapay: Object.freeze({
        required: ['billing_instapay_address'],
        optional: [],
        note: 'billing_instapay_note',
    }),
    bank_transfer: Object.freeze({
        required: [
            'billing_bank_name',
            'billing_bank_account_holder',
            'billing_bank_account_number',
            'billing_bank_iban',
        ],
        optional: ['billing_bank_branch'],
        note: 'billing_bank_note',
    }),
});

/** Setting key -> `billing.console.settings.*` label key. */
export const SETTING_LABEL_KEYS = Object.freeze({
    billing_vodafone_cash_number: 'vodafone_cash_number',
    billing_instapay_address: 'instapay_address',
    billing_bank_name: 'bank_name',
    billing_bank_account_holder: 'bank_account_holder',
    billing_bank_account_number: 'bank_account_number',
    billing_bank_iban: 'bank_iban',
    billing_bank_branch: 'bank_branch',
});

const filled = (form, key) => String(form?.[key] ?? '').trim() !== '';

/**
 * Computed card status of a payment method from the form (mirrors the server's
 * PaymentMethodDetails::statuses: unfilled = hidden, never a toggle).
 * status: visible | missing (some required empty, something filled) | empty (nothing filled).
 */
export function methodStatus(method, form) {
    const definition = PAYMENT_METHODS[method];
    if (!definition) return { status: 'empty', visible: false, missing: [] };
    const missing = definition.required.filter((key) => !filled(form, key));
    const anyFilled = [...definition.required, ...definition.optional, definition.note].some((key) =>
        filled(form, key)
    );
    return {
        status: missing.length === 0 ? 'visible' : anyFilled ? 'missing' : 'empty',
        visible: missing.length === 0,
        missing,
    };
}

const S = 'billing.console.settings.';
const noteField = (key) => ({
    key,
    label: `${S}method_note`,
    placeholder: `${S}method_note_placeholder`,
    hint: `${S}method_note_hint`,
    required: false,
    multiline: true,
    maxlength: 300,
});

/** Fields of each payment-method card (settings key, label / placeholder translation keys, input attributes). */
export const METHOD_FIELDS = Object.freeze({
    vodafone_cash: [
        {
            key: 'billing_vodafone_cash_number',
            label: `${S}vodafone_cash_number`,
            placeholder: `${S}vodafone_cash_number_placeholder`,
            required: true,
            ltr: true,
            type: 'tel',
            inputmode: 'tel',
            maxlength: 20,
        },
        noteField('billing_vodafone_cash_note'),
    ],
    instapay: [
        {
            key: 'billing_instapay_address',
            label: `${S}instapay_address`,
            placeholder: `${S}instapay_address_placeholder`,
            required: true,
            ltr: true,
            maxlength: 100,
        },
        noteField('billing_instapay_note'),
    ],
    bank_transfer: [
        { key: 'billing_bank_name', label: `${S}bank_name`, required: true, maxlength: 120 },
        { key: 'billing_bank_account_holder', label: `${S}bank_account_holder`, required: true, maxlength: 120 },
        {
            key: 'billing_bank_account_number',
            label: `${S}bank_account_number`,
            required: true,
            ltr: true,
            inputmode: 'numeric',
            maxlength: 40,
        },
        {
            key: 'billing_bank_iban',
            label: `${S}bank_iban`,
            placeholder: `${S}bank_iban_placeholder`,
            required: true,
            ltr: true,
            maxlength: 40,
        },
        { key: 'billing_bank_branch', label: `${S}bank_branch`, required: false, maxlength: 120 },
        noteField('billing_bank_note'),
    ],
});

/** Everything a method's card edits: details first, then the note. */
export const METHOD_SETTING_KEYS = Object.freeze(
    Object.values(METHOD_FIELDS).flatMap((fields) => fields.map((field) => field.key))
);

/**
 * What the shop sees for one method, built from the form: only filled detail rows, plus the note.
 * Mirrors PaymentMethodDetails::forShops() (the branch is listed when filled and is not copyable).
 */
export function methodPreviewRows(method, form) {
    const definition = PAYMENT_METHODS[method];
    if (!definition) return { rows: [], note: '' };
    const keys = [...definition.required, ...definition.optional];
    const rows = keys
        .filter((key) => filled(form, key))
        .map((key) => ({
            key,
            label: `${S}${SETTING_LABEL_KEYS[key]}`,
            value: String(form[key]).trim(),
            copyable: key !== 'billing_bank_branch',
        }));
    return { rows, note: String(form?.[definition.note] ?? '').trim() };
}
