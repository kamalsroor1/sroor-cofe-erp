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
        'activation_requires_payment' => 'An account can only be activated after its payment is confirmed.',
        'invalid_transition' => 'The account status cannot change from ":from" to ":to".',
        'status_conflict' => 'The account status has changed to ":status". Refresh the page and try again.',
        'archive_not_allowed' => 'Only suspended or cancelled accounts can be archived. Current status: ":status".',
        'not_archived' => 'This account is not archived.',
        'suspension_reason_required' => 'Choose a suspension reason.',
        'limit_reached' => 'You have reached your plan limit for :resource (:max). Upgrade your plan or buy an add-on.',
        'feature_unavailable' => 'This feature is not included in your current plan.',
    ],
];
