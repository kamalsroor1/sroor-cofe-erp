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
];
