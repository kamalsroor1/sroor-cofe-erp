{{-- SIGN-12: body of App\Mail\SignupProvisioningDelayedMail ("we're on it"). --}}
@extends('mail.layouts.platform')

@section('content')
    <h1 style="margin:0 0 12px 0; font-size:20px; line-height:1.4; color:#111827;">{{ __('mail.signup_delayed.heading') }}</h1>
    <p style="margin:0 0 12px 0;">{{ __('mail.signup_delayed.body', ['business' => $businessName, 'app' => $brand['name']]) }}</p>
    <p style="margin:0; color:#6b7280; font-size:14px;">{{ __('mail.signup_delayed.no_action') }}</p>
@endsection
