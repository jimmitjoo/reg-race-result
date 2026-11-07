<x-mail::message>
# {{ __('receipt.heading') }}

@if ($count > 1)
{{ __('registration.confirmation_text_many', ['count' => $count, 'event' => $event->name]) }}
@else
{{ __('registration.confirmation_text', ['name' => $lines[0]['name'], 'class' => $registration->raceClass->name, 'event' => $event->name]) }}
@endif

{{ __('receipt.date') }}: {{ $date }}

<x-mail::table>
| {{ __('registration.runner') }} | {{ __('receipt.class') }} | {{ __('receipt.start') }} | {{ __('receipt.price') }} |
|:--|:--|:--|--:|
@foreach ($lines as $line)
| {{ $line['name'] }} | {{ $line['class'] }} | {{ $line['start'] }} | {{ $line['price'] }} |
@endforeach
| **{{ __('registration.total') }}** | | | **{{ $total }}**@if ($method) ({{ $method }})@endif |
</x-mail::table>

<x-mail::button :url="$url">
{{ __('receipt.show') }}
</x-mail::button>

{{ __('receipt.reply') }}

[{{ __('privacy.read_terms') }}]({{ $terms }})

{{ $event->organizer->name }}
</x-mail::message>
