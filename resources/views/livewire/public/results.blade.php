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

<div class="flex flex-col gap-6" @if ($event->results_public_at) wire:poll.15s @endif>
    <div>
        <flux:heading size="xl">{{ __('live.title') }}</flux:heading>
        <flux:subheading>{{ $event->name }}</flux:subheading>
    </div>

    @if (! $event->results_public_at)
        <flux:callout icon="clock" :heading="__('live.not_public')" />
    @else
        <div class="grid gap-3 sm:grid-cols-2">
            <flux:input wire:model.live.debounce.250ms="search" icon="magnifying-glass" :placeholder="__('live.search')" />
            <flux:select wire:model.live="classId">
                <option value="">{{ __('live.all_classes') }}</option>
                @foreach ($allClasses as $id => $results)
                    <option value="{{ $id }}">{{ $results->raceClass->name }}</option>
                @endforeach
            </flux:select>
        </div>
        <flux:text class="text-xs">{{ __('live.updated') }}</flux:text>

        @forelse ($classes as $id => ['results' => $results, 'rows' => $rows])
            <section wire:key="live-{{ $id }}" class="flex flex-col gap-1">
                <flux:heading size="lg">{{ $results->raceClass->name }}</flux:heading>
                <table class="w-full text-sm">
                    @foreach ($rows as $row)
                        <tr class="border-t border-zinc-100 dark:border-zinc-800">
                            <td class="w-8 py-1.5 pe-2 text-right">{{ $row->placing }}</td>
                            <td class="py-1.5 pe-2">
                                <div class="font-medium">{{ $row->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $row->club }}</div>
                            </td>
                            <td class="py-1.5 pe-2 text-right font-mono">{{ $row->time }}</td>
                            <td class="w-12 py-1.5 text-right font-mono text-zinc-500">{{ $row->bib }}</td>
                        </tr>
                    @endforeach
                </table>
            </section>
        @empty
            <flux:text>{{ __('live.nobody_found') }}</flux:text>
        @endforelse
    @endif
</div>
