{{-- #232 UI §5: plain-text alternative of App\Mail\BillingTemporaryAccessReminderMail. --}}
{!! $brand['name'] !!}

{!! __('mail.billing_grace_reminder.title') !!}

{!! __('mail.billing_grace_reminder.tenant') !!} {!! $tenantName !!}
{!! __('mail.billing_grace_reminder.invoice') !!} {!! $invoiceNumber !!} · {!! $amount !!} {!! $currency !!}@if ($method !== null) · {!! __('billing.billing_payment_method.'.$method) !!}@endif

{!! __('mail.billing_grace_reminder.ends_at') !!} {!! $endsAt !!}
{!! __('mail.billing_grace_reminder.consequence') !!}

{!! __('mail.billing_grace_reminder.button') !!}: {!! $reviewUrl !!}

--
{!! __('mail.billing_admin.footer', ['app' => $brand['name']]) !!}
