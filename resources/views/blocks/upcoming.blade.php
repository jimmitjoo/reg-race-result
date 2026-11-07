{{-- The coming upplagor, read from the arrangemang: they cannot go out of date. --}}
@use('App\Support\Format')
<section class="flex flex-col gap-4">
    <h2 class="text-[26px] leading-[32px] font-medium sm:text-[32px] sm:leading-[38px]">{{ trim($block['heading']) ?: __('public.upcoming') }}</h2>
    @if ($upcoming->isEmpty())
        <p class="rounded-card bg-white p-7 text-[17px] text-ink-soft shadow-rest">{{ __('public.no_upcoming') }}</p>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($upcoming as $event)
                <a href="{{ $event->publicUrl() }}" class="group flex flex-col gap-4 rounded-card bg-white p-6 shadow-rest hover:shadow-hover sm:p-7">
                    <span class="flex flex-col gap-1">
                        <span class="text-[21px] leading-[28px] font-medium group-hover:underline">{{ $event->name }}</span>
                        <span class="text-[15px] text-ink-soft">{{ Format::date($event->date) }}@if ($event->city) · {{ $event->city }}@endif</span>
                    </span>
                    @if ($event->status() !== 'scheduled')
                        <span class="text-[15px] font-medium {{ $event->status() === 'cancelled' ? 'text-danger' : 'text-peach-deep' }}">{{ $event->status() === 'cancelled' ? __('public.cancelled') : __('public.moved', ['date' => Format::date($event->date)]) }}</span>
                    @else
                        <span class="mt-auto inline-flex items-center gap-2 text-[15px] font-semibold text-brand">{{ __('registration.cta') }} <flux:icon.arrow-right class="size-4" /></span>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</section>
