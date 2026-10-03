<?php

use App\Models\Event;
use App\Models\RaceClass;
use Carbon\CarbonImmutable;
use Livewire\Volt\Component;

new class extends Component {
    public Event $event;

    public ?int $editingId = null;
    public string $className = '';
    public ?int $distanceMeters = null;
    public string $gender = '';
    public bool $timed = true;
    public string $startTime = '';
    public int $minTimeMinutes = 0;

    public function with(): array
    {
        return ['classes' => $this->event->raceClasses()->orderBy('start_at')->orderBy('name')->get()];
    }

    public function editClass(int $id): void
    {
        $class = $this->event->raceClasses()->findOrFail($id);

        $this->editingId = $class->id;
        $this->className = $class->name;
        $this->distanceMeters = $class->distance_meters;
        $this->gender = $class->gender ?? '';
        $this->timed = $class->timed;
        $this->startTime = $class->start_at->setTimezone($this->event->timezone)->format('H:i:s');
        $this->minTimeMinutes = intdiv($class->min_time_seconds, 60);
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'className', 'distanceMeters', 'gender', 'timed', 'startTime', 'minTimeMinutes');
        $this->resetValidation();
    }

    public function saveClass(): void
    {
        $this->validate([
            'className' => ['required', 'string', 'max:255'],
            'distanceMeters' => ['nullable', 'integer', 'min:1'],
            'gender' => ['nullable', 'in:M,K'],
            'timed' => ['boolean'],
            'startTime' => ['required', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            'minTimeMinutes' => ['integer', 'min:0'],
        ]);

        $attributes = [
            'name' => $this->className,
            'distance_meters' => $this->distanceMeters,
            'gender' => $this->gender ?: null,
            'timed' => $this->timed,
            'start_at' => CarbonImmutable::parse($this->event->date->toDateString().' '.$this->startTime, $this->event->timezone),
            'min_time_seconds' => $this->minTimeMinutes * 60,
        ];

        $this->editingId
            ? $this->event->raceClasses()->findOrFail($this->editingId)->update($attributes)
            : $this->event->raceClasses()->create($attributes);

        $this->cancel();
    }
}; ?>

<div class="flex max-w-4xl flex-col gap-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $event->name }}</flux:heading>
            <flux:subheading>{{ $event->date->toDateString() }} · {{ $event->timezone }}</flux:subheading>
        </div>
        <flux:button :href="route('timing.show', $event)" icon="clock">{{ __('timing.title') }}</flux:button>
    </div>

    <section class="flex flex-col gap-3">
        <flux:heading size="lg">{{ __('events.classes') }}</flux:heading>

        @forelse ($classes as $class)
            <div wire:key="class-{{ $class->id }}" class="flex flex-wrap items-center gap-x-6 gap-y-1 rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <span class="min-w-40 font-semibold">{{ $class->name }}</span>
                <span class="font-mono">{{ $class->start_at->setTimezone($event->timezone)->format('H:i:s') }}</span>
                <span class="text-sm text-zinc-500">{{ $class->timed ? __('events.timed') : __('events.untimed') }}</span>
                @if ($class->timed)
                    <span class="text-sm text-zinc-500">{{ __('events.min_time_minutes') }}: {{ intdiv($class->min_time_seconds, 60) }}</span>
                @endif
                <flux:button size="sm" variant="ghost" class="ms-auto" wire:click="editClass({{ $class->id }})">{{ __('events.edit') }}</flux:button>
            </div>
        @empty
            <flux:text>{{ __('events.no_classes') }}</flux:text>
        @endforelse
    </section>

    <form wire:submit="saveClass" class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading>{{ $editingId ? __('events.edit_class') : __('events.new_class') }}</flux:heading>

        <flux:input wire:model="className" :label="__('events.class_name')" :placeholder="__('events.class_name_example')" />

        <div class="grid gap-4 sm:grid-cols-3">
            <flux:input wire:model="startTime" :label="__('events.start_time')" placeholder="10:00:00" />
            <flux:input wire:model="distanceMeters" type="number" min="1" :label="__('events.distance_meters')" />
            <flux:select wire:model="gender" :label="__('events.gender')">
                <option value="">{{ __('events.gender_any') }}</option>
                <option value="M">{{ __('events.gender_m') }}</option>
                <option value="K">{{ __('events.gender_k') }}</option>
            </flux:select>
        </div>

        <flux:checkbox wire:model.live="timed" :label="__('events.timed')" />

        <div x-show="$wire.timed">
            <flux:input wire:model="minTimeMinutes" type="number" min="0" :label="__('events.min_time_minutes')" :description="__('events.min_time_help')" />
        </div>

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">{{ __('events.save') }}</flux:button>
            @if ($editingId)
                <flux:button wire:click="cancel">{{ __('events.cancel') }}</flux:button>
            @endif
        </div>
    </form>
</div>
