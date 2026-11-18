{{-- FREE-15: plain-text alternative of App\Mail\DormantShopReminderMail. --}}
{!! $brand['name'] !!}

{!! __('mail.dormant_reminder.heading', ['business' => $businessName]) !!}

{!! __('mail.dormant_reminder.intro', ['business' => $businessName, 'days' => $inactiveDays]) !!}

{!! __('mail.dormant_reminder.login_url') !!} {!! $loginUrl !!}

{!! __('mail.dormant_reminder.what_happens', ['date' => $archiveOn]) !!}
{!! __('mail.dormant_reminder.data_kept') !!}

--
{!! __('mail.layout.support_title') !!}
@if ($brand['support_phone'] !== '')
{!! __('mail.layout.phone_label') !!} {!! $brand['support_phone'] !!}
@endif
@if ($brand['support_email'] !== '')
{!! __('mail.layout.email_label') !!} {!! $brand['support_email'] !!}
@endif
{!! __('mail.layout.automated_notice', ['app' => $brand['name']]) !!}
