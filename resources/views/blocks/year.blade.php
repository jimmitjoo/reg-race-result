{{-- One earlier year: its results once published, otherwise who ran. --}}
@php($link = $event->results_public_at ? ['public.results', 'live.title'] : ['public.start-list', 'onsite.start_list'])
<a href="{{ route($link[0], ['organizer' => $organizer->slug, 'event' => $event->slug]) }}" class="inline-flex h-8 items-center rounded-pill border-[1.5px] border-contour px-3 text-[14px] tabular-nums hover:border-ink" aria-label="{{ __($link[1]) }} {{ $event->name }}">{{ $event->date->year }}</a>
