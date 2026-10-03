<?php

use App\Models\Event;
use Carbon\CarbonImmutable;
use Livewire\Volt\Component;

new class extends Component {
    public Event $event;

    /** @var list<int> */
    public array $selected = [];
    public string $startTime = '';
    public bool $changed = false;

    public function with(): array
    {
        return ['classes' => $this->event->raceClasses()->orderBy('start_at')->orderBy('name')->get()];
    }

    public function changeStartTime(): void
    {
        $this->validate([
            'selected' => ['required', 'array', 'min:1'],
            'startTime' => ['required', 'regex:/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
        ]);

        foreach ($this->event->raceClasses()->whereIn('id', $this->selected)->get() as $class) {
            $localDate = $class->start_at->setTimezone($this->event->timezone)->toDateString();
            $class->update(['start_at' => CarbonImmutable::parse("{$localDate} {$this->startTime}", $this->event->timezone)]);
        }

        $this->reset('selected', 'startTime');
        $this->changed = true;
    }
}; ?>

<div class="flex max-w-2xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('results.start') }}</flux:heading>
        <flux:subheading>{{ $event->name }}</flux:subheading>
    </div>

    <flux:text>{{ __('results.start_help') }}</flux:text>

    <div class="flex flex-col gap-2">
        @foreach ($classes as $class)
            <label wire:key="start-{{ $class->id }}" class="flex items-center gap-4 rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <flux:checkbox wire:model="selected" value="{{ $class->id }}" />
                <span class="flex-1 font-semibold">{{ $class->name }}</span>
                <span class="font-mono text-lg">{{ $class->start_at->setTimezone($event->timezone)->format('H:i:s') }}</span>
            </label>
        @endforeach
    </div>
    <flux:error name="selected" />

    <form wire:submit="changeStartTime" class="flex flex-wrap items-end gap-2">
        <flux:input wire:model="startTime" :label="__('results.start_time')" placeholder="10:03:30" />
        <flux:button type="submit" variant="primary">{{ __('results.change_start_time') }}</flux:button>
    </form>

    @if ($changed)
        <flux:callout variant="success" icon="check-circle" :heading="__('results.changed')" />
    @endif
</div>
