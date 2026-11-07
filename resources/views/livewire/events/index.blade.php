<?php

use App\Models\Series;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $date = '';
    public string $city = '';

    // Date of the next upplaga, keyed by series id.
    public array $editionDate = [];

    public ?int $renamingId = null;
    public string $seriesName = '';

    // The arrangemang whose next upplaga is being dated, and whether the new-arrangemang form is open.
    public ?int $editionFor = null;
    public bool $creating = false;

    public function mount(): void
    {
        if (! auth()->user()->organizer_id) {
            $this->redirectRoute('club.create', navigate: true);
        }
    }

    public function with(): array
    {
        // No organizer yet: mount() sends the user to the club step.
        $series = $this->organizer()?->series()->with(['events' => fn ($q) => $q->withCount('registrations')->orderByDesc('date')])->get()
            ->sortByDesc(fn ($s) => $s->events->first()?->date) ?? collect();

        return [
            'series' => $series,
            'upcoming' => $series->flatMap->events->filter(fn ($e) => $e->date->gte(today()))->sortBy('date')->values(),
        ];
    }

    public function startEdition(int $seriesId): void
    {
        $latest = $this->organizer()->series()->findOrFail($seriesId)->events()->max('date');
        // A guess to correct: a year after the latest upplaga.
        $this->editionDate[$seriesId] ??= $latest ? Illuminate\Support\Carbon::parse($latest)->addYear()->toDateString() : '';
        [$this->editionFor, $this->renamingId, $this->creating] = [$seriesId, null, false];
        $this->resetValidation();
    }

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'city' => ['nullable', 'string', 'max:100'],
        ]);

        // Typing the name of an arrangemang that exists makes its next upplaga.
        $series = $this->organizer()->series()->firstOrCreate(['name' => Series::nameFrom($this->name)]);
        $event = $series->events()->exists()
            ? $series->newEdition($this->date)
            : $series->addEdition($this->date, ['city' => $this->city ?: null]);

        $this->redirectRoute('events.show', $event, navigate: true);
    }

    public function newEdition(int $seriesId): void
    {
        $this->validate(["editionDate.{$seriesId}" => ['required', 'date']], [], ["editionDate.{$seriesId}" => __('events.date')]);

        $event = $this->organizer()->series()->findOrFail($seriesId)->newEdition($this->editionDate[$seriesId]);

        $this->redirectRoute('events.show', $event, navigate: true);
    }

    public function editSeries(int $id): void
    {
        $series = $this->organizer()->series()->findOrFail($id);
        [$this->renamingId, $this->seriesName, $this->editionFor] = [$series->id, $series->name, null];
    }

    public function cancelRename(): void
    {
        $this->reset('renamingId', 'seriesName');
        $this->resetValidation();
    }

    public function saveSeries(): void
    {
        $series = $this->organizer()->series()->findOrFail($this->renamingId);
        $this->seriesName = Series::nameFrom($this->seriesName);
        // One name per arrangemang: typing a name makes the next upplaga of the one that has it.
        $this->validate(['seriesName' => ['required', 'string', 'max:255', Illuminate\Validation\Rule::unique('series', 'name')->where('organizer_id', $series->organizer_id)->ignore($series->id)]], [], ['seriesName' => __('events.series_name')]);

        $series->rename($this->seriesName);
        $this->cancelRename();
    }

    private function organizer(): ?App\Models\Organizer
    {
        return auth()->user()->organizer;
    }
}; ?>

<div class="flex max-w-4xl flex-col gap-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex max-w-xl flex-col gap-1">
            <flux:heading size="xl">{{ __('events.title') }}</flux:heading>
            <flux:text>{{ __('events.index_help') }}</flux:text>
        </div>
        @unless ($creating || $series->isEmpty())
            <flux:button variant="primary" icon="plus" wire:click="$set('creating', true)">{{ __('events.new') }}</flux:button>
        @endunless
    </div>

    @if ($creating || $series->isEmpty())
        <form wire:submit="create" class="rounded-panel flex flex-col gap-4 border border-zinc-200 p-6 dark:border-zinc-700">
            <div class="flex flex-col gap-1">
                <flux:heading size="lg">{{ __('events.new') }}</flux:heading>
                <flux:text>{{ __('events.new_help') }}</flux:text>
            </div>
            <flux:input wire:model="name" :label="__('events.name')" :placeholder="__('events.name_example')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="date" type="date" :label="__('events.date')" />
                <flux:input wire:model="city" :label="__('events.city')" :placeholder="__('events.city_example')" />
            </div>
            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">{{ __('events.create') }}</flux:button>
                @if ($series->isNotEmpty())<flux:button wire:click="$set('creating', false)">{{ __('events.cancel') }}</flux:button>@endif
            </div>
        </form>
    @endif

    @if ($upcoming->isNotEmpty())
        <section aria-labelledby="upcoming" class="flex flex-col gap-3">
            <flux:heading id="upcoming" size="lg">{{ __('events.upcoming') }}</flux:heading>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($upcoming as $event)
                    @php($days = (int) today()->diffInDays($event->date))
                    <a wire:key="upcoming-{{ $event->id }}" href="{{ route('events.show', $event) }}" wire:navigate
                       class="rounded-panel flex flex-col gap-3 border border-zinc-200 p-4 transition hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                        <span class="flex items-baseline justify-between gap-3">
                            <span class="font-semibold">{{ $event->name }}</span>
                            <span class="shrink-0 text-sm text-zinc-500">{{ $days ? trans_choice('events.days_left', $days) : __('events.today') }}</span>
                        </span>
                        <span class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="text-zinc-500">{{ \App\Support\Format::shortDate($event->date) }}</span>
                            <span class="font-medium tabular-nums">{{ trans_choice('events.runners', $event->registrations_count) }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($series->isNotEmpty())
        <section aria-labelledby="all" class="flex flex-col gap-3">
            <flux:heading id="all" size="lg">{{ __('events.all_series') }}</flux:heading>
            <ul class="flex flex-col divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($series as $s)
                    @php($latest = $s->events->first())
                    <li wire:key="series-{{ $s->id }}" class="flex flex-col gap-3 py-4">
                        @if ($renamingId === $s->id)
                            <form wire:submit="saveSeries" class="flex flex-wrap items-end gap-2">
                                <flux:input wire:model="seriesName" size="sm" :label="__('events.series_name')" :description="__('events.series_name_help')" />
                                <flux:button type="submit" size="sm" variant="primary">{{ __('events.save') }}</flux:button>
                                <flux:button size="sm" wire:click="cancelRename">{{ __('events.cancel') }}</flux:button>
                            </form>
                        @else
                            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                                <div class="flex flex-col">
                                    <flux:heading size="lg">{{ $s->name }}</flux:heading>
                                    @if ($latest)
                                        <span class="text-sm text-zinc-500">
                                            {{ __('events.latest') }}: <a href="{{ route('events.show', $latest) }}" wire:navigate class="underline underline-offset-2 hover:text-zinc-800 dark:hover:text-zinc-200">{{ $latest->name }}</a>, {{ \App\Support\Format::shortDate($latest->date) }}
                                        </span>
                                    @endif
                                </div>
                                <div class="flex gap-2">
                                    <flux:button size="sm" variant="ghost" wire:click="editSeries({{ $s->id }})">{{ __('events.rename') }}</flux:button>
                                    @unless ($editionFor === $s->id)
                                        <flux:button size="sm" icon="document-duplicate" wire:click="startEdition({{ $s->id }})">{{ __('events.new_edition') }}</flux:button>
                                    @endunless
                                </div>
                            </div>
                        @endif

                        @if ($editionFor === $s->id)
                            <form wire:submit="newEdition({{ $s->id }})" class="rounded-panel flex flex-col gap-3 bg-zinc-50 p-4 dark:bg-zinc-800/50">
                                <div class="flex flex-wrap items-end gap-2">
                                    <flux:input wire:model="editionDate.{{ $s->id }}" type="date" size="sm" :label="__('events.edition_date')" />
                                    <flux:button type="submit" size="sm" variant="primary">{{ __('events.create') }}</flux:button>
                                    <flux:button size="sm" wire:click="$set('editionFor', null)">{{ __('events.cancel') }}</flux:button>
                                </div>
                                @if ($latest)<flux:text class="text-sm">{{ __('events.new_edition_help', ['name' => $latest->name]) }}</flux:text>@endif
                            </form>
                        @endif

                        @if ($s->events->count() > 1)
                            <details wire:ignore.self class="group text-sm">
                                <summary class="cursor-pointer text-zinc-500 select-none hover:text-zinc-800 dark:hover:text-zinc-200">{{ trans_choice('events.earlier_editions', $s->events->count() - 1) }}</summary>
                                <ul class="mt-2 grid gap-x-6 gap-y-1 sm:grid-cols-3">
                                    @foreach ($s->events->skip(1) as $event)
                                        <li wire:key="edition-{{ $event->id }}" class="flex justify-between gap-3">
                                            <a href="{{ route('events.show', $event) }}" wire:navigate class="underline decoration-zinc-300 underline-offset-2 hover:decoration-current dark:decoration-zinc-600">{{ $event->date->year }}</a>
                                            <span class="text-zinc-500 tabular-nums">{{ trans_choice('events.runners', $event->registrations_count) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
