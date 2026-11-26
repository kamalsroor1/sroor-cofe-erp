{{--
    SIGN-10: shared layout of every platform email (App\Mail\PlatformMailable).
    Email-client safe: table layout, inline styles, system fonts only, light background,
    max width 600px (fluid on phones). RTL for Arabic, LTR for English.
    Provided by PlatformMailable::content(): $mailLocale, $mailDir, $mailAlign, $brand, $year.
    Child views fill @section('content'); an optional @section('footer') replaces the support block.
--}}
<!DOCTYPE html>
<html lang="{{ $mailLocale }}" dir="{{ $mailDir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $brand['name'] }}</title>
    <style>
        @media only screen and (max-width: 620px) {
            .mail-container { width: 100% !important; }
            .mail-body { padding: 24px 18px !important; }
        }
    </style>
</head>
<body dir="{{ $mailDir }}" style="margin:0; padding:0; background-color:#f4f5f7; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" dir="{{ $mailDir }}" style="background-color:#f4f5f7;">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <table role="presentation" class="mail-container" width="600" cellpadding="0" cellspacing="0" border="0" dir="{{ $mailDir }}" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                {{-- Header: logo / name on the brand colour bar --}}
                <tr>
                    <td style="height:6px; line-height:6px; font-size:0; background-color:{{ $brand['primary_color'] }};">&nbsp;</td>
                </tr>
                <tr>
                    <td align="center" style="padding:28px 24px 8px 24px;">
                        @if ($brand['logo_url'])
                            <img src="{{ $brand['logo_url'] }}" alt="{{ __('mail.layout.logo_alt', ['app' => $brand['name']]) }}" height="48" style="display:block; height:48px; max-width:200px; border:0; outline:none; text-decoration:none;">
                        @endif
                        <div style="margin-top:8px; font-family:Tahoma, Arial, 'Segoe UI', sans-serif; font-size:20px; font-weight:bold; color:{{ $brand['primary_color'] }};">{{ $brand['name'] }}</div>
                    </td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td class="mail-body" dir="{{ $mailDir }}" style="padding:24px 36px 32px 36px; font-family:Tahoma, Arial, 'Segoe UI', sans-serif; font-size:16px; line-height:1.7; color:#1f2937; text-align:{{ $mailAlign }};">
                        @yield('content')
                    </td>
                </tr>

                {{-- Footer: support contact --}}
                <tr>
                    <td dir="{{ $mailDir }}" style="padding:20px 36px 28px 36px; background-color:#f9fafb; border-top:1px solid #e5e7eb; font-family:Tahoma, Arial, 'Segoe UI', sans-serif; font-size:13px; line-height:1.7; color:#6b7280; text-align:{{ $mailAlign }};">
                        @hasSection('footer')
                            {{-- #232: operator e-mails carry their own footer instead of the shop support block. --}}
                            @yield('footer')
                        @else
                        <div style="font-weight:bold; color:#374151;">{{ __('mail.layout.support_title') }}</div>
                        <div>{{ __('mail.layout.support_intro', ['app' => $brand['name']]) }}</div>
                        @if ($brand['support_phone'] !== '')
                            <div>{{ __('mail.layout.phone_label') }} <span dir="ltr" style="unicode-bidi:embed;"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $brand['support_phone']) }}" style="color:{{ $brand['primary_color'] }}; text-decoration:none;">{{ $brand['support_phone'] }}</a></span></div>
                        @endif
                        @if ($brand['support_email'] !== '')
                            <div>{{ __('mail.layout.email_label') }} <a href="mailto:{{ $brand['support_email'] }}" style="color:{{ $brand['primary_color'] }}; text-decoration:none;">{{ $brand['support_email'] }}</a></div>
                        @endif
                        @if ($brand['website_url'] !== '' && preg_match('#^https?://#i', $brand['website_url']) === 1)
                            <div>{{ __('mail.layout.website_label') }} <a href="{{ $brand['website_url'] }}" style="color:{{ $brand['primary_color'] }}; text-decoration:none;">{{ $brand['website_url'] }}</a></div>
                        @endif
                        @endif
                        <div style="margin-top:12px; font-size:12px; color:#9ca3af;">{{ __('mail.layout.automated_notice', ['app' => $brand['name']]) }}</div>
                        <div style="font-size:12px; color:#9ca3af;">{{ __('mail.layout.copyright', ['year' => $year, 'name' => $brand['legal_name']]) }}</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
