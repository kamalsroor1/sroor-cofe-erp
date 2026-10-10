<?php

return [
    'workspace_not_ready' => 'This workspace is still being set up. Please try again shortly, and contact support if it persists.',
    'retry_not_allowed' => 'Provisioning cannot be retried now: its current status is ":status". Retry is only available after a failure, or when setup has made no progress for too long.',
    'retry_unavailable' => 'Provisioning cannot be retried: the first admin\'s data is no longer available. Create the tenant again or contact operations.',
    'requires_ready_workspace' => 'This action needs a ready workspace. Its setup status is currently ":status".',
    'retry_queued' => 'Workspace provisioning has been queued again and will be ready in a few minutes.',
    'forbidden' => 'You are not allowed to manage tenant provisioning.',
    'company_subtitle_default' => 'Sales, inventory and branch management',
    'statuses' => [
        'pending' => 'Waiting for setup',
        'running' => 'Setting up',
        'ready' => 'Ready',
        'failed' => 'Setup failed',
    ],
    'errors' => [
        'database_exists' => 'A database with this name already exists and is not empty, or was not created by us.',
        'invalid_database_name' => 'The database name is not valid.',
        'database_create_failed' => 'The database could not be created or reached.',
        'database_user_failed' => 'The tenant\'s database user could not be created.',
        'migration_failed' => 'Setting up the database tables failed.',
        'seed_failed' => 'Setting up the base data (permissions, main branch, admin) failed.',
        'timeout' => 'Setup took longer than allowed.',
        'unexpected' => 'An unexpected error happened during setup.',
    ],
];
