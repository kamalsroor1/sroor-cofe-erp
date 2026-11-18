<?php

declare(strict_types=1);

/*
| SIGN-11..13: public self-signup with an emailed verification code (POST /api/v1/signup*).
*/

return [
    'code_sent' => 'We sent a verification code to your email.',
    'code_resent' => 'We sent a new code to your email.',
    'verified' => 'Your email is confirmed.',

    'errors' => [
        'disabled' => 'Signup is paused for now. Try again later or contact support.',
        'code_invalid' => 'The code is wrong or has expired. Request a new code if needed.',
        'token_invalid' => 'This signup session was not found or has ended. Please sign up again.',
        'already_verified' => 'This email is already confirmed.',
        'code_throttled' => 'Please wait a little before requesting a new code.',
        'not_provisionable' => 'This request is not a verified self-signup waiting in the review queue.',
        'no_tenant' => 'No shop was created from this request.',
        'shop_exists_contact_support' => 'We could not create a new shop with these details. Check your email for the next steps, or contact support.',
        'codes_paused' => 'We cannot send verification codes right now. Please try again later or contact support.',
    ],

    'console' => [
        'provisioning_started' => 'The shop is being prepared; its owner will get an email when it is ready.',
        'tenant_suspended' => 'The shop was suspended.',
        'duplicate_shop_rejected' => 'Refused automatically: this email or phone number already has a shop.',
    ],

    'notify' => [
        'new_signup' => "New verified self-signup\nShop: :business\nName: :contact\nPhone: :phone\nPlan: :plan\nSubdomain: :subdomain",
        'backlog' => ':count self-signups were verified in the last hour. Open the signup log in the console.',
        'cap_reached' => 'The daily automatic shop creation limit (:cap shops) was reached. New verified signups are waiting for manual approval in the console.',
        'provisioning_failed' => "A self-signup shop could not be prepared (request #:id)\nShop: :business\nPhone: :phone\nReason: :code",
        'codes_ceiling' => 'The daily limit of signup verification emails (:cap) was reached. New signups cannot get a code until midnight (Cairo).',
        'network_cap' => 'A network reached its daily limit of automatic shop creations (:cap). Its next verified signups wait for manual approval in the console.',
    ],

    'validation' => [
        'email_not_allowed' => 'This email address cannot be used to sign up. Please use your permanent email.',
        'no_links' => 'Names cannot contain links, email addresses or website names.',
    ],

    'prune' => [
        'description' => 'Delete self-signups that were not verified within the given hours.',
        'done' => 'Deleted :count signups not verified for more than :hours hours.',
        'scrubbed' => 'Revoked :tokens expired signup tokens and cleared the secrets and IP address of :count old finished signups.',
    ],

    'ui' => [
        'form' => [
            'title' => 'Start free in minutes',
            'subtitle' => 'Create your account, confirm your email with a 6-digit code, and your shop is ready.',
            'business_name' => 'Shop name',
            'owner_name' => 'Owner name',
            'owner_name_placeholder' => 'Full name',
            'email' => 'Email',
            'email_hint' => 'We send the confirmation code here. Use your permanent email.',
            'phone' => 'Mobile number',
            'phone_placeholder' => '01xxxxxxxxx',
            'password' => 'Password',
            'password_hint' => 'At least 8 characters.',
            'password_confirmation' => 'Confirm password',
            'business_type' => 'Business type',
            'business_type_placeholder' => 'Choose your business type',
            'city' => 'City (optional)',
            'city_placeholder' => 'Cairo',
            'plan' => 'Plan',
            'plans_failed' => 'Plans could not be loaded. Your account starts on the free plan.',
            'subdomain' => 'Shop address',
            'subdomain_hint' => 'Lowercase English letters, digits and dashes, 3 to 30 characters. Leave it empty and we will pick one for you.',
            'terms_prefix' => 'I agree to the',
            'terms_link' => 'Terms of Service',
            'terms_and' => 'and',
            'privacy_link' => 'Privacy Policy',
            'dpa_link' => 'Data Processing Agreement (DPA)',
            'submit' => 'Create account and send code',
            'submitting' => 'Creating your account...',
        ],
        'otp' => [
            'title' => 'Confirm your email',
            'subtitle' => 'We sent a 6-digit code to :email. It is valid for 15 minutes.',
            'code_label' => 'Verification code',
            'submit' => 'Confirm',
            'verifying' => 'Confirming...',
            'resend' => 'Send a new code',
            'resend_in' => 'Send a new code in :time',
            'resending' => 'Sending...',
            'check_spam' => 'Did not get it? Check your spam folder or send a new code.',
            'wrong_help' => 'After 5 wrong tries the code is cancelled and you need a new one.',
            'start_over' => 'Use a different email',
        ],
        'provisioning' => [
            'title' => 'Preparing your shop',
            'subtitle' => 'This usually takes less than a minute. You can keep this page open.',
            'step_verified' => 'Email confirmed',
            'step_creating' => 'Creating your shop and its database',
            'step_ready' => 'Ready to sign in',
            'ready_title' => 'Your shop is ready',
            'ready_body' => ':shop is ready. Sign in with your email and the password you chose.',
            'open_login' => 'Go to sign in',
            'queued_title' => 'We received your request',
            'queued_body' => 'Many shops are being created right now. Yours will be ready soon and we will email you at :email as soon as it is.',
            'delayed_title' => 'It is taking a little longer',
            'delayed_body' => 'Your shop is still being prepared. We will email you at :email when it is ready, so you can close this page.',
            'failed_title' => 'We could not finish creating your shop',
            'failed_body' => 'Something went wrong while preparing your shop. Please contact support and we will fix it with you.',
            'check_again' => 'Check again',
            'support' => 'Contact support',
            'back_home' => 'Back to home',
        ],
        'aside' => [
            'label' => 'About signing up',
            'verify_title' => 'Confirm your email',
            'verify_body' => 'We send a 6-digit code to your email to confirm it is yours.',
        ],
        'time' => [
            'seconds' => ':count seconds',
            'minutes' => ':count minutes',
        ],
        'errors' => [
            'throttled' => 'Too many attempts. Please try again in :wait.',
            'disabled' => 'Signup is paused for now. Please try again later or contact support.',
            'failed' => 'Something went wrong. Please try again.',
            'session_ended' => 'This signup session ended. Please sign up again.',
            'code_required' => 'Enter the 6-digit code.',
        ],
    ],
];
