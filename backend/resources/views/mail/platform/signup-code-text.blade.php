{{-- SIGN-11: plain-text alternative of App\Mail\SignupVerificationCodeMail. --}}
{!! $brand['name'] !!}

{!! __('mail.signup_code.heading') !!}

{!! __('mail.signup_code.intro', ['business' => $businessName, 'app' => $brand['name']]) !!}

{!! $code !!}

{!! __('mail.signup_code.validity', ['minutes' => $validMinutes]) !!}
{!! __('mail.signup_code.never_share') !!}
{!! __('mail.signup_code.not_you') !!}

--
{!! __('mail.layout.support_title') !!}
@if ($brand['support_phone'] !== '')
{!! __('mail.layout.phone_label') !!} {!! $brand['support_phone'] !!}
@endif
@if ($brand['support_email'] !== '')
{!! __('mail.layout.email_label') !!} {!! $brand['support_email'] !!}
@endif
{!! __('mail.layout.automated_notice', ['app' => $brand['name']]) !!}
