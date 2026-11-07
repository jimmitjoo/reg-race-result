<x-mail::message>
# {{ __('forms.mail_heading', ['form' => $form->name]) }}

@foreach ($answers as $answer)
**{{ $answer['label'] }}**<br>
{{ $answer['value'] === '' ? '–' : $answer['value'] }}

@endforeach
@if ($stored)
<x-mail::button :url="$url">{{ __('forms.mail_see_all') }}</x-mail::button>
@endif
</x-mail::message>
