{{-- When and where, or that it is cancelled or moved. --}}
@use('App\Support\Format')
@if ($event->status() === 'cancelled')
    <span class="font-medium text-danger">{{ __('public.cancelled') }}</span> · {{ Format::date($event->date) }}
@elseif ($event->status() === 'moved')
    <span class="font-medium text-peach-deep">{{ __('public.moved', ['date' => Format::date($event->date)]) }}</span>
@else
    {{ Format::date($event->date) }}@if ($event->city) · {{ $event->city }}@endif
@endif
