{{-- SIGN-12: plain-text alternative of App\Mail\SignupProvisioningDelayedMail. --}}
{!! $brand['name'] !!}

{!! __('mail.signup_delayed.heading') !!}

{!! __('mail.signup_delayed.body', ['business' => $businessName, 'app' => $brand['name']]) !!}
{!! __('mail.signup_delayed.no_action') !!}

--
{!! __('mail.layout.support_title') !!}
@if ($brand['support_phone'] !== '')
{!! __('mail.layout.phone_label') !!} {!! $brand['support_phone'] !!}
@endif
@if ($brand['support_email'] !== '')
{!! __('mail.layout.email_label') !!} {!! $brand['support_email'] !!}
@endif
{!! __('mail.layout.automated_notice', ['app' => $brand['name']]) !!}
