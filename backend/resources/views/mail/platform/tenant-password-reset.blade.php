{{-- SIGN-15: body of App\Mail\TenantPasswordResetMail. --}}
@extends('mail.layouts.platform')

@section('content')
    <h1 style="margin:0 0 12px 0; font-size:20px; line-height:1.4; color:#111827;">{{ __('mail.tenant_password_reset.heading') }}</h1>
    <p style="margin:0 0 12px 0;">{{ __('mail.tenant_password_reset.greeting', ['name' => $userName]) }}</p>
    <p style="margin:0 0 16px 0;">{{ __('mail.tenant_password_reset.intro', ['shop' => $shopName]) }}</p>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px 0;">
        <tr>
            <td style="border-radius:8px; background-color:{{ $brand['primary_color'] }};">
                <a href="{{ $resetUrl }}" style="display:inline-block; padding:12px 24px; font-weight:bold; color:#ffffff; text-decoration:none;">{{ __('mail.tenant_password_reset.button') }}</a>
            </td>
        </tr>
    </table>
    <p style="margin:0 0 12px 0;">{{ __('mail.tenant_password_reset.expiry', ['minutes' => $expireMinutes]) }}</p>
    <p style="margin:0 0 12px 0; word-break:break-all;">{{ __('mail.tenant_password_reset.link_label') }} <a href="{{ $resetUrl }}" dir="ltr" style="color:{{ $brand['primary_color'] }};">{{ $resetUrl }}</a></p>
    <p style="margin:0; color:#6b7280; font-size:14px;">{{ __('mail.tenant_password_reset.not_you') }}</p>
@endsection
