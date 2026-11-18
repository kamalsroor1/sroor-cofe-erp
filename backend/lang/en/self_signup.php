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
];
