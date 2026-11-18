{{-- FREE-15: body of App\Mail\DormantShopReminderMail. --}}
@extends('mail.layouts.platform')

@section('content')
    <h1 style="margin:0 0 12px 0; font-size:20px; line-height:1.4; color:#111827;">{{ __('mail.dormant_reminder.heading', ['business' => $businessName]) }}</h1>
    <p style="margin:0 0 16px 0;">{{ __('mail.dormant_reminder.intro', ['business' => $businessName, 'days' => $inactiveDays]) }}</p>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px 0;">
        <tr>
            <td style="border-radius:8px; background-color:{{ $brand['primary_color'] }};">
                <a href="{{ $loginUrl }}" style="display:inline-block; padding:12px 24px; font-weight:bold; color:#ffffff; text-decoration:none;">{{ __('mail.dormant_reminder.login_button') }}</a>
            </td>
        </tr>
    </table>
    <p style="margin:0 0 4px 0;">{{ __('mail.dormant_reminder.login_url') }} <a href="{{ $loginUrl }}" dir="ltr" style="color:{{ $brand['primary_color'] }};">{{ $loginUrl }}</a></p>
    <p style="margin:12px 0 4px 0;">{{ __('mail.dormant_reminder.what_happens', ['date' => $archiveOn]) }}</p>
    <p style="margin:0 0 4px 0;">{{ __('mail.dormant_reminder.data_kept') }}</p>
@endsection
