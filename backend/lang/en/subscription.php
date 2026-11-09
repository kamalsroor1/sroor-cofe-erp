<?php

/*
| Tenant subscription lifecycle (IDEN-3.1). Created in Wave 1; later tasks
| (ENTI-2.1, IDEN-3.x) APPEND their keys at the end of this file. Keep the same
| keys in lang/ar/subscription.php.
*/

return [
    'statuses' => [
        'trial' => 'Trial',
        'active' => 'Active',
        'past_due' => 'Past due',
        'read_only' => 'Read-only',
        'suspended' => 'Suspended',
        'cancelled' => 'Cancelled',
        'archived' => 'Archived',
    ],

    'actors' => [
        'system' => 'System',
        'super_admin' => 'Platform admin',
        'billing' => 'Billing',
    ],

    'access_levels' => [
        'full' => 'Full access',
        'read_only' => 'View and export only',
        'blocked' => 'Access suspended',
    ],

    'state_messages' => [
        'trial' => 'You are on a free trial. Days left: :days',
        'active' => 'Your subscription is active.',
        'past_due' => 'Your subscription period has ended. Renew before the account becomes read-only. Days left: :days',
        'read_only' => 'Your account is read-only: you can view and export your data but cannot add or change anything. Renew before the account is suspended. Days left: :days',
        'suspended' => 'Your account is suspended. Contact the platform team or renew your subscription to reactivate it.',
        'cancelled' => 'Your subscription is cancelled. Contact the platform team to reactivate it.',
        'archived' => 'Your account is archived. Contact the platform team.',
    ],

    'trial_extension' => [
        'already_used' => 'This account\'s trial was already extended. The extension can be used only once.',
        'already_paid' => 'The trial cannot be extended for an account that has already paid.',
        'not_eligible' => 'The trial cannot be extended in the account\'s current status.',
    ],

    // CTO W1 Q2 (IDEN-3.2): reason codes of a suspension / cancellation.
    'suspension_reasons' => [
        'non_payment' => 'Non-payment',
        'violation' => 'Terms of use violation',
        'customer_request' => 'Customer request',
        'other' => 'Other reason',
    ],

    // ENTI-2.1: names of plan limits used in subscription.errors.limit_reached.
    'limit_resources' => [
        'users' => 'users',
        'stores' => 'branches',
        'warehouses' => 'warehouses',
        'vans' => 'vans',
        'items' => 'items',
        'invoices_month' => 'monthly invoices',
        'storage_mb' => 'storage',
        'other' => 'this resource',
    ],

    // IDEN-3.3 (TenantLifecycleException) + ENTI-2.1 (entitlement exceptions).
    'errors' => [
        'read_only' => 'This account is read-only: you cannot add or change data. Renew your subscription to reactivate it.',
        'access_blocked' => 'Access to this account is blocked (status: :status). Contact the platform team.',
        'access_blocked_generic' => 'Access to this account is currently unavailable. Contact the platform team.',
        'activation_requires_payment' => 'An account can only be activated after its payment is confirmed.',
        'invalid_transition' => 'The account status cannot change from ":from" to ":to".',
        'status_conflict' => 'The account status has changed to ":status". Refresh the page and try again.',
        'archive_not_allowed' => 'Only suspended or cancelled accounts can be archived. Current status: ":status".',
        'not_archived' => 'This account is not archived.',
        'suspension_reason_required' => 'Choose a suspension reason.',
        'limit_reached' => 'You have reached your plan limit for :resource (:max). Upgrade your plan or buy an add-on.',
        'feature_unavailable' => 'This feature is not included in your current plan.',
        'plan_has_tenants' => 'This plan still has tenants. Move them to another plan before deleting it.',
    ],

    // OPS-9: asynchronous archive (DELETE /super-admin/tenants/{id}) and the CLI purge.
    'archive_states' => [
        'queued' => 'Archive queued',
        'running' => 'Archiving: taking the final backup',
        'failed' => 'Archive failed',
    ],

    'archive_error_codes' => [
        'final_backup_failed' => 'The final backup could not be taken or verified',
        'status_changed' => 'The account status changed before the archive finished',
        'purged' => 'The account was purged',
        'unknown' => 'Unexpected error (see the server log)',
    ],

    'archive_errors' => [
        'final_backup_required' => 'An account can only be archived after its final backup is uploaded and verified.',
        'archive_in_progress' => 'An archive request for this account is already in progress.',
        'already_archived' => 'This account is already archived.',
        'tenant_purged' => 'This account was permanently deleted and can no longer be changed.',
        'archive_failed' => 'The archive did not complete and the account status was not changed: :reason.',
        'purge_refused' => 'Permanent deletion refused: :reason.',
        'confirm_code_mismatch' => 'Type the shop code exactly to confirm.',
    ],

    // Titles of the SPA alert shown when the API refuses a request because of the subscription state.
    'alerts' => [
        'read_only_title' => 'Account is read-only',
        'blocked_title' => 'Account access is blocked',
        'feature_unavailable_title' => 'Feature not in your plan',
        'limit_reached_title' => 'Plan limit reached',
    ],

    // PRD-04: tenant subscription & billing page (/billing).
    'billing' => [
        'page' => [
            'title' => 'Subscription & billing',
            'subtitle' => 'Your plan, usage, invoices and payments',
            'load_failed' => 'Could not load your subscription details.',
            'retry' => 'Try again',
        ],
        'plan' => [
            'card_title' => 'Current plan',
            'plan' => 'Plan',
            'cycle' => 'Billing cycle',
            'founder' => 'Founder price',
            'ends_at' => 'Subscription ends',
            'trial_ends_at' => 'Trial ends',
            'no_end_date' => 'No end date',
            'days_left' => ':days days left',
            'day_left_one' => '1 day left',
            'expires_today' => 'Ends today',
            'overdue_days' => 'Overdue by :days days',
            'open_invoices' => 'Open invoices: :count',
            'read_only_notice' => 'Your account is read-only. Pay your invoice to restore full access.',
            'blocked_notice' => 'Your account access is suspended. Pay your invoice or contact support.',
            'request_renewal' => 'Request renewal invoice',
            'pay_now' => 'Pay now / renew subscription',
            'change_plan' => 'Change plan',
        ],
        'renewal' => [
            'dialog_title' => 'Renew subscription',
            'dialog_text' => 'Choose the renewal period',
            'confirm' => 'Issue invoice',
            'cycle_required' => 'Choose a renewal period',
            'existing_opened' => 'You already have an open invoice (:number). Opening it now.',
        ],
        'change_plan' => [
            'title' => 'Change plan',
            'text' => 'Plan changes are handled by our support team. Contact us and we will switch your plan.',
            'phone' => 'Phone',
            'email' => 'Email',
            'no_contact' => 'Please contact the platform support team.',
        ],
        'usage' => [
            'title' => 'Usage and limits',
            'unlimited' => 'Unlimited',
            'of_limit' => ':used of :limit',
            'empty' => 'No usage data yet.',
        ],
        'addons' => [
            'title' => 'Add-ons',
            'quantity' => 'Quantity: :count',
            'ends_at' => 'Ends :date',
        ],
        'invoices' => [
            'title' => 'Invoices',
            'number' => 'Invoice',
            'type' => 'Type',
            'status' => 'Status',
            'total' => 'Total',
            'remaining' => 'Remaining',
            'due' => 'Due date',
            'details' => 'Details',
            'pay' => 'Pay',
            'pending_review' => 'Payment under review',
            'rejected_reason' => 'Last payment rejected: :reason',
            'empty_title' => 'No invoices yet',
            'empty_description' => 'Your subscription invoices will appear here.',
        ],
        'pay' => [
            'title' => 'Pay invoice :number',
            'instructions_title' => 'Payment details',
            'instructions_hint' => 'Transfer the amount, then upload the receipt below.',
            'instapay_address' => 'InstaPay address',
            'vodafone_cash_number' => 'Vodafone Cash number',
            'account_name' => 'Account name',
            'payment_note' => 'Note',
            'copy' => 'Copy',
            'copied' => 'Copied',
            'contact_support' => 'Payment details are not available yet. Contact support: :phone',
            'amount' => 'Amount paid',
            'method' => 'Payment method',
            'payer_reference' => 'Transfer reference or sender number',
            'paid_at' => 'Payment date',
            'receipt' => 'Receipt',
            'choose_receipt' => 'Choose receipt file',
            'receipt_hint' => 'JPG, PNG, WEBP or PDF, up to 5 MB',
            'remove_receipt' => 'Remove file',
            'submit' => 'Submit payment',
            'pending_notice' => 'A payment for this invoice is already under review.',
            'errors' => [
                'receipt_required' => 'Attach the receipt.',
                'receipt_type' => 'The receipt must be a JPG, PNG, WEBP or PDF file.',
                'receipt_size' => 'The receipt must not exceed 5 MB.',
                'method_required' => 'Choose a payment method.',
                'amount_invalid' => 'Enter a valid amount.',
                'amount_out_of_range' => 'The amount must be between 0 and :remaining.',
                'reference_length' => 'The reference must be 3 to 100 characters.',
                'paid_at_required' => 'Choose the payment date.',
                'paid_at_future' => 'The payment date cannot be in the future.',
            ],
        ],
        'details' => [
            'title' => 'Invoice :number',
            'period' => 'Period',
            'issued_at' => 'Issued',
            'paid_at' => 'Paid',
            'lines' => 'Items',
            'description' => 'Description',
            'quantity' => 'Qty',
            'unit_price' => 'Unit price',
            'amount' => 'Amount',
            'founder' => 'Founder',
            'subtotal' => 'Subtotal',
            'discount' => 'Discount',
            'tax' => 'Tax (:rate%)',
            'total' => 'Total',
            'paid' => 'Paid',
            'remaining' => 'Remaining',
            'payments' => 'Payments',
            'no_payments' => 'No payments submitted yet.',
            'method' => 'Method',
            'reference' => 'Reference',
            'submitted_at' => 'Submitted',
            'rejection_reason' => 'Rejection reason',
            'notes' => 'Notes',
            'print' => 'Print invoice',
            'open_print' => 'Print / PDF',
        ],
        'print' => [
            'heading' => 'Subscription invoice',
            'paid_stamp' => 'PAID',
            'not_found' => 'Invoice not found.',
            'invoice_to' => 'Billed to',
        ],
    ],
];
