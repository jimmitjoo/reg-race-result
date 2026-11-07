<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Results\ResultList;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.public')] class extends Component {
    public Event $raceEvent;

    public string $search = '';
    public string $classId = '';

    public function mount(string $organizer, string $event): void
    {
        $this->raceEvent = Organizer::where('slug', $organizer)->firstOrFail()
            ->events()->where('slug', $event)->firstOrFail();
    }

    public function rendering($view): void
    {
        $view->title(__('public.title', ['page' => __('live.title'), 'event' => $this->raceEvent->name, 'organizer' => $this->raceEvent->organizer->name]))
            ->layoutData(['organizer' => $this->raceEvent->organizer]);
    }

    public function with(): array
    {
        $search = mb_strtolower(trim($this->search));
        $classes = $this->raceEvent->results_public_at ? ResultList::for($this->raceEvent) : [];

        return [
            'event' => $this->raceEvent,
            'allClasses' => $classes,
            'classes' => collect($classes)
                ->filter(fn ($results, $id) => $this->classId === '' || (string) $id === $this->classId)
                ->map(fn ($results) => [
                    'results' => $results,
                    'rows' => array_filter($results->rows, fn ($row) => $search === ''
                        || (string) $row->bib === $search
                        || str_contains(mb_strtolower($row->name), $search)),
                ])
                ->filter(fn ($entry) => $entry['rows'] !== []),
        ];
    }
}; ?>

<div class="flex flex-col gap-8" @if ($event->results_public_at) wire:poll.15s @endif>
    <header class="flex flex-col gap-3">
        <p class="text-[15px] text-ink-soft"><a href="{{ route('public.event', ['organizer' => $event->organizer->slug, 'event' => $event->slug]) }}" class="underline decoration-contour hover:text-ink">{{ $event->name }}</a></p>
        <h1 class="text-[38px] leading-[42px] font-normal tracking-[-0.02em] sm:text-[52px] sm:leading-[56px]">{{ __('live.title') }}</h1>
        <p class="text-[17px] text-ink-soft">{{ \App\Support\Format::date($event->date) }}@if ($event->city) · {{ $event->city }}@endif</p>
    </header>

    @if (! $event->results_public_at)
        <p class="rounded-card bg-peach-light px-6 py-5 text-[17px] text-peach-deep">{{ __('live.not_public') }}</p>
    @else
        <div class="flex flex-col gap-2">
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model.live.debounce.250ms="search" icon="magnifying-glass" :label="__('live.search')" :placeholder="__('live.search')" label:class="sr-only" />
                <flux:select wire:model.live="classId" :label="__('live.all_classes')" label:class="sr-only">
                    <option value="">{{ __('live.all_classes') }}</option>
                    @foreach ($allClasses as $id => $results)
                        <option value="{{ $id }}">{{ $results->raceClass->name }}</option>
                    @endforeach
                </flux:select>
            </div>
            <p class="text-[13px] text-ink-soft">{{ __('live.updated') }}</p>
        </div>

        <div aria-live="polite" class="flex flex-col gap-6">
            @forelse ($classes as $id => ['results' => $results, 'rows' => $rows])
                @php($ageGroups = $results->raceClass->race?->age_groups)
                <section wire:key="live-{{ $id }}" aria-labelledby="resultat-{{ $id }}" class="rounded-card bg-white p-5 shadow-rest sm:p-7">
                    <h2 id="resultat-{{ $id }}" class="text-[21px] font-medium">{{ $results->raceClass->name }}</h2>
                    <table class="mt-4 w-full text-[15px]">
                        <caption class="sr-only">{{ __('live.title') }}, {{ $results->raceClass->name }}</caption>
                        <thead class="text-left text-[13px] text-ink-soft">
                            <tr>
                                <th scope="col" class="w-10 pb-2 text-right font-medium">{{ __('results.placing') }}</th>
                                <th scope="col" class="ps-3 pb-2 font-medium">{{ __('results.name') }}</th>
                                @if ($ageGroups)
                                    <th scope="col" class="pb-2 text-right font-medium">{{ __('results.age_group') }}</th>
                                @endif
                                <th scope="col" class="pb-2 text-right font-medium">{{ __('results.time') }}</th>
                                <th scope="col" class="hidden w-14 pb-2 text-right font-medium sm:table-cell">{{ __('results.bib') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr class="border-t border-contour">
                                    <td class="py-2.5 text-right align-top font-medium tabular-nums">{{ $row->placing }}</td>
                                    <td class="py-2.5 ps-3 pe-3 align-top">
                                        <span class="font-medium">{{ $row->name }}</span>
                                        @if ($row->club)<span class="block text-[14px] text-ink-soft">{{ $row->club }}</span>@endif
                                    </td>
                                    @if ($ageGroups)
                                        <td class="py-2.5 pe-3 text-right align-top text-[14px] text-ink-soft">{{ $row->ageGroup ? $row->ageGroup.' '.$row->ageGroupPlacing : '' }}</td>
                                    @endif
                                    <td class="py-2.5 text-right align-top tabular-nums">{{ $row->time }}</td>
                                    <td class="hidden py-2.5 text-right align-top text-ink-soft tabular-nums sm:table-cell">{{ $row->bib }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @empty
                <p class="rounded-card bg-white p-7 text-[17px] text-ink-soft shadow-rest">{{ __('live.nobody_found') }}</p>
            @endforelse
        </div>
    @endif
</div>
