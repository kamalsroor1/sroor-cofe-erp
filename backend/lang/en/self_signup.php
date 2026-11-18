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
    ],
];
