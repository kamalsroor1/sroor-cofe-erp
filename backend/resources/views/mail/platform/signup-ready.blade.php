{{-- SIGN-12: body of App\Mail\SignupAccountReadyMail. Never a password (the owner chose it). --}}
@extends('mail.layouts.platform')

@section('content')
    <h1 style="margin:0 0 12px 0; font-size:20px; line-height:1.4; color:#111827;">{{ __('mail.signup_ready.heading', ['business' => $businessName]) }}</h1>
    <p style="margin:0 0 16px 0;">{{ __('mail.signup_ready.intro', ['app' => $brand['name']]) }}</p>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px 0;">
        <tr>
            <td style="border-radius:8px; background-color:{{ $brand['primary_color'] }};">
                <a href="{{ $loginUrl }}" style="display:inline-block; padding:12px 24px; font-weight:bold; color:#ffffff; text-decoration:none;">{{ __('mail.signup_ready.login_button') }}</a>
            </td>
        </tr>
    </table>
    <p style="margin:0 0 4px 0;">{{ __('mail.signup_ready.login_url') }} <a href="{{ $loginUrl }}" dir="ltr" style="color:{{ $brand['primary_color'] }};">{{ $loginUrl }}</a></p>
    <p style="margin:0 0 4px 0;">{{ __('mail.signup_ready.login_email') }} <span dir="ltr" style="unicode-bidi:embed;">{{ $loginEmail }}</span></p>
    <p style="margin:0 0 4px 0;">{{ __('mail.signup_ready.password_hint') }}</p>
    <p style="margin:0 0 4px 0;">{{ __('mail.signup_ready.plan', ['plan' => $planName]) }}</p>
    @if ($trialEndsOn)
        <p style="margin:0 0 4px 0;">{{ __('mail.signup_ready.trial_ends', ['date' => $trialEndsOn]) }}</p>
    @endif
    <p style="margin:12px 0 0 0;">{{ __('mail.signup_ready.help') }} <a href="{{ $helpUrl }}" dir="ltr" style="color:{{ $brand['primary_color'] }};">{{ $helpUrl }}</a></p>
@endsection
