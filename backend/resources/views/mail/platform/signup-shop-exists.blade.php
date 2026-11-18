{{-- Security review: body of App\Mail\SignupShopExistsMail. Never names the matched identity. --}}
@extends('mail.layouts.platform')

@section('content')
    <h1 style="margin:0 0 12px 0; font-size:20px; line-height:1.4; color:#111827;">{{ __('mail.signup_shop_exists.heading') }}</h1>
    <p style="margin:0 0 16px 0;">{{ __('mail.signup_shop_exists.intro', ['app' => $brand['name']]) }}</p>
    @if ($loginUrl)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px 0;">
            <tr>
                <td style="border-radius:8px; background-color:{{ $brand['primary_color'] }};">
                    <a href="{{ $loginUrl }}" style="display:inline-block; padding:12px 24px; font-weight:bold; color:#ffffff; text-decoration:none;">{{ __('mail.signup_shop_exists.login_button') }}</a>
                </td>
            </tr>
        </table>
        <p style="margin:0 0 12px 0;">{{ __('mail.signup_shop_exists.login_url') }} <a href="{{ $loginUrl }}" dir="ltr" style="color:{{ $brand['primary_color'] }};">{{ $loginUrl }}</a></p>
        <p style="margin:0 0 12px 0;">{{ __('mail.signup_shop_exists.forgot_password') }}</p>
    @endif
    <p style="margin:0 0 12px 0;">{{ __('mail.signup_shop_exists.contact_support') }}</p>
    <p style="margin:0; color:#6b7280; font-size:14px;">{{ __('mail.signup_shop_exists.not_you') }}</p>
@endsection
