@use('App\Support\Format')
@use('App\Support\Money')
{{-- Invoice basis for a campaign (#85): the club writes the invoice in its own system from this. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('campaigns.invoice_basis') }} – {{ $campaign->name }} – {{ $event->name }}</title>
    <style>
        body { font: 14px/1.5 system-ui, sans-serif; color: #18201c; max-width: 800px; margin: 32px auto; padding: 0 16px; }
        h1 { font-size: 24px; font-weight: 500; margin: 0 0 4px; }
        dl { display: grid; grid-template-columns: 10rem 1fr; gap: 4px 16px; margin: 24px 0; }
        dt { color: #4f5a53; }
        dd { margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 6px 8px 6px 0; border-bottom: 1px solid #d9ded6; }
        th { font-size: 12px; color: #4f5a53; font-weight: 500; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        tfoot td { font-weight: 600; border-bottom: 0; border-top: 2px solid #18201c; }
        button { margin-top: 24px; }
        .logo { float: right; max-height: 56px; max-width: 180px; margin: 0 0 8px 16px; }
        /* No margin on the page leaves no room for the browser's date, address and page numbers. */
        @page { margin: 0; }
        @media print { button { display: none; } body { margin: 0; max-width: none; padding: 14mm; box-decoration-break: clone; -webkit-box-decoration-break: clone; } }
    </style>
</head>
<body>
    @if ($logo = \App\Support\Theme::logoDataUri(\App\Support\Theme::for($event->organizer, $event)))
        <img class="logo" src="{{ $logo }}" alt="{{ $event->organizer->name }}">
    @endif
    <h1>{{ __('campaigns.invoice_basis') }}: {{ $campaign->name }}</h1>
    <p>{{ $event->name }}, {{ Format::date($event->date) }} · {{ $event->organizer->name }}@if ($event->organizer->org_number), {{ __('receipt.org_number') }} {{ $event->organizer->org_number }}@endif</p>

    <dl>
        <dt>{{ __('campaigns.invoice_name') }}</dt><dd>{{ $campaign->invoice_name }}</dd>
        @if ($campaign->invoice_org_number)<dt>{{ __('receipt.org_number_label') }}</dt><dd>{{ $campaign->invoice_org_number }}</dd>@endif
        @if ($campaign->invoice_reference)<dt>{{ __('campaigns.invoice_reference') }}</dt><dd>{{ $campaign->invoice_reference }}</dd>@endif
        @if ($campaign->invoice_email)<dt>{{ __('campaigns.invoice_email') }}</dt><dd>{{ $campaign->invoice_email }}</dd>@endif
        <dt>{{ __('campaigns.status') }}</dt><dd>{{ $campaign->invoice_paid_at ? __('campaigns.invoice_paid').' '.Format::shortDate($campaign->invoice_paid_at) : __('campaigns.invoice_unpaid') }}</dd>
    </dl>

    <table>
        <thead><tr><th>{{ __('receipt.participant') }}</th><th>{{ __('registration.class') }}</th><th>{{ __('campaigns.code') }}</th><th class="num">{{ __('receipt.price') }}</th></tr></thead>
        <tbody>
            @foreach ($registrations as $registration)
                <tr>
                    <td>{{ $registration->displayName() }}</td>
                    <td>{{ trim("{$registration->raceClass->race?->name} – {$registration->raceClass->name}", ' –') }}</td>
                    <td>{{ $registration->campaign_code }}</td>
                    <td class="num">{{ Money::format((int) $registration->price, $currency) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="3">{{ __('registration.total') }}, {{ trans_choice('public.runners', $registrations->count(), ['count' => $registrations->count()]) }}</td><td class="num">{{ Money::format((int) $registrations->sum('price'), $currency) }}</td></tr></tfoot>
    </table>

    <button type="button" onclick="window.print()">{{ __('receipt.print') }}</button>
</body>
</html>
