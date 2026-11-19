{{-- PRD-22: public signed receipt (read-only, no session). Data comes from GetPublicReceiptAction (allowlist). --}}
@php
    $isRtl = app()->getLocale() !== 'en';
    $money = static fn (string $value): string => number_format((float) $value, 2);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ __('receipts.page_title', ['number' => $invoice['number']]) }}</title>
    <style>
        :root {
            --color-bg: #f1f5f9;
            --color-surface: #ffffff;
            --color-ink: #0f172a;
            --color-muted: #64748b;
            --color-line: #e2e8f0;
            --color-primary: #059669;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --color-bg: #020617;
                --color-surface: #0f172a;
                --color-ink: #f1f5f9;
                --color-muted: #94a3b8;
                --color-line: #1e293b;
                --color-primary: #34d399;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 16px;
            background: var(--color-bg);
            color: var(--color-ink);
            font-family: Tajawal, Cairo, system-ui, -apple-system, 'Segoe UI', sans-serif;
            font-size: 15px;
            line-height: 1.5;
        }
        .receipt {
            max-width: 480px;
            margin: 0 auto;
            background: var(--color-surface);
            border: 1px solid var(--color-line);
            border-radius: 20px;
            padding: 20px;
        }
        .center { text-align: center; }
        .brand-logo { max-height: 64px; max-width: 70%; object-fit: contain; display: block; margin: 0 auto 8px; }
        h1 { font-size: 20px; margin: 0; }
        .muted { color: var(--color-muted); font-size: 13px; margin: 2px 0; }
        .meta, .totals { border-top: 1px dashed var(--color-line); margin-top: 14px; padding-top: 12px; }
        .row { display: flex; justify-content: space-between; gap: 12px; padding: 3px 0; }
        .row strong { font-weight: 800; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th { font-size: 12px; color: var(--color-muted); font-weight: 700; padding: 6px 0; border-bottom: 1px solid var(--color-line); text-align: start; }
        td { padding: 8px 0; border-bottom: 1px solid var(--color-line); vertical-align: top; }
        .num { text-align: end; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .net { font-size: 18px; font-weight: 900; color: var(--color-primary); }
        .footer { margin-top: 16px; text-align: center; font-weight: 700; }
        .notice { margin-top: 12px; text-align: center; color: var(--color-muted); font-size: 12px; }
        .actions { max-width: 480px; margin: 12px auto 0; display: flex; justify-content: center; }
        button {
            min-height: 44px; padding: 0 20px; border-radius: 12px; border: 1px solid var(--color-line);
            background: var(--color-surface); color: var(--color-ink); font: inherit; font-weight: 700; cursor: pointer;
        }
        @media print {
            body { background: #fff; color: #000; padding: 0; }
            .receipt { border: none; }
            .actions, .notice { display: none; }
        }
    </style>
</head>
<body>
    <main class="receipt" data-receipt>
        <header class="center">
            @if ($brand['logo'])
                <img class="brand-logo" src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}">
            @endif
            @if ($brand['name'] !== '')
                <h1>{{ $brand['name'] }}</h1>
            @endif
            @if ($brand['subtitle'] !== '')
                <p class="muted">{{ $brand['subtitle'] }}</p>
            @endif
            @foreach ($brand['header_lines'] as $headerLine)
                <p class="muted">{{ $headerLine }}</p>
            @endforeach
            @if ($brand['phone'] !== '')
                <p class="muted" dir="ltr">{{ $brand['phone'] }}</p>
            @endif
            @if ($brand['address'] !== '')
                <p class="muted">{{ $brand['address'] }}</p>
            @endif
        </header>

        <section class="meta">
            <div class="row"><span>{{ __('receipts.invoice_number') }}</span><strong dir="ltr">{{ $invoice['number'] }}</strong></div>
            <div class="row"><span>{{ __('receipts.date') }}</span><span dir="ltr">{{ $invoice['date'] }} {{ $invoice['time'] }}</span></div>
            @if (! empty($invoice['customer']))
                <div class="row"><span>{{ __('receipts.customer') }}</span><span>{{ $invoice['customer'] }}</span></div>
            @endif
        </section>

        <table>
            <thead>
                <tr>
                    <th>{{ __('receipts.item') }}</th>
                    <th class="num">{{ __('receipts.quantity') }}</th>
                    <th class="num">{{ __('receipts.unit_price') }}</th>
                    <th class="num">{{ __('receipts.line_total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr>
                        <td>{{ $line['name'] }}</td>
                        <td class="num">{{ rtrim(rtrim($line['quantity'], '0'), '.') }}</td>
                        <td class="num">{{ $money($line['unit_price']) }}</td>
                        <td class="num">{{ $money($line['total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <section class="totals">
            <div class="row"><span>{{ __('receipts.subtotal') }}</span><span class="num">{{ $money($invoice['subtotal']) }} {{ $currency }}</span></div>
            @if (bccomp($invoice['discount'], '0', 3) > 0)
                <div class="row"><span>{{ __('receipts.discount') }}</span><span class="num">-{{ $money($invoice['discount']) }} {{ $currency }}</span></div>
            @endif
            @foreach ($invoice['extras'] as $extra)
                <div class="row"><span>+ {{ $extra['title'] }}</span><span class="num">{{ $money($extra['amount']) }} {{ $currency }}</span></div>
            @endforeach
            <div class="row net"><span>{{ __('receipts.net') }}</span><span class="num">{{ $money($invoice['net']) }} {{ $currency }}</span></div>
            <div class="row"><span>{{ __('receipts.paid') }}</span><span class="num">{{ $money($invoice['paid']) }} {{ $currency }}</span></div>
            @if (bccomp($invoice['remaining'], '0', 3) > 0)
                <div class="row"><strong>{{ __('receipts.remaining') }}</strong><strong class="num">{{ $money($invoice['remaining']) }} {{ $currency }}</strong></div>
            @endif
        </section>

        <p class="footer">{{ $brand['footer'] !== '' ? $brand['footer'] : __('receipts.thank_you') }}</p>
        <p class="notice">{{ __('receipts.notice') }}</p>
    </main>
    <div class="actions">
        <button type="button" onclick="window.print()">{{ __('receipts.print') }}</button>
    </div>
</body>
</html>
