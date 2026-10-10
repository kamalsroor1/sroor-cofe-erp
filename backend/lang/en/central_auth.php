<?php

// IDEN-1.3: platform-operator (CentralUser) authentication, /api/v1/super-admin/auth/*.
return [
    'failed' => 'The email or password is incorrect.',
    'login_success' => 'Signed in to the platform console.',
    'two_factor_required' => 'A two-factor code is required to finish signing in.',
    'logout_success' => 'Signed out of the platform console.',
    'unauthenticated' => 'Unauthorized. Sign in to the platform console first.',
    'session_expired' => 'Your platform console session has expired. Please sign in again.',
    // IDEN-1.12: mandatory two-factor (TOTP), step-up and password reset.
    'two_factor_setup_needed' => 'Signed in. Set up two-factor authentication to continue.',
    'two_factor_setup_required' => 'Set up two-factor authentication before using the platform console.',
    'two_factor_challenge_invalid' => 'This sign-in attempt has expired or is no longer valid. Sign in again.',
    'two_factor_invalid' => 'The verification code is incorrect.',
    'two_factor_enabled' => 'Scan the code with your authenticator app, then confirm it with a verification code.',
    'two_factor_confirmed' => 'Two-factor authentication is on. Save your recovery codes somewhere safe.',
    'two_factor_already_confirmed' => 'Two-factor authentication is already set up for this account.',
    'two_factor_not_enabled' => 'Start the two-factor setup first.',
    'step_up_required' => 'Confirm your identity with a verification code to continue.',
    'step_up_confirmed' => 'Identity confirmed.',
    'password_reset_link_sent' => 'If this email belongs to a platform account, a password reset link has been sent to it.',
    'password_reset_invalid' => 'This password reset link is invalid or has expired.',
    'password_reset_success' => 'Your password has been reset. Sign in again on every device.',
    // W2-B3 security: accounts moved by central:migrate-super-admins must set a new password first.
    'password_reset_required' => 'You must reset your password before signing in. Use "Forgot password" to set a new one.',
    'password_reset_mail' => [
        'subject' => 'Reset your platform console password',
        'greeting' => 'Hello :name,',
        'intro' => 'We received a request to reset the password of your platform console account.',
        'action' => 'Reset password',
        'expiry' => 'This link expires in :minutes minutes.',
        'ignore' => 'If you did not request a password reset, ignore this email; your password stays the same.',
    ],
];
