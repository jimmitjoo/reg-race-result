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

    public function mount(): void
    {
        if (! auth()->user()->organizer_id) {
            $this->redirectRoute('club.create', navigate: true);
        }
    }

    public function with(): array
    {
        // No organizer yet: mount() sends the user to the club step.
        return ['series' => $this->organizer()?->series()->with(['events' => fn ($q) => $q->orderByDesc('date')])->get()->sortByDesc(fn ($s) => $s->events->first()?->date) ?? collect()];
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
        [$this->renamingId, $this->seriesName] = [$series->id, $series->name];
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

<div class="flex max-w-3xl flex-col gap-8">
    <flux:heading size="xl">{{ __('events.title') }}</flux:heading>

    <div class="flex flex-col gap-6">
        @forelse ($series as $s)
            <section wire:key="series-{{ $s->id }}" class="flex flex-col gap-2">
                @if ($renamingId === $s->id)
                    <form wire:submit="saveSeries" class="flex flex-wrap items-end gap-2">
                        <flux:input wire:model="seriesName" size="sm" :label="__('events.series_name')" :description="__('events.series_name_help')" />
                        <flux:button type="submit" size="sm" variant="primary">{{ __('events.save') }}</flux:button>
                        <flux:button size="sm" wire:click="cancelRename">{{ __('events.cancel') }}</flux:button>
                    </form>
                @else
                    <div class="flex items-center gap-2">
                        <flux:heading size="lg">{{ $s->name }}</flux:heading>
                        <flux:button size="xs" variant="ghost" wire:click="editSeries({{ $s->id }})">{{ __('events.rename') }}</flux:button>
                    </div>
                @endif
                @foreach ($s->events as $event)
                    <a href="{{ route('events.show', $event) }}" wire:navigate
                       class="flex items-center justify-between rounded-lg border border-zinc-200 px-4 py-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                        <span class="font-semibold">{{ $event->name }}</span>
                        <span class="text-sm text-zinc-500">{{ $event->date->toDateString() }}</span>
                    </a>
                @endforeach
                <div class="flex flex-wrap items-end gap-2">
                    <flux:input wire:model="editionDate.{{ $s->id }}" type="date" size="sm" :label="__('events.edition_date')" />
                    <flux:button size="sm" icon="document-duplicate" wire:click="newEdition({{ $s->id }})">{{ __('events.new_edition') }}</flux:button>
                </div>
                @if ($s->events->isNotEmpty())
                    <flux:text class="text-sm">{{ __('events.new_edition_help', ['name' => $s->events->first()->name]) }}</flux:text>
                @endif
            </section>
        @empty
            <flux:text>{{ __('events.none') }}</flux:text>
        @endforelse
    </div>

    <form wire:submit="create" class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading>{{ __('events.new') }}</flux:heading>
        <flux:input wire:model="name" :label="__('events.name')" :placeholder="__('events.name_example')" />
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="date" type="date" :label="__('events.date')" />
            <flux:input wire:model="city" :label="__('events.city')" :placeholder="__('events.city_example')" />
        </div>
        <div><flux:button type="submit" variant="primary">{{ __('events.create') }}</flux:button></div>
    </form>
</div>
