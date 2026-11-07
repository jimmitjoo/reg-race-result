<?php

use App\Models\Event;
use App\Models\Organizer;
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
        $view->title(__('public.title', ['page' => __('onsite.start_list'), 'event' => $this->raceEvent->name, 'organizer' => $this->raceEvent->organizer->name]))
            ->layoutData(['organizer' => $this->raceEvent->organizer]);
    }

    public function with(): array
    {
        $search = mb_strtolower(trim($this->search));

        return [
            'event' => $this->raceEvent,
            'allClasses' => $all = \App\Support\Format::classOrder($this->raceEvent->raceClasses()
                ->with(['race', 'event', 'registrations' => fn ($q) => $q->orderBy('bib')->orderBy('last_name')])
                ->get()),
            'classes' => $all
                ->filter(fn ($class) => $this->classId === '' || (string) $class->id === $this->classId)
                ->map(fn ($class) => [
                    'class' => $class,
                    'runners' => $class->registrations->filter(fn ($r) => $search === ''
                        || (string) $r->bib === $search
                        || str_contains(mb_strtolower($r->publicName()), $search)
                        || (! $r->hidden_at && str_contains(mb_strtolower((string) $r->club), $search))),
                ])
                ->filter(fn ($row) => $row['runners']->isNotEmpty()),
        ];
    }
}; ?>

<div class="flex flex-col gap-8">
    <header class="flex flex-col gap-3">
        <p class="text-[15px] text-ink-soft"><a href="{{ route('public.event', ['organizer' => $event->organizer->slug, 'event' => $event->slug]) }}" class="underline decoration-contour hover:text-ink">{{ $event->name }}</a></p>
        <h1 class="text-[38px] leading-[42px] font-normal tracking-[-0.02em] sm:text-[52px] sm:leading-[56px]">{{ __('onsite.start_list') }}</h1>
        <p class="text-[17px] text-ink-soft">{{ \App\Support\Format::date($event->date) }}@if ($event->city) · {{ $event->city }}@endif</p>
    </header>

    <div class="grid gap-3 sm:grid-cols-2">
        <flux:input wire:model.live.debounce.250ms="search" icon="magnifying-glass" :label="__('onsite.search')" :placeholder="__('onsite.search')" label:class="sr-only" />
        <flux:select wire:model.live="classId" :label="__('live.all_classes')" label:class="sr-only">
            <option value="">{{ __('live.all_classes') }}</option>
            @foreach ($allClasses as $class)
                <option value="{{ $class->id }}">{{ $class->name }}</option>
            @endforeach
        </flux:select>
    </div>

    <div aria-live="polite" class="flex flex-col gap-6">
        @forelse ($classes as ['class' => $class, 'runners' => $runners])
            @php($bibs = $runners->contains(fn ($r) => $r->bib !== null))
            <section wire:key="class-{{ $class->id }}" aria-labelledby="klass-{{ $class->id }}" class="rounded-card bg-white p-5 shadow-rest sm:p-7">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4">
                    <h2 id="klass-{{ $class->id }}" class="text-[21px] font-medium">{{ $class->name }}</h2>
                    <p class="text-[15px] text-ink-soft">{{ \App\Support\Format::time($class->start_at->setTimezone($event->timezone)) }} · {{ trans_choice('public.runners', $runners->count(), ['count' => $runners->count()]) }}</p>
                </div>
                <table class="mt-4 w-full table-fixed text-[15px]">
                    <caption class="sr-only">{{ __('onsite.start_list') }}, {{ $class->name }}</caption>
                    <thead class="text-left text-[13px] text-ink-soft">
                        <tr>
                            @if ($bibs)<th scope="col" class="w-16 pb-2 font-medium">{{ __('results.bib') }}</th>@endif
                            <th scope="col" class="pb-2 font-medium sm:w-2/5">{{ $class->team_size ? __('registration.team_name') : __('results.name') }}</th>
                            @unless ($class->team_size)<th scope="col" class="w-16 pb-2 font-medium">{{ __('results.born') }}</th>@endunless
                            <th scope="col" class="hidden pb-2 font-medium sm:table-cell">{{ __('registration.club') }}</th>
                            @if ($class->race?->age_groups)
                                <th scope="col" class="w-20 pb-2 text-right font-medium">{{ __('results.age_group') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($runners as $runner)
                            <tr id="anmalan-{{ $runner->id }}" class="scroll-mt-24 border-t border-contour target:bg-peach-light">
                                @if ($bibs)<td class="py-2.5 pe-3 align-top tabular-nums">{{ $runner->bib ?? '–' }}</td>@endif
                                <td class="py-2.5 pe-3 align-top">
                                    <span class="font-medium">{{ $runner->publicName() }}</span>
                                    @unless ($runner->hidden_at)
                                        <span class="block text-[14px] text-ink-soft sm:hidden">{{ $runner->club }}</span>
                                    @endunless
                                </td>
                                @unless ($class->team_size)
                                    <td class="py-2.5 pe-3 align-top text-ink-soft tabular-nums">{{ $runner->hidden_at ? '' : $runner->birth_date?->format('y') }}</td>
                                @endunless
                                <td class="hidden py-2.5 pe-3 align-top text-ink-soft sm:table-cell">{{ $runner->hidden_at ? '' : $runner->club }}</td>
                                @if ($class->race?->age_groups)
                                    <td class="py-2.5 text-right align-top text-ink-soft">{{ $runner->hidden_at ? '' : $class->ageGroupOf($runner) }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @empty
            <p class="rounded-card bg-white p-7 text-[17px] text-ink-soft shadow-rest">{{ __('onsite.nobody_found') }}</p>
        @endforelse
    </div>
</div>
