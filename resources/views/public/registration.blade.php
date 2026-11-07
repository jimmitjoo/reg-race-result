@use('App\Support\Format')
@use('App\Support\Money')
@php($class = $registration->raceClass)
@php($eventUrl = route('public.event', ['organizer' => $event->organizer->slug, 'event' => $event->series?->slug ?? $event->slug]))
<x-layouts.public :organizer="$event->organizer" :title="__('public.title', ['page' => __('registration.confirmation_heading'), 'event' => $event->name, 'organizer' => $event->organizer->name])">
    <div class="mx-auto flex max-w-[720px] flex-col gap-8">
        <header class="flex flex-col gap-3">
            <p class="text-[15px] text-ink-soft">{{ $event->organizer->name }}</p>
            @if ($registration->paid_at)
                <h1 class="text-[38px] leading-[42px] font-normal tracking-[-0.02em] text-balance sm:text-[52px] sm:leading-[56px]">{{ __('registration.confirmation_heading') }}</h1>
                <p class="text-[17px] text-ink-soft sm:text-[19px]">{{ __('registration.confirmation_text', ['name' => $registration->displayName(), 'class' => $class->name, 'event' => $event->name]) }}</p>
            @else
                <h1 class="text-[38px] leading-[42px] font-normal tracking-[-0.02em] text-balance sm:text-[52px] sm:leading-[56px]">{{ __('registration.unpaid_heading') }}</h1>
                <p class="text-[17px] text-ink-soft sm:text-[19px]">{{ __('registration.unpaid_text') }}</p>
            @endif
        </header>

        <section aria-labelledby="anmalan" class="rounded-card bg-white p-5 shadow-rest sm:p-7">
            <h2 id="anmalan" class="sr-only">{{ __('registration.your_registration') }}</h2>
            @php($pair = 'grid gap-0.5 sm:grid-cols-[8rem_1fr] sm:gap-6')
            <dl class="flex flex-col gap-4">
                <div class="{{ $pair }}">
                    <dt class="text-[15px] text-ink-soft">{{ $registration->team_name ? __('registration.team_name') : __('registration.runner') }}</dt>
                    <dd class="text-[17px] font-medium">{{ $registration->displayName() }}</dd>
                </div>
                <div class="{{ $pair }}">
                    <dt class="text-[15px] text-ink-soft">{{ __('registration.class') }}</dt>
                    <dd class="text-[17px]">{{ $class->race?->name }} – {{ $class->name }}</dd>
                </div>
                <div class="{{ $pair }}">
                    <dt class="text-[15px] text-ink-soft">{{ __('receipt.start') }}</dt>
                    <dd class="text-[17px]">{{ Format::date($event->date) }}, {{ Format::time($class->start_at->setTimezone($event->timezone)) }}@if ($event->city) · {{ $event->city }}@endif</dd>
                </div>
                @if ($registration->price !== null)
                    <div class="{{ $pair }}">
                        <dt class="text-[15px] text-ink-soft">{{ __('registrations.price') }}</dt>
                        <dd class="text-[17px] tabular-nums">{{ Money::format($registration->price, $event->organizer->currency) }}@if ($registration->paid_at && $registration->payment_method) · {{ __("registrations.method.{$registration->payment_method}") }}@endif</dd>
                    </div>
                @endif
            </dl>
            @if ($registration->paid_at && $registration->email)
                <p class="mt-6 border-t border-contour pt-4 text-[15px] text-ink-soft">{{ __('registration.receipt_sent', ['email' => $registration->email]) }}</p>
            @endif
        </section>

        <div class="flex flex-wrap gap-2">
            @if ($registration->paid_at)
                <a href="{{ URL::signedRoute('public.registration.calendar', ['organizer' => $event->organizer->slug, 'event' => $event->slug, 'registration' => $registration]) }}" class="inline-flex h-11 items-center rounded-full bg-brand px-6 text-[15px] font-semibold text-brand-contrast hover:opacity-90">{{ __('registration.add_to_calendar') }}</a>
                <a href="{{ route('public.start-list', ['organizer' => $event->organizer->slug, 'event' => $event->slug]) }}#anmalan-{{ $registration->id }}" class="inline-flex h-11 items-center rounded-full bg-white px-5 text-[15px] font-semibold text-ink shadow-rest hover:shadow-hover">{{ __('registration.see_start_list') }}</a>
            @else
                <a href="{{ $eventUrl }}#steg-3" class="inline-flex h-11 items-center rounded-full bg-brand px-6 text-[15px] font-semibold text-brand-contrast hover:opacity-90">{{ __('registration.try_again') }}</a>
            @endif
            <a href="{{ $eventUrl }}" class="inline-flex h-11 items-center rounded-full bg-white px-5 text-[15px] font-semibold text-ink shadow-rest hover:shadow-hover">{{ __('registration.back_to_race') }}</a>
        </div>
    </div>
</x-layouts.public>
