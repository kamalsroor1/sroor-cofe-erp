{{-- SIGN-12: plain-text alternative of App\Mail\SignupAccountReadyMail. --}}
{!! $brand['name'] !!}

{!! __('mail.signup_ready.heading', ['business' => $businessName]) !!}

{!! __('mail.signup_ready.intro', ['app' => $brand['name']]) !!}

{!! __('mail.signup_ready.login_url') !!} {!! $loginUrl !!}
{!! __('mail.signup_ready.login_email') !!} {!! $loginEmail !!}
{!! __('mail.signup_ready.password_hint') !!}
{!! __('mail.signup_ready.plan', ['plan' => $planName]) !!}
@if ($trialEndsOn)
{!! __('mail.signup_ready.trial_ends', ['date' => $trialEndsOn]) !!}
@endif
{!! __('mail.signup_ready.help') !!} {!! $helpUrl !!}

--
{!! __('mail.layout.support_title') !!}
@if ($brand['support_phone'] !== '')
{!! __('mail.layout.phone_label') !!} {!! $brand['support_phone'] !!}
@endif
@if ($brand['support_email'] !== '')
{!! __('mail.layout.email_label') !!} {!! $brand['support_email'] !!}
@endif
{!! __('mail.layout.automated_notice', ['app' => $brand['name']]) !!}
