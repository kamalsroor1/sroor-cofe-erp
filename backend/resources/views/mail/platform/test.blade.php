{{-- SIGN-10: body of App\Mail\TestPlatformMail (SMTP check sent by `mail:test`). --}}
@extends('mail.layouts.platform')

@section('content')
    <h1 style="margin:0 0 12px 0; font-size:20px; line-height:1.4; color:#111827;">{{ __('mail.test.heading') }}</h1>
    <p style="margin:0 0 12px 0;">{{ __('mail.test.body', ['app' => $brand['name']]) }}</p>
    <p style="margin:0; color:#6b7280; font-size:14px;">{{ __('mail.test.no_action') }}</p>
@endsection
