<?php

declare(strict_types=1);

/*
 * Tenant permission labels (QA-2). Key = the spatie permission name, so
 * __('permissions.'.$name) resolves e.g. permissions.pos.access.
 * Every name in Database\Seeders\PermissionsSeeder::PERMISSIONS needs a label here and in ar.
 */
return [
    'pos' => [
        'access' => 'Access the point of sale (POS) screen',
    ],
    'invoices' => [
        'view' => 'View the sales invoice history',
        'create' => 'Create and confirm sales invoices',
        'edit' => 'Edit confirmed sales invoices',
        'cancel' => 'Cancel invoices and reverse their stock effect',
        'delete' => 'Delete and archive sales invoices',
        'discount' => 'Grant discounts to customers',
    ],
    'items' => [
        'view' => 'View the item list and prices',
        'create' => 'Add new items to inventory',
        'edit' => 'Edit item details and prices',
        'delete' => 'Archive and delete items',
        'view_cost' => 'See cost prices and profit margins',
    ],
    'inventory' => [
        'adjust' => 'Adjust stock balances manually (counts and deposits)',
    ],
    'purchases' => [
        'view' => 'View the purchase invoice history',
        'create' => 'Record new purchases into stock',
        'delete' => 'Archive purchase invoices',
    ],
    'stores' => [
        'manage' => 'Manage branches and assign staff',
        'view_all' => 'View data of all branches combined',
    ],
    'transfers' => [
        'view' => 'View stock transfer orders',
        'create' => 'Create stock transfers and load distribution vans',
    ],
    'customers' => [
        'manage' => 'Manage the customer directory',
        'statement' => 'View and export a customer statement',
    ],
    'suppliers' => [
        'manage' => 'Manage suppliers and their accounts',
        'statement' => 'View and export a supplier statement',
    ],
    'daily_journal' => [
        'view' => 'View the daily cash journal, drawer movements and shifts',
        'close_shift' => 'Open and close cashier shifts',
        'manage' => 'Manage the treasury and transfers between drawers',
    ],
    'expenses' => [
        'manage' => 'Record, edit and delete expenses',
    ],
    'returns' => [
        'manage' => 'Manage sales and purchase returns',
    ],
    'reports' => [
        'view' => 'View financial and profit reports and branch comparison',
    ],
    'trash' => [
        'access' => 'Access the central recycle bin and restore data',
    ],
    'roles' => [
        'manage' => 'Manage users, roles and permissions',
    ],
    'logs' => [
        'view' => 'View and inspect the activity and audit log',
    ],
    'settings' => [
        'manage' => 'Manage shop, printing and integration settings',
    ],
];
