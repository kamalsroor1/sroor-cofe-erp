{{-- SIGN-11: body of App\Mail\SignupVerificationCodeMail (self-signup email verification code). --}}
@extends('mail.layouts.platform')

@section('content')
    <h1 style="margin:0 0 12px 0; font-size:20px; line-height:1.4; color:#111827;">{{ __('mail.signup_code.heading') }}</h1>
    <p style="margin:0 0 16px 0;">{{ __('mail.signup_code.intro', ['app' => $brand['name']]) }}</p>
    <div dir="ltr" style="margin:0 0 16px 0; padding:16px; text-align:center; font-family:'Courier New', Courier, monospace; font-size:32px; font-weight:bold; letter-spacing:8px; color:#111827; background-color:#f3f4f6; border-radius:8px;">{{ $code }}</div>
    <p style="margin:0 0 12px 0;">{{ __('mail.signup_code.validity', ['minutes' => $validMinutes]) }}</p>
    <p style="margin:0 0 12px 0; color:#6b7280; font-size:14px;">{{ __('mail.signup_code.never_share') }}</p>
    <p style="margin:0; color:#6b7280; font-size:14px;">{{ __('mail.signup_code.not_you') }}</p>
@endsection
