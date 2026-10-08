<?php

return [
    'immutable' => 'The platform audit log is append-only. Entries cannot be changed or deleted.',
    'events' => [
        'login_succeeded' => 'Operator signed in',
        'login_failed' => 'Failed operator sign-in',
        'logout' => 'Operator signed out',
        'two_factor_enabled' => 'Two-factor authentication enabled',
        'two_factor_disabled' => 'Two-factor authentication disabled',
        'password_changed' => 'Operator password changed',
        'central_user_created' => 'Operator account created',
        'central_user_updated' => 'Operator account updated',
        'central_user_deactivated' => 'Operator account deactivated',
        'central_user_deleted' => 'Operator account deleted',
        'impersonation_started' => 'Support session started',
        'impersonation_exchanged' => 'Support session opened in the shop',
        'impersonation_ended' => 'Support session ended',
        'impersonation_destructive_used' => 'Restricted action used during a support session',
        'tenant_created' => 'Shop created',
        'tenant_updated' => 'Shop updated',
        'tenant_suspended' => 'Shop suspended',
        'tenant_reactivated' => 'Shop reactivated',
        'tenant_trial_extended' => 'Shop trial extended',
        'tenant_deleted' => 'Shop deleted',
        'subscription_activated' => 'Subscription activated',
        'plan_updated' => 'Plan updated',
    ],
];
