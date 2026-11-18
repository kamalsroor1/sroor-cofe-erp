{{-- FREE-15: body of App\Mail\DormantShopArchivedMail. --}}
@extends('mail.layouts.platform')

@section('content')
    <h1 style="margin:0 0 12px 0; font-size:20px; line-height:1.4; color:#111827;">{{ __('mail.dormant_archived.heading', ['business' => $businessName]) }}</h1>
    <p style="margin:0 0 16px 0;">{{ __('mail.dormant_archived.intro', ['business' => $businessName, 'days' => $inactiveDays]) }}</p>
    <p style="margin:0 0 4px 0;">{{ __('mail.dormant_archived.data_kept') }}</p>
    <p style="margin:0 0 4px 0;">{{ __('mail.dormant_archived.contact_support') }}</p>
    @if ($brand['support_phone'] !== '')
        <p style="margin:0 0 4px 0;">{{ __('mail.layout.phone_label') }} <span dir="ltr" style="unicode-bidi:embed;">{{ $brand['support_phone'] }}</span></p>
    @endif
    @if ($brand['support_email'] !== '')
        <p style="margin:0 0 4px 0;">{{ __('mail.layout.email_label') }} <span dir="ltr" style="unicode-bidi:embed;">{{ $brand['support_email'] }}</span></p>
    @endif
@endsection
