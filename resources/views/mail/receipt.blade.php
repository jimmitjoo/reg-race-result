<x-mail::message>
# {{ __('receipt.heading') }}

{{ $intro }}

{{ __('receipt.date') }}: {{ $date }}

**{{ $receipt['number'] ? __('receipt.title_number', ['number' => $receipt['number']]) : __('receipt.title') }}** · {{ __('receipt.paid_on', ['date' => $receipt['paidAt']]) }}

<x-mail::table>
| {{ __('receipt.participant') }} | {{ __('receipt.what') }} | {{ __('receipt.price') }} |
|:--|:--|--:|
@foreach ($receipt['lines'] as $line)
| {{ $line['name'] }} | {{ $line['what'] }}, {{ $line['date'] }} | {{ $line['price'] }} |
@endforeach
| **{{ __('registration.total') }}** | {{ $receipt['vat'] }}@if ($receipt['method']), {{ __('receipt.paid_with', ['method' => $receipt['method']]) }}@endif | **{{ $receipt['total'] }}** |
</x-mail::table>

{{ __('receipt.seller') }}: {{ implode(', ', $receipt['seller']) }}<br>
{{ __('receipt.buyer') }}: {{ implode(', ', $receipt['buyer']) }}

<x-mail::button :url="$url">
{{ __('receipt.show') }}
</x-mail::button>

[{{ __('receipt.receipt_link') }}]({{ $receipt['url'] }}) · [{{ __('privacy.read_terms') }}]({{ $terms }})

{{ __('receipt.reply') }}

{{ $receipt['via'] }}
</x-mail::message>
