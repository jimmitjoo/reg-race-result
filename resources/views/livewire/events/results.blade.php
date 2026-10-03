<?php

use App\Models\Event;
use App\Results\ResultList;
use Livewire\Volt\Component;

new class extends Component {
    public Event $event;

    public function with(): array
    {
        return ['classes' => ResultList::for($this->event)];
    }
}; ?>

<div class="flex max-w-4xl flex-col gap-8" wire:poll.10s>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('results.title') }}</flux:heading>
            <flux:subheading>{{ $event->name }}</flux:subheading>
        </div>
        <div class="flex flex-col items-end gap-1">
            <flux:button :href="route('exports.sfif', $event)" icon="arrow-down-tray">{{ __('results.export_sfif') }}</flux:button>
            <flux:text class="text-xs">{{ __('results.export_sfif_help') }}</flux:text>
        </div>
    </div>

    @foreach ($classes as $results)
        <section wire:key="results-{{ $results->raceClass->id }}" class="flex flex-col gap-2">
            <div class="flex items-baseline justify-between">
                <flux:heading size="lg">{{ $results->raceClass->name }}</flux:heading>
                <span class="font-mono text-sm text-zinc-500">{{ $results->raceClass->start_at->setTimezone($event->timezone)->format('H:i:s') }}</span>
            </div>

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
                            <th class="py-1 text-right">{{ __('results.bib') }}</th>
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
                                <td class="py-1 text-right font-mono">{{ $row->bib }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endforeach
</div>
