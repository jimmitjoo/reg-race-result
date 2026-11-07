@props(['event', 'page' => null])
{{-- Högby IF › Sylvesterloppet › 2026 › Startlista (#117): where the page sits, and the way back. --}}
@php
    $organizer = $event->organizer;
    $crumbs = [[$organizer->name, route('public.organizer', $organizer->slug)]];
    if ($event->series) {
        $crumbs[] = [$event->series->name, route('public.event', ['organizer' => $organizer->slug, 'event' => $event->series->slug])];
    }
    $crumbs[] = [$event->series ? (string) $event->date->year : $event->name, route('public.event', ['organizer' => $organizer->slug, 'event' => $event->slug])];
    if ($page) {
        $crumbs[] = [$page, null];
    }
@endphp
<nav aria-label="{{ __('public.breadcrumbs') }}" {{ $attributes->class('text-[15px] text-ink-soft') }}>
    <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
        @foreach ($crumbs as [$label, $url])
            <li class="flex items-center gap-2">
                @if (! $loop->first)<span aria-hidden="true">›</span>@endif
                @if ($loop->last)
                    <span aria-current="page">{{ $label }}</span>
                @else
                    <a href="{{ $url }}" class="underline decoration-contour hover:text-ink">{{ $label }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
