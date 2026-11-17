{{-- SIGN-10: plain-text alternative of App\Mail\TestPlatformMail. --}}
{!! $brand['name'] !!}

{!! __('mail.test.heading') !!}

{!! __('mail.test.body', ['app' => $brand['name']]) !!}
{!! __('mail.test.no_action') !!}

--
{!! __('mail.layout.support_title') !!}
@if ($brand['support_phone'] !== '')
{!! __('mail.layout.phone_label') !!} {!! $brand['support_phone'] !!}
@endif
@if ($brand['support_email'] !== '')
{!! __('mail.layout.email_label') !!} {!! $brand['support_email'] !!}
@endif
{!! __('mail.layout.automated_notice', ['app' => $brand['name']]) !!}
