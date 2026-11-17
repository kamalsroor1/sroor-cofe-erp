<?php

declare(strict_types=1);

/*
| SIGN-10: platform emails (sent by the SaaS operator, not by a shop).
| Layout, the SMTP test message, the mail:test command and the failure alert.
*/

return [
    'layout' => [
        'logo_alt' => ':app logo',
        'support_title' => 'Need help?',
        'support_intro' => 'The :app support team is here to help.',
        'phone_label' => 'Phone / WhatsApp:',
        'email_label' => 'Email:',
        'website_label' => 'Website:',
        'automated_notice' => 'This message was sent automatically by :app.',
        'copyright' => '© :year :name',
    ],

    'test' => [
        'subject' => 'Test message from :app',
        'heading' => 'Email settings are working',
        'body' => 'If you received this message, email delivery for :app is configured correctly.',
        'no_action' => 'No action is needed, you can ignore this message.',
    ],

    'test_command' => [
        'description' => 'Send a platform test email to verify the SMTP settings.',
        'invalid_email' => 'The email address is not valid.',
        'invalid_locale' => 'The locale must be ar or en.',
        'log_mailer_warning' => 'The current mailer (:mailer) writes messages to the log file and does not deliver them. Set MAIL_MAILER=smtp and the SMTP settings first.',
        'queued' => 'The test message was queued for an address at :domain (mailer: :mailer).',
        'queue_hint' => 'A queue worker must be running for the message to be sent. Use --now to send immediately.',
        'sent' => 'The test message was sent to an address at :domain (mailer: :mailer).',
        'failed' => 'Sending failed (:exception): :reason',
    ],

    'failed' => [
        'telegram' => 'Platform email :mailable failed permanently after all attempts. Recipient domain: :domains. Reason: :exception.',
    ],
];
