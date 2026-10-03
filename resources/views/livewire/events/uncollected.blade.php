<?php

use App\Models\Event;
use Livewire\Volt\Component;

new class extends Component {
    public Event $event;

    /** @var list<int> bibs marked as not collected */
    public array $marked = [];
    public bool $saved = false;

    public function mount(): void
    {
        $this->marked = $this->event->registrations()->where('status', 'dns')->whereNotNull('bib')->orderBy('bib')->pluck('bib')->all();
    }

    public function with(): array
    {
        $registrations = $this->event->registrations()->whereNotNull('bib')->get(['bib', 'status']);

        // One ten per column (560–569 …), a bib in its own row of the column, empty slots for missing numbers.
        $columns = [];
        foreach ($registrations->pluck('bib')->sort() as $bib) {
            $ten = intdiv($bib, 10) * 10;
            $columns[$ten] ??= array_fill(0, 10, null);
            $columns[$ten][$bib - $ten] = $bib;
        }

        return [
            'columns' => $columns,
            'locked' => $registrations->whereIn('status', ['dnf', 'dq'])->pluck('bib')->all(),
        ];
    }

    public function toggle(int $bib): void
    {
        $this->marked = in_array($bib, $this->marked, true)
            ? array_values(array_diff($this->marked, [$bib]))
            : [...$this->marked, $bib];
        $this->saved = false;
    }

    public function save(): void
    {
        $registrations = $this->event->registrations()->whereIn('registrations.status', ['registered', 'dns']);

        (clone $registrations)->whereIn('bib', $this->marked)->update(['status' => 'dns']);
        (clone $registrations)->whereNotIn('bib', $this->marked)->update(['status' => 'registered']);

        $this->saved = true;
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('results.uncollected') }}</flux:heading>
        <flux:subheading>{{ $event->name }}</flux:subheading>
    </div>

    <flux:text class="max-w-2xl">{{ __('results.uncollected_help') }}</flux:text>

    <div class="flex gap-4 overflow-x-auto pb-2">
        @foreach ($columns as $ten => $slots)
            <div wire:key="ten-{{ $ten }}" class="flex shrink-0 flex-col gap-1">
                <div class="text-center text-xs font-semibold text-zinc-500">{{ $ten }}–{{ $ten + 9 }}</div>
                @foreach ($slots as $bib)
                    @if ($bib === null)
                        <div class="h-11 w-16"></div>
                    @elseif (in_array($bib, $locked, true))
                        <div class="flex h-11 w-16 items-center justify-center rounded-md bg-zinc-100 font-mono text-zinc-400 dark:bg-zinc-800">{{ $bib }}</div>
                    @else
                        <button type="button" wire:click="toggle({{ $bib }})" wire:key="bib-{{ $bib }}"
                                class="h-11 w-16 rounded-md border font-mono text-lg {{ in_array($bib, $marked, true) ? 'border-red-600 bg-red-600 text-white' : 'border-zinc-300 bg-white hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900' }}">
                            {{ $bib }}
                        </button>
                    @endif
                @endforeach
            </div>
        @endforeach
    </div>

    <div class="flex items-center gap-4">
        <flux:button variant="primary" wire:click="save">{{ __('results.save') }}</flux:button>
        @if ($saved)
            <flux:text>{{ __('results.saved') }}</flux:text>
        @endif
    </div>
</div>
