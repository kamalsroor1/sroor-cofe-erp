<?php

declare(strict_types=1);

/*
| Assisted signup (PRD-05): public "request a workspace" form and its console review.
*/

return [
    'submitted' => 'Thank you. We received your request and will contact you shortly to set up your workspace.',
    'approved' => 'The request was approved and the workspace is being prepared.',
    'rejected' => 'The request was rejected.',

    'validation' => [
        'duplicate_pending' => 'We already have a pending request for this phone number. We will contact you soon.',
        'plan_unknown' => 'The selected plan is not available.',
        'subdomain_format' => 'The workspace code must be 3 to 30 characters: lowercase English letters, digits and dashes, not starting or ending with a dash.',
        'subdomain_reserved' => 'This workspace code is reserved. Please choose another one.',
        'subdomain_taken' => 'This workspace code is already taken. Please choose another one.',
        'terms_required' => 'You must accept the Terms of Service, Privacy Policy and Data Processing Agreement before sending the request.',
    ],

    'errors' => [
        'queue_full' => 'The service is busy right now. Please try again later.',
        'not_pending' => 'This request was already handled.',
        'no_trial_plan' => 'No active plan is available to start this workspace on.',
        'provisioning_failed' => 'The workspace could not be created. Check the workspace code and try again.',
    ],

    'notify' => [
        'backlog' => 'Signup backlog: :count requests are pending review. Per-request alerts are paused for an hour.',
        'new_request' => "New signup request\nBusiness: :business\nContact: :contact\nPhone: :phone\nPlan: :plan",
    ],

    'business_types' => [
        'retail' => 'Retail shop',
        'wholesale' => 'Wholesale',
        'weighted' => 'Sold by weight',
        'other' => 'Other',
    ],

    'statuses' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],

    'form' => [
        'title' => 'Request a trial account',
        'subtitle' => 'Enter your business details and we will contact you to set up the account.',
        'business_name' => 'Business name',
        'contact_name' => 'Contact person',
        'phone' => 'Mobile number',
        'phone_placeholder' => '01xxxxxxxxx',
        'contact_name_placeholder' => 'Full name',
        'email_placeholder' => 'name@example.com',
        'city_placeholder' => 'Cairo',
        'notes_placeholder' => 'Number of branches, the devices you have, anything you want us to know…',
        'email' => 'Email (optional)',
        'city' => 'City (optional)',
        'business_type' => 'Business type',
        'plan' => 'Plan (optional)',
        'plan_none' => 'Not sure yet',
        'subdomain' => 'Preferred workspace code (optional)',
        'subdomain_hint' => 'Lowercase English letters, digits and dashes, 3 to 30 characters.',
        'notes' => 'Notes (optional)',
        'terms_prefix' => 'I agree to the',
        'terms_link' => 'Terms of Service',
        'terms_and' => 'and',
        'privacy_link' => 'Privacy Policy',
        'dpa_link' => 'Data Processing Agreement (DPA)',
        'submit' => 'Send request',
        'submitting' => 'Sending...',
        'success_title' => 'We received your request',
        'back_home' => 'Back to home',
        'view_pricing' => 'View pricing',
        'throttled' => 'Too many attempts. Please wait a moment and try again.',
        'failed' => 'The request could not be sent. Please try again.',
        'plans_failed' => 'Plans could not be loaded. You can still send the request without choosing a plan.',
    ],

    'aside' => [
        'label' => 'Your request details',
        'selected_plan' => 'Selected plan',
        'change' => 'Change',
        'next_title' => 'What happens next',
        'review_title' => 'We review your request',
        'review_body' => 'Our team reviews your details and calls you on your mobile.',
        'help' => 'Need help?',
    ],

    'console' => [
        'page_title' => 'Signup requests',
        'page_subtitle' => 'Sign-up requests coming from the pricing page. Review them, then approve or reject.',
        'nav' => 'Signup requests',
        'search_placeholder' => 'Search by name or mobile',
        'load_failed' => 'The requests could not be loaded.',
        'empty_title' => 'No requests',
        'empty_description' => 'There are no requests in this section right now.',
        'fields' => [
            'contact' => 'Contact',
            'phone' => 'Mobile',
            'email' => 'Email',
            'city' => 'City',
            'type' => 'Business',
            'plan' => 'Plan',
            'subdomain' => 'Preferred code',
            'notes' => 'Notes',
            'date' => 'Requested on',
            'rejection_reason' => 'Rejection reason',
        ],
        'approve' => 'Approve',
        'reject' => 'Reject',
        'approve_title' => 'Approve request and create the account',
        'subdomain_label' => 'Workspace code',
        'plan_label' => 'Plan',
        'owner_email' => 'Owner email',
        'owner_password' => 'Owner password',
        'generate_password' => 'Generate password',
        'copy_password' => 'Copy password',
        'copied' => 'Copied.',
        'password_hint' => 'Copy the password now and hand it to the customer. It will not be shown again.',
        'approve_confirm' => 'Approve and create account',
        'preparing_title' => 'The account is being prepared',
        'preparing_description' => 'The request was approved and the workspace is being prepared. It will appear in the tenants list in a moment.',
        'already_handled' => 'This request was already handled. The list was refreshed.',
        'reject_title' => 'Reject request',
        'reject_label' => 'Rejection reason (3 to 500 characters)',
        'reject_confirm' => 'Confirm rejection',
        'reject_reason_length' => 'The rejection reason must be 3 to 500 characters.',
        'action_failed' => 'The operation could not be completed.',
        'password_mode_auto' => 'Generate the password automatically (recommended)',
        'password_mode_auto_hint' => 'The system creates a temporary password and shows it to you once after approval. The owner changes it at first sign-in.',
        'password_mode_manual' => 'I will type the password myself',
        'password_manual_required' => 'Type a password or choose automatic generation.',
        'credentials_title' => 'Shop owner sign-in details',
    ],
];
