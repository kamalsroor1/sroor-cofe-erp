{{-- #232 UI §5 (option B): body of App\Mail\BillingReceiptUploadedMail. Inline styles only; no receipt attached. --}}
@extends('mail.layouts.platform')

@section('content')
    <h1 style="margin:0 0 16px 0; font-family:Tajawal, Tahoma, Arial, sans-serif; font-size:20px; line-height:1.4; color:#0d9488;">{{ __('mail.billing_receipt.title') }}</h1>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px 0; font-family:Tajawal, Tahoma, Arial, sans-serif; font-size:14px; line-height:1.8; color:#334155;">
        <tr><td><strong>{{ __('mail.billing_receipt.tenant') }}</strong> {{ $tenantName }}</td></tr>
        <tr><td><strong>{{ __('mail.billing_receipt.invoice') }}</strong> <span dir="ltr" style="unicode-bidi:embed;">{{ $invoiceNumber }}</span> · {{ __('billing.billing_invoice_type.'.$invoiceType) }}</td></tr>
        <tr><td><strong>{{ __('mail.billing_receipt.amount') }}</strong> <span dir="ltr" style="unicode-bidi:embed;">{{ $amount }} {{ $currency }}</span> · {{ __('billing.billing_payment_method.'.$method) }}</td></tr>
        <tr><td><strong>{{ __('mail.billing_receipt.uploaded_at') }}</strong> <span dir="ltr" style="unicode-bidi:embed;">{{ $uploadedAt }}</span></td></tr>
        <tr><td><strong>{{ __('mail.billing_receipt.access') }}</strong>
            @if ($temporaryAccessEndsAt !== null)
                {{ __('mail.billing_receipt.access_until') }} <span dir="ltr" style="unicode-bidi:embed;">{{ $temporaryAccessEndsAt }}</span>
            @else
                <span style="color:#64748b;">{{ __('mail.billing_receipt.access_manual') }}</span>
            @endif
        </td></tr>
    </table>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px 0;">
        <tr>
            <td style="border-radius:10px; background-color:#0d9488;">
                <a href="{{ $reviewUrl }}" style="display:inline-block; padding:0 24px; line-height:48px; height:48px; font-family:Tajawal, Tahoma, Arial, sans-serif; font-weight:bold; color:#ffffff; text-decoration:none;">{{ __('mail.billing_receipt.button') }}</a>
            </td>
        </tr>
    </table>
@endsection

@section('footer')
    <div style="font-family:Tajawal, Tahoma, Arial, sans-serif; color:#64748b;">{{ __('mail.billing_admin.footer', ['app' => $brand['name']]) }}</div>
@endsection
