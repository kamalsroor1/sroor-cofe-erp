// #232 UI §2: maps an invoice's server `review_state` to the one status card of the invoice screen.

const T = 'subscription.billing.pay.';

const CARDS = Object.freeze({
    under_review_temporary: {
        tone: 'warning',
        titleKey: `${T}state_review_title`,
        bodyKey: `${T}state_review_grace`,
        actions: ['withdraw'],
    },
    under_review_grace_used: {
        tone: 'warning',
        titleKey: `${T}state_review_title`,
        bodyKey: `${T}state_review_grace_used`,
        actions: ['withdraw'],
    },
    under_review_manual: {
        tone: 'neutral',
        titleKey: `${T}state_manual_title`,
        bodyKey: `${T}state_manual_body`,
        actions: ['withdraw'],
    },
    under_review: {
        tone: 'warning',
        titleKey: `${T}state_review_title`,
        bodyKey: `${T}state_review_plain`,
        actions: ['withdraw'],
    },
    rejected: {
        tone: 'danger',
        titleKey: `${T}state_rejected_title`,
        bodyKey: `${T}state_rejected_reason`,
        actions: ['upload_again'],
    },
    paid: {
        tone: 'success',
        titleKey: `${T}state_paid_title`,
        bodyKey: `${T}state_paid_body`,
        actions: ['view_invoice'],
    },
    void: { tone: 'neutral', titleKey: null, bodyKey: null, actions: [] },
});

/** The status card for a review state; null while the payment steps are shown (awaiting_payment). */
export const invoiceStatusCard = (reviewState) => CARDS[reviewState] || null;

/** The shop's pending payment of an invoice (the one it may withdraw), or null. */
export const pendingPaymentOf = (invoice) =>
    [...(invoice?.payments || [])].reverse().find((payment) => payment.status?.value === 'pending') || null;

/** Reason of the last rejected payment. */
export const rejectionReasonOf = (invoice) =>
    [...(invoice?.payments || [])].reverse().find((payment) => payment.status?.value === 'rejected')
        ?.rejection_reason || '';

/** { start, end } of a paid invoice: the verified transfer time (else the period start) and the period end. */
export const paidSummary = (invoice) => {
    const verified = [...(invoice?.payments || [])].reverse().find((payment) => payment.status?.value === 'verified');
    return {
        start: verified?.transferred_at || invoice?.period_start || null,
        end: invoice?.period_end || null,
    };
};

/** A partial payment already verified: paid > 0 and something still due. */
export const isPartlyPaid = (invoice) =>
    Number(invoice?.paid_amount) > 0 && Number(invoice?.remaining) > 0 && invoice?.status?.value === 'pending';

/** The invoice shows the pay steps (picker, receipt, send) rather than a status card. */
export const showsPaySteps = (invoice) =>
    invoice?.status?.value === 'pending' &&
    (!invoice.review_state || invoice.review_state === 'awaiting_payment' || invoice.review_state === 'rejected');
