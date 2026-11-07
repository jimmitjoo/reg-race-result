{{-- The arrangemang's earlier years with results, for the runners who look up last year's time. --}}
<section class="flex flex-col gap-3">
    <x-editable :$editor :$block field="heading" as="h2" class="text-[21px] font-medium sm:text-[26px]" :placeholder="__('registration.earlier_results')" :fallback="__('registration.earlier_results')" />
    @if ($history->isEmpty())
        <p class="text-[15px] text-ink-soft">{{ __('page.history_none') }}</p>
    @else
        <ul class="flex flex-wrap gap-2">
            @foreach ($history as $year)
                <li wire:key="earlier-{{ $year->id }}">
                    <a href="{{ route('public.results', ['organizer' => $organizer->slug, 'event' => $year->slug]) }}" class="inline-flex h-11 items-center rounded-pill bg-white px-5 text-[15px] font-medium text-ink shadow-rest hover:shadow-hover">{{ $year->name }}</a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
