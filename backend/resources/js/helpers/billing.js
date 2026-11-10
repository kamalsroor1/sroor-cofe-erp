export const RECEIPT_MAX_BYTES = 5 * 1024 * 1024;
export const RECEIPT_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
export const RECEIPT_ACCEPT = RECEIPT_EXTENSIONS.map((ext) => `.${ext}`).join(',');
export const PAYABLE_METHODS = ['instapay', 'vodafone_cash', 'bank_transfer'];

const TONES = {
    success:
        'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900/60',
    warning:
        'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900/60',
    danger: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900/60',
    info: 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-900/60',
    neutral: 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
};

export const toneClass = (tone) => TONES[tone] || TONES.neutral;

const SUBSCRIPTION_TONES = {
    active: 'success',
    trialing: 'info',
    trial: 'info',
    past_due: 'danger',
    expired: 'danger',
};
const INVOICE_TONES = { paid: 'success', pending: 'warning', void: 'neutral', refunded: 'info', draft: 'neutral' };
const PAYMENT_TONES = {
    verified: 'success',
    pending: 'warning',
    rejected: 'danger',
    failed: 'danger',
    refunded: 'info',
};

export const subscriptionTone = (status) => SUBSCRIPTION_TONES[status] || 'neutral';
export const invoiceTone = (status) => INVOICE_TONES[status] || 'neutral';
export const paymentTone = (status) => PAYMENT_TONES[status] || 'neutral';

/** Usage bar color: green under 70, amber 70-90, red from 90. */
export const usageBarClass = (percent) => {
    if (percent === null || percent === undefined) return 'bg-slate-300 dark:bg-slate-600';
    if (percent >= 90) return 'bg-rose-500';
    if (percent >= 70) return 'bg-amber-500';
    return 'bg-emerald-500';
};

/** Validation messages (translation keys) mirroring the backend receipt rules; null when valid. */
export const receiptErrorKey = (file) => {
    if (!file) return 'subscription.billing.pay.errors.receipt_required';
    const ext = String(file.name || '')
        .split('.')
        .pop()
        .toLowerCase();
    if (!RECEIPT_EXTENSIONS.includes(ext)) return 'subscription.billing.pay.errors.receipt_type';
    if (file.size > RECEIPT_MAX_BYTES) return 'subscription.billing.pay.errors.receipt_size';
    return null;
};

const DECIMAL_PATTERN = /^\d+(\.\d{1,3})?$/;

export const isValidDecimal = (value) => DECIMAL_PATTERN.test(String(value ?? '').trim());

/** Compares two non-negative decimal strings without floats; returns -1, 0 or 1. */
export const compareDecimalStrings = (a, b) => {
    const [aInt, aFrac = ''] = String(a).trim().split('.');
    const [bInt, bFrac = ''] = String(b).trim().split('.');
    const left = BigInt(aInt || '0');
    const right = BigInt(bInt || '0');
    if (left !== right) return left > right ? 1 : -1;
    const scale = Math.max(aFrac.length, bFrac.length);
    const fl = BigInt(aFrac.padEnd(scale, '0') || '0');
    const fr = BigInt(bFrac.padEnd(scale, '0') || '0');
    if (fl === fr) return 0;
    return fl > fr ? 1 : -1;
};

export const todayIso = () => {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
};

export const isPayable = (invoice) =>
    invoice?.status?.value === 'pending' && !invoice.has_pending_payment && Number(invoice.remaining) > 0;

export const lastRejection = (invoice) => {
    const payments = invoice?.payments || [];
    const last = payments[payments.length - 1];
    return last && last.status?.value === 'rejected' ? last.rejection_reason : null;
};
