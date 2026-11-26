{{-- #232 UI §5 (option B): body of App\Mail\BillingTemporaryAccessReminderMail. Inline styles only. --}}
@extends('mail.layouts.platform')

@section('content')
    <h1 style="margin:0 0 16px 0; font-family:Tajawal, Tahoma, Arial, sans-serif; font-size:20px; line-height:1.4; color:#d97706;">{{ __('mail.billing_grace_reminder.title') }}</h1>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px 0; font-family:Tajawal, Tahoma, Arial, sans-serif; font-size:14px; line-height:1.8; color:#334155;">
        <tr><td><strong>{{ __('mail.billing_grace_reminder.tenant') }}</strong> {{ $tenantName }}</td></tr>
        <tr><td><strong>{{ __('mail.billing_grace_reminder.invoice') }}</strong> <span dir="ltr" style="unicode-bidi:embed;">{{ $invoiceNumber }}</span> · <span dir="ltr" style="unicode-bidi:embed;">{{ $amount }} {{ $currency }}</span>@if ($method !== null) · {{ __('billing.billing_payment_method.'.$method) }}@endif</td></tr>
        <tr><td><strong>{{ __('mail.billing_grace_reminder.ends_at') }}</strong> <span dir="ltr" style="unicode-bidi:embed;">{{ $endsAt }}</span></td></tr>
        <tr><td style="color:#64748b;">{{ __('mail.billing_grace_reminder.consequence') }}</td></tr>
    </table>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px 0;">
        <tr>
            <td style="border-radius:10px; background-color:#d97706;">
                <a href="{{ $reviewUrl }}" style="display:inline-block; padding:0 24px; line-height:48px; height:48px; font-family:Tajawal, Tahoma, Arial, sans-serif; font-weight:bold; color:#ffffff; text-decoration:none;">{{ __('mail.billing_grace_reminder.button') }}</a>
            </td>
        </tr>
    </table>
@endsection

@section('footer')
    <div style="font-family:Tajawal, Tahoma, Arial, sans-serif; color:#64748b;">{{ __('mail.billing_admin.footer', ['app' => $brand['name']]) }}</div>
@endsection
