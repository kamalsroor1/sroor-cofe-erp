{{-- SIGN-15: plain-text alternative of App\Mail\TenantPasswordResetMail. --}}
{!! $brand['name'] !!}

{!! __('mail.tenant_password_reset.heading') !!}

{!! __('mail.tenant_password_reset.greeting', ['name' => $userName]) !!}
{!! __('mail.tenant_password_reset.intro', ['shop' => $shopName]) !!}

{!! __('mail.tenant_password_reset.link_label') !!} {!! $resetUrl !!}
{!! __('mail.tenant_password_reset.expiry', ['minutes' => $expireMinutes]) !!}

{!! __('mail.tenant_password_reset.not_you') !!}

--
{!! __('mail.layout.support_title') !!}
@if ($brand['support_phone'] !== '')
{!! __('mail.layout.phone_label') !!} {!! $brand['support_phone'] !!}
@endif
@if ($brand['support_email'] !== '')
{!! __('mail.layout.email_label') !!} {!! $brand['support_email'] !!}
@endif
{!! __('mail.layout.automated_notice', ['app' => $brand['name']]) !!}
