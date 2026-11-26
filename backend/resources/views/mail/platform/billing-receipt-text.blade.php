{{-- #232 UI §5: plain-text alternative of App\Mail\BillingReceiptUploadedMail. --}}
{!! $brand['name'] !!}

{!! __('mail.billing_receipt.title') !!}

{!! __('mail.billing_receipt.tenant') !!} {!! $tenantName !!}
{!! __('mail.billing_receipt.invoice') !!} {!! $invoiceNumber !!} · {!! __('billing.billing_invoice_type.'.$invoiceType) !!}
{!! __('mail.billing_receipt.amount') !!} {!! $amount !!} {!! $currency !!} · {!! __('billing.billing_payment_method.'.$method) !!}
{!! __('mail.billing_receipt.uploaded_at') !!} {!! $uploadedAt !!}
@if ($temporaryAccessEndsAt !== null)
{!! __('mail.billing_receipt.access') !!} {!! __('mail.billing_receipt.access_until') !!} {!! $temporaryAccessEndsAt !!}
@else
{!! __('mail.billing_receipt.access') !!} {!! __('mail.billing_receipt.access_manual') !!}
@endif

{!! __('mail.billing_receipt.button') !!}: {!! $reviewUrl !!}

--
{!! __('mail.billing_admin.footer', ['app' => $brand['name']]) !!}
