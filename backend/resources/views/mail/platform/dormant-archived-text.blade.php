{{-- FREE-15: plain-text alternative of App\Mail\DormantShopArchivedMail. --}}
{!! $brand['name'] !!}

{!! __('mail.dormant_archived.heading', ['business' => $businessName]) !!}

{!! __('mail.dormant_archived.intro', ['business' => $businessName, 'days' => $inactiveDays]) !!}

{!! __('mail.dormant_archived.data_kept') !!}
{!! __('mail.dormant_archived.contact_support') !!}

--
{!! __('mail.layout.support_title') !!}
@if ($brand['support_phone'] !== '')
{!! __('mail.layout.phone_label') !!} {!! $brand['support_phone'] !!}
@endif
@if ($brand['support_email'] !== '')
{!! __('mail.layout.email_label') !!} {!! $brand['support_email'] !!}
@endif
{!! __('mail.layout.automated_notice', ['app' => $brand['name']]) !!}
