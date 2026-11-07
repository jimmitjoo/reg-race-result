{{-- The next race and the way to it; light on the brand colour or a picture, the brand colour on a light ground. --}}
@use('App\Support\Format')
@if ($next)
    <div @class(['flex flex-col gap-4', 'sm:flex-row sm:items-center sm:justify-between' => ! ($stack ?? false), $class ?? ''])>
        <p @class(['flex flex-col', 'items-center' => $center ?? false])>
            <span class="text-[14px] font-medium tracking-wide uppercase">{{ __('page.next') }}</span>
            <span class="text-[19px] font-medium">{{ $next->name }}</span>
            <span class="text-[15px]">{{ Format::date($next->date) }}@if ($next->city) · {{ $next->city }}@endif</span>
        </p>
        <a href="{{ $next->publicUrl() }}" @class([
            'inline-flex h-12 shrink-0 items-center justify-center gap-2 self-start rounded-pill px-6 text-[17px] font-semibold',
            'bg-white text-ink hover:bg-ground' => $tone === 'dark',
            'bg-brand text-brand-contrast hover:shadow-hover' => $tone === 'light',
            'sm:self-auto' => ! ($stack ?? false),
            'self-center' => $center ?? false,
        ])>{{ __('registration.cta') }} <flux:icon.arrow-right class="size-4" /></a>
    </div>
@endif
