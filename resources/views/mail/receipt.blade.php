<x-mail::message>
# {{ __('receipt.heading') }}

{{ __('registration.confirmation_text', ['name' => $registration->first_name.' '.$registration->last_name, 'class' => $class, 'event' => $event->name]) }}

<x-mail::table>
| | |
|:--|:--|
@if ($race)
| {{ __('receipt.race') }} | {{ $race }} |
@endif
| {{ __('receipt.class') }} | {{ $class }} |
| {{ __('receipt.date') }} | {{ $date }} |
| {{ __('receipt.start') }} | {{ $start }} |
| {{ __('receipt.price') }} | {{ $price }}@if ($method) ({{ $method }})@endif |
</x-mail::table>

<x-mail::button :url="$url">
{{ __('receipt.show') }}
</x-mail::button>

{{ __('receipt.reply') }}

[{{ __('privacy.read_terms') }}]({{ $terms }})

{{ $event->organizer->name }}
</x-mail::message>
