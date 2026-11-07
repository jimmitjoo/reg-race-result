<?php

use App\Models\Organizer;
use App\Support\Format;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

// The organizer's own page (#117): the races coming and earlier years' results. Blocks and theme come with #115–#117.
new #[Layout('components.layouts.public')] class extends Component {
    public Organizer $raceOrganizer;

    public function mount(string $organizer): void
    {
        $this->raceOrganizer = Organizer::where('slug', $organizer)->firstOrFail();
    }

    public function rendering($view): void
    {
        $view->title("{$this->raceOrganizer->name} – Löpning.se")->layoutData(['organizer' => $this->raceOrganizer]);
    }

    public function with(): array
    {
        $today = now($this->raceOrganizer->timezone)->toDateString();
        $events = $this->raceOrganizer->events()->with(['series', 'organizer'])->orderBy('date')->get();

        return [
            'organizer' => $this->raceOrganizer,
            'upcoming' => $events->filter(fn ($event) => $event->date->toDateString() >= $today),
            // Earlier years per arrangemang, the one run most recently first.
            'earlier' => $events->filter(fn ($event) => $event->date->toDateString() < $today)
                ->groupBy(fn ($event) => $event->series?->name ?? $event->name)
                ->map->sortByDesc('date')
                ->sortByDesc(fn ($years) => $years->first()->date),
        ];
    }
}; ?>

<div class="flex flex-col gap-12">
    <header class="flex flex-col gap-3">
        <h1 class="text-[38px] leading-[42px] font-normal tracking-[-0.02em] text-balance sm:text-[52px] sm:leading-[56px]">{{ $organizer->name }}</h1>
    </header>

    <section aria-labelledby="kommande" class="flex flex-col gap-4">
        <h2 id="kommande" class="text-[21px] font-medium sm:text-[26px]">{{ __('public.upcoming') }}</h2>
        @forelse ($upcoming as $event)
            <a wire:key="kommande-{{ $event->id }}" href="{{ $event->publicUrl() }}" class="group flex items-center justify-between gap-4 rounded-card bg-white p-5 shadow-rest hover:shadow-hover sm:p-7">
                <span class="flex flex-col gap-1">
                    <span class="text-[21px] font-medium group-hover:underline">{{ $event->name }}</span>
                    <span class="text-[15px] text-ink-soft">{{ Format::date($event->date) }}@if ($event->city) · {{ $event->city }}@endif</span>
                </span>
                <flux:icon.arrow-right class="size-5 shrink-0 text-ink-soft" />
            </a>
        @empty
            <p class="rounded-card bg-white p-7 text-[17px] text-ink-soft shadow-rest">{{ __('public.no_upcoming') }}</p>
        @endforelse
    </section>

    @if ($earlier->isNotEmpty())
        <section aria-labelledby="tidigare" class="flex flex-col gap-4">
            <h2 id="tidigare" class="text-[21px] font-medium sm:text-[26px]">{{ __('public.earlier') }}</h2>
            <div class="rounded-card bg-white p-5 shadow-rest sm:p-7">
                <ul class="flex flex-col">
                    @foreach ($earlier as $name => $years)
                        <li wire:key="tidigare-{{ $loop->index }}" class="grid gap-2 border-contour py-4 not-first:border-t first:pt-0 last:pb-0 sm:grid-cols-[14rem_1fr] sm:gap-6">
                            <h3 class="text-[17px] font-medium">
                                @if ($series = $years->first()->series)
                                    <a href="{{ route('public.event', ['organizer' => $organizer->slug, 'event' => $series->slug]) }}" class="underline decoration-contour hover:decoration-ink">{{ $name }}</a>
                                @else
                                    {{ $name }}
                                @endif
                            </h3>
                            <ul class="flex flex-wrap gap-2">
                                @foreach ($years as $event)
                                                                        {{-- Results once published, otherwise who ran. --}}
                                    @php($page = $event->results_public_at ? ['public.results', 'live.title'] : ['public.start-list', 'onsite.start_list'])
                                    <li><a href="{{ route($page[0], ['organizer' => $organizer->slug, 'event' => $event->slug]) }}" class="inline-flex h-9 items-center rounded-full border-[1.5px] border-contour px-4 text-[15px] tabular-nums hover:border-ink" aria-label="{{ __($page[1]) }} {{ $event->name }}">{{ $event->date->year }}</a></li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
</div>
