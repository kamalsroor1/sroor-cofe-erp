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

    // SIGN-11: self-signup email verification code.
    'signup_code' => [
        'subject' => ':code is your :app verification code',
        'heading' => 'Confirm your email',
        'intro' => 'Thanks for signing up on :app. Enter this code on the signup page to confirm your email:',
        'validity' => 'The code is valid for :minutes minutes.',
        'never_share' => 'Do not share this code with anyone. The :app team will never ask for it.',
        'not_you' => 'If you did not sign up, ignore this message and nobody can use your email.',
    ],

    // SIGN-12: "your shop is ready" and "we're on it" after a self-signup.
    'signup_ready' => [
        'subject' => 'Your shop ":business" is ready on :app',
        'heading' => 'Your shop ":business" is ready',
        'intro' => 'Your :app account is set up and you can sign in and start working now.',
        'login_button' => 'Go to your shop',
        'login_url' => 'Sign-in link:',
        'login_email' => 'Sign in with:',
        'password_hint' => 'Password: the one you chose when you signed up.',
        'plan' => 'Plan: :plan',
        'trial_ends' => 'Your trial ends on :date.',
        'help' => 'Help center:',
    ],

    'signup_delayed' => [
        'subject' => 'We are preparing your shop ":business"',
        'heading' => 'We need a little more time',
        'body' => 'Your email is confirmed, but preparing ":business" on :app is taking longer than usual. Our team has been alerted and is on it; we will email you as soon as it is ready.',
        'no_action' => 'There is nothing you need to do right now.',
    ],

    // Security review: the verified address already has a shop (no new one was created).
    'signup_shop_exists' => [
        'subject' => 'You already have a shop on :app',
        'heading' => 'You already have a shop',
        'intro' => 'We confirmed your email, but the details you entered already belong to a shop on :app, so we did not create a new one.',
        'login_button' => 'Sign in to your shop',
        'login_url' => 'Sign-in link:',
        'forgot_password' => 'Forgot your password? Use "Forgot password" on the sign-in page.',
        'contact_support' => 'If you think this is a mistake or you need another shop, contact our support team.',
        'not_you' => 'If you did not try to sign up, you can ignore this message.',
    ],

    'failed' => [
        'telegram' => 'Platform email :mailable failed permanently after all attempts. Recipient domain: :domains. Reason: :exception.',
    ],
];
