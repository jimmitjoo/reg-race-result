<?php

use App\Models\Event;
use App\Results\ResultList;
use Livewire\Volt\Component;

new class extends Component {
    public Event $event;

    public function with(): array
    {
        return [
            'classes' => ResultList::for($this->event),
            'publicUrl' => route('public.results', ['organizer' => $this->event->organizer->slug, 'event' => $this->event->slug]),
        ];
    }

    public function togglePublic(): void
    {
        $this->event->update(['results_public_at' => $this->event->results_public_at ? null : now()]);
    }
}; ?>

<div class="flex max-w-4xl flex-col gap-8" wire:poll.10s>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('results.title') }}</flux:heading>
            <flux:subheading>{{ $event->name }}</flux:subheading>
        </div>
        <div class="flex flex-col items-end gap-1">
            <div class="flex gap-2">
                <flux:button :href="route('events.exports.pdf', $event)" icon="document-arrow-down">{{ __('results.export_pdf') }}</flux:button>
                <flux:button :href="route('events.exports.sfif', $event)" icon="arrow-down-tray">{{ __('results.export_sfif') }}</flux:button>
            </div>
            <flux:text class="text-xs">{{ __('results.export_sfif_help') }}</flux:text>
        </div>
    </div>

    <section class="flex flex-col gap-2 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
        <div>
            <flux:button size="sm" :variant="$event->results_public_at ? 'filled' : 'primary'" icon="signal" wire:click="togglePublic">
                {{ $event->results_public_at ? __('live.hide_public') : __('live.make_public') }}
            </flux:button>
        </div>
        @if ($event->results_public_at)
            <flux:text class="text-sm">{{ __('live.public_since') }} <flux:link :href="$publicUrl" target="_blank">{{ $publicUrl }}</flux:link></flux:text>
            <flux:text class="text-sm">{{ __('live.embed') }}</flux:text>
            <code class="block rounded bg-zinc-100 p-2 text-xs dark:bg-zinc-800">&lt;iframe src="{{ $publicUrl }}" style="width:100%;height:900px;border:0"&gt;&lt;/iframe&gt;</code>
        @endif
    </section>

    @foreach ($classes as $results)
        <section wire:key="results-{{ $results->raceClass->id }}" class="flex flex-col gap-2">
            <div class="flex items-baseline justify-between">
                <flux:heading size="lg">{{ $results->raceClass->name }}</flux:heading>
                <span class="font-mono text-sm text-zinc-500">{{ $results->raceClass->start_at->setTimezone($event->timezone)->format('H:i:s') }}</span>
            </div>

            @php($race = $results->timed ? $results->raceClass->race : null)
            @if ($results->rows === [])
                <flux:text>{{ __('results.no_results') }}</flux:text>
            @else
                <table class="w-full text-left text-sm">
                    <thead class="text-zinc-500">
                        <tr>
                            <th class="py-1 pe-2">{{ __('results.placing') }}</th>
                            <th class="py-1 pe-2">{{ __('results.name') }}</th>
                            <th class="py-1 pe-2">{{ __('results.born') }}</th>
                            <th class="py-1 pe-2">{{ __('results.club') }}</th>
                            <th class="py-1 pe-2 text-right">{{ $results->timed ? __('results.time') : __('results.untimed') }}</th>
                            <th class="py-1 pe-2 text-right">{{ __('results.bib') }}</th>
                            @if ($race?->age_groups)<th class="py-1 pe-2">{{ __('results.age_group') }}</th>@endif
                            @if ($race?->championship)<th class="py-1 pe-2">{{ __('results.championship_placing', ['name' => $race->championship]) }}</th>@endif
                            @if ($race?->championship_veterans)<th class="py-1">{{ __('results.championship_placing', ['name' => 'V'.$race->championship]) }}</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($results->rows as $row)
                            <tr class="border-t border-zinc-100 dark:border-zinc-800">
                                <td class="py-1 pe-2">{{ $row->placing }}</td>
                                <td class="py-1 pe-2">{{ $row->name }}</td>
                                <td class="py-1 pe-2">{{ $row->birthYear }}</td>
                                <td class="py-1 pe-2">{{ $row->club }}</td>
                                <td class="py-1 pe-2 text-right font-mono">{{ $row->time }}</td>
                                <td class="py-1 pe-2 text-right font-mono">{{ $row->bib }}</td>
                                @if ($race?->age_groups)<td class="py-1 pe-2">{{ $row->ageGroup ? $row->ageGroup.' '.$row->ageGroupPlacing : '' }}</td>@endif
                                @if ($race?->championship)<td class="py-1 pe-2">{{ $row->championshipPlacing }}</td>@endif
                                @if ($race?->championship_veterans)<td class="py-1">{{ $row->veteranGroup ? $row->veteranPlacing.' '.$row->veteranGroup : '' }}</td>@endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endforeach
</div>
