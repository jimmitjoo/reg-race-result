{{--
    The next race and the way to it; light on the brand colour or a picture, the brand colour on a light ground.
    On an upplaga's page, its day and place and the ways on: registration while open, start list, results.
--}}
@use('App\Support\Format')
@php($primary = fn (string $tone) => ['inline-flex h-12 shrink-0 items-center justify-center gap-2 rounded-pill px-6 text-[17px] font-semibold', 'bg-white text-ink hover:bg-ground' => $tone === 'dark', 'bg-brand text-brand-contrast hover:shadow-hover' => $tone === 'light'])
@if ($edition)
    @php($links = [[route('public.start-list', ['organizer' => $organizer->slug, 'event' => $edition->slug]), __('onsite.start_list')]])
    @if ($edition->results_public_at)
        @php(array_unshift($links, [route('public.results', ['organizer' => $organizer->slug, 'event' => $edition->slug]), __('live.title')]))
    @endif
    <div @class(['flex flex-col gap-4', 'items-center' => $center ?? false, $class ?? ''])>
        <p @class(['flex flex-col', 'items-center' => $center ?? false])>
            <span class="text-[19px] font-medium">{{ Format::date($edition->date) }}</span>
            @if ($edition->city)<span class="text-[15px]">{{ $edition->city }}</span>@endif
        </p>
        <div @class(['flex flex-wrap items-center gap-2', 'justify-center' => $center ?? false])>
            @if ($registering)
                <a href="#steg-1" @class($primary($tone))>{{ __('registration.cta') }} <flux:icon.arrow-down class="size-4" /></a>
            @endif
            @foreach ($links as [$url, $label])
                <a href="{{ $url }}" @class([
                    'inline-flex h-12 items-center rounded-pill px-5 text-[15px] font-semibold',
                    'ring-1 ring-current/40 hover:ring-current' => $tone === 'dark',
                    'bg-white text-ink shadow-rest hover:shadow-hover' => $tone === 'light',
                ])>{{ $label }}</a>
            @endforeach
        </div>
    </div>
@elseif ($next)
    <div @class(['flex flex-col gap-4', 'sm:flex-row sm:items-center sm:justify-between' => ! ($stack ?? false), $class ?? ''])>
        <p @class(['flex flex-col', 'items-center' => $center ?? false])>
            <span class="text-[14px] font-medium tracking-wide uppercase">{{ __('page.next') }}</span>
            <span class="text-[19px] font-medium">{{ $next->name }}</span>
            <span class="text-[15px]">{{ Format::date($next->date) }}@if ($next->city) · {{ $next->city }}@endif</span>
        </p>
        <a href="{{ $next->publicUrl() }}" @class([
            ...$primary($tone),
            'self-start',
            'sm:self-auto' => ! ($stack ?? false),
            'self-center' => $center ?? false,
        ])>{{ __('registration.cta') }} <flux:icon.arrow-right class="size-4" /></a>
    </div>
@endif
