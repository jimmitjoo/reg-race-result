@props(['event'])
{{-- Cancelled or moved, first of all on the page (#117). --}}
@if ($event->status() === 'cancelled')
    <p role="status" class="rounded-[18px] bg-danger-surface px-5 py-4 text-[17px] font-medium text-danger">{{ __('public.cancelled') }}</p>
@elseif ($event->status() === 'moved')
    <p role="status" class="rounded-[18px] bg-peach-light px-5 py-4 text-[17px] font-medium text-peach-deep">{{ __('public.moved', ['date' => \App\Support\Format::date($event->date)]) }}</p>
@endif
