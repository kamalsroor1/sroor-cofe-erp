<?php

declare(strict_types=1);

// MOB-05: public account & data deletion page, request form and console queue.
return [
    'submitted' => 'Your request has been recorded. Our team will contact you within 3 working days to verify your identity before anything is deleted.',
    'form' => [
        'title' => 'Account deletion request',
        'subtitle' => 'Send us your details and we will contact you to verify the request. Nothing is deleted before verification.',
        'contact_name' => 'Full name',
        'phone' => 'Mobile number',
        'phone_hint' => 'The number registered on the account, e.g. 01012345678',
        'email' => 'Email (optional)',
        'shop_code' => 'Shop code (optional)',
        'shop_code_hint' => 'The code you type when opening the app, e.g. my-shop',
        'reason' => 'Anything we should know? (optional)',
        'confirm' => 'I understand that deletion is final and cannot be undone once carried out.',
        'submit' => 'Send deletion request',
        'submitting' => 'Sending...',
        'success_title' => 'Request received',
        'error_title' => 'The request could not be sent',
        'too_many' => 'Too many requests from this network. Please try again later.',
        'privacy_link' => 'Read the privacy policy',
    ],
    'related' => [
        'title' => 'Want to delete your account?',
        'body' => 'See what is deleted, what is retained, and send a deletion request.',
        'link' => 'Account and data deletion',
    ],
    'settings' => [
        'title' => 'Privacy and account',
        'body' => 'Read how your data is handled, or ask us to delete your account and data.',
    ],
    'validation' => [
        'confirm_required' => 'Please confirm that you understand deletion is final.',
        'phone_invalid' => 'Enter a valid Egyptian mobile number (11 digits starting with 01).',
        'shop_code_invalid' => 'The shop code may contain only English letters, digits and dashes.',
    ],
    'notify' => [
        'new_request' => "🗑️ New account deletion request\nName: :contact\nPhone: :phone\nEmail: :email\nShop code: :shop\nVerify the requester before deleting anything.",
    ],
    'statuses' => [
        'pending' => 'Pending',
        'handled' => 'Handled',
        'all' => 'All',
    ],
    'console' => [
        'nav' => 'Deletion requests',
        'page_title' => 'Account deletion requests',
        'page_subtitle' => 'Verify the requester, carry the deletion out by hand, then mark the request handled.',
        'search_placeholder' => 'Search by name, phone, email or shop code',
        'empty' => 'No requests here.',
        'load_failed' => 'Could not load the requests.',
        'retry' => 'Retry',
        'columns' => [
            'requester' => 'Requester',
            'shop_code' => 'Shop code',
            'reason' => 'Reason',
            'received' => 'Received',
            'status' => 'Status',
            'actions' => 'Actions',
        ],
        'mark_handled' => 'Mark handled',
        'handle_title' => 'Mark the request handled',
        'handle_note_label' => 'What was done? (optional)',
        'handle_confirm' => 'Mark handled',
        'handled_success' => 'The request was marked handled.',
        'already_handled' => 'This request was already handled.',
        'action_failed' => 'The action failed',
        'handled_by' => 'Handled by :name',
    ],
];
