@use('App\Support\Format')
@use('App\Support\Money')
@php($class = $registration->raceClass)
@php($eventUrl = route('public.event', ['organizer' => $event->organizer->slug, 'event' => $event->series?->slug ?? $event->slug]))
<x-layouts.public :organizer="$event->organizer" :event="$event" :title="__('public.title', ['page' => __('registration.confirmation_heading'), 'event' => $event->name, 'organizer' => $event->organizer->name])">
    <div class="mx-auto flex max-w-[720px] flex-col gap-8">
        <header class="flex flex-col gap-3">
            <x-breadcrumbs :event="$event" :page="__('registration.your_registration')" />
            @if ($registration->paid_at || $registration->payment_method === 'invoice')
                <h1 class="text-[38px] leading-[42px] font-normal tracking-[-0.02em] text-balance sm:text-[52px] sm:leading-[56px]">{{ __('registration.confirmation_heading') }}</h1>
                <p class="text-[17px] text-ink-soft sm:text-[19px]">{{ $order->count() > 1 ? __('registration.confirmation_text_many', ['count' => $order->count(), 'event' => $event->name]) : __('registration.confirmation_text', ['name' => $registration->displayName(), 'class' => $class->name, 'event' => $event->name]) }}</p>
            @else
                <h1 class="text-[38px] leading-[42px] font-normal tracking-[-0.02em] text-balance sm:text-[52px] sm:leading-[56px]">{{ __('registration.unpaid_heading') }}</h1>
                <p class="text-[17px] text-ink-soft sm:text-[19px]">{{ __('registration.unpaid_text') }}</p>
            @endif
        </header>

        <section aria-labelledby="anmalan" class="rounded-card bg-white p-5 shadow-rest sm:p-7">
            <h2 id="anmalan" class="sr-only">{{ $order->count() > 1 ? __('registration.your_order') : __('registration.your_registration') }}</h2>
            <ul class="flex flex-col gap-4">
                @foreach ($order as $entry)
                    <li class="flex items-baseline justify-between gap-4">
                        <span class="flex flex-col gap-0.5">
                            <span class="text-[17px] font-medium">{{ $entry->displayName() }}</span>
                            <span class="text-[15px] text-ink-soft">{{ $entry->raceClass->race?->name }} – {{ $entry->raceClass->name }}, {{ Format::time($entry->raceClass->start_at->setTimezone($event->timezone)) }}</span>
                        </span>
                        @if ($entry->price !== null)
                            <span class="text-[17px] tabular-nums">{{ Money::format($entry->price, $event->organizer->currency) }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            <p class="mt-4 text-[15px] text-ink-soft">{{ Format::date($event->date) }}@if ($event->city) · {{ $event->city }}@endif</p>
            @if ($order->count() > 1 || ($registration->paid_at && $registration->payment_method))
                <div class="mt-4 flex items-baseline justify-between border-t border-contour pt-4">
                    <span class="text-[15px] text-ink-soft">{{ __('registration.total') }}@if ($registration->paid_at && $registration->payment_method) · {{ __("registrations.method.{$registration->payment_method}") }}@endif</span>
                    <span class="text-[21px] font-medium tabular-nums">{{ Money::format($order->sum('price'), $event->organizer->currency) }}</span>
                </div>
            @endif
            @if ($registration->paid_at && $registration->email)
                <p class="mt-6 border-t border-contour pt-4 text-[15px] text-ink-soft">{{ __('registration.receipt_sent', ['email' => $registration->email]) }}</p>
            @endif
        </section>

        @if (! $registration->paid_at && $registration->payment_method === 'invoice')
            <p class="rounded-card bg-white/60 px-5 py-4 text-[15px] text-ink-soft sm:px-7">{{ __('registration.invoiced_by', ['name' => $registration->campaign?->invoice_name ?: $registration->campaign?->name]) }}</p>
        @endif

        <div class="flex flex-wrap gap-2">
            @if ($registration->paid_at || $registration->payment_method === 'invoice')
                <a href="{{ URL::signedRoute('public.registration.calendar', ['organizer' => $event->organizer->slug, 'event' => $event->slug, 'registration' => $registration]) }}" class="inline-flex h-11 items-center rounded-pill bg-brand px-6 text-[15px] font-semibold text-brand-contrast hover:opacity-90">{{ __('registration.add_to_calendar') }}</a>
                <a href="{{ URL::signedRoute('public.receipt', ['organizer' => $event->organizer->slug, 'event' => $event->slug, 'registration' => $order->first()]) }}" class="inline-flex h-11 items-center rounded-pill bg-white px-5 text-[15px] font-semibold text-ink shadow-rest hover:shadow-hover">{{ __('receipt.receipt_link') }}</a>
                <a href="{{ route('public.start-list', ['organizer' => $event->organizer->slug, 'event' => $event->slug]) }}#anmalan-{{ $registration->id }}" class="inline-flex h-11 items-center rounded-pill bg-white px-5 text-[15px] font-semibold text-ink shadow-rest hover:shadow-hover">{{ __('registration.see_start_list') }}</a>
            @else
                <a href="{{ $eventUrl }}#steg-3" class="inline-flex h-11 items-center rounded-pill bg-brand px-6 text-[15px] font-semibold text-brand-contrast hover:opacity-90">{{ __('registration.try_again') }}</a>
            @endif
            <a href="{{ $eventUrl }}" class="inline-flex h-11 items-center rounded-pill bg-white px-5 text-[15px] font-semibold text-ink shadow-rest hover:shadow-hover">{{ __('registration.back_to_race') }}</a>
        </div>
    </div>
</x-layouts.public>
