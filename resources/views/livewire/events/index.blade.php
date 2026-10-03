<?php

use App\Models\Event;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $date = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->organizer_id, 403);
    }

    public function with(): array
    {
        return ['events' => $this->organizer()->events()->orderByDesc('date')->get()];
    }

    public function create(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
        ]);

        $event = $this->organizer()->events()->create($data + ['timezone' => $this->organizer()->timezone]);

        $this->redirectRoute('events.show', $event, navigate: true);
    }

    private function organizer(): App\Models\Organizer
    {
        return auth()->user()->organizer;
    }
}; ?>

<div class="flex max-w-3xl flex-col gap-8">
    <flux:heading size="xl">{{ __('events.title') }}</flux:heading>

    <div class="flex flex-col gap-2">
        @forelse ($events as $event)
            <a href="{{ route('events.show', $event) }}" wire:navigate
               class="flex items-center justify-between rounded-lg border border-zinc-200 px-4 py-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                <span class="font-semibold">{{ $event->name }}</span>
                <span class="text-sm text-zinc-500">{{ $event->date->toDateString() }}</span>
            </a>
        @empty
            <flux:text>{{ __('events.none') }}</flux:text>
        @endforelse
    </div>

    <form wire:submit="create" class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading>{{ __('events.new') }}</flux:heading>
        <flux:input wire:model="name" :label="__('events.name')" :placeholder="__('events.name_example')" />
        <flux:input wire:model="date" type="date" :label="__('events.date')" />
        <div><flux:button type="submit" variant="primary">{{ __('events.create') }}</flux:button></div>
    </form>
</div>
