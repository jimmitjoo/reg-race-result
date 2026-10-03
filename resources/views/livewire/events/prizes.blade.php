<?php

use App\Models\Event;
use App\Models\Registration;
use App\Timing\EventResults;
use App\Timing\ResultStatus;
use Livewire\Volt\Component;

new class extends Component {
    public Event $event;

    public string $prizeName = '';

    public function with(): array
    {
        return ['prizes' => $this->event->prizes()->with('winner')->orderBy('id')->get()];
    }

    public function addPrize(): void
    {
        $this->validate(['prizeName' => ['required', 'string', 'max:255']]);
        $this->event->prizes()->create(['name' => $this->prizeName]);
        $this->reset('prizeName');
    }

    public function removePrize(int $id): void
    {
        $this->event->prizes()->findOrFail($id)->delete();
    }

    public function draw(int $id): void
    {
        $prize = $this->event->prizes()->findOrFail($id);
        $candidates = $this->candidates()->where('id', '!=', $prize->registration_id);

        if ($candidates->isEmpty()) {
            $this->addError('draw', __('prizes.nobody'));

            return;
        }

        $prize->update(['registration_id' => $candidates->random()->id]);
    }

    /** Everyone who finished, or took part in an untimed class, and has not won yet. */
    private function candidates()
    {
        $results = EventResults::for($this->event)->results;
        $winners = $this->event->prizes()->whereNotNull('registration_id')->pluck('registration_id')->all();

        return $this->event->registrations()->with('raceClass')->get()->filter(fn (Registration $r) => ! in_array($r->id, $winners, true)
            && ! in_array($r->status, ['dns', 'dnf', 'dq'], true)
            && (! $r->raceClass->timed || ($results[(string) $r->bib] ?? null)?->status === ResultStatus::Finished));
    }
}; ?>

<div class="flex max-w-3xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('prizes.title') }}</flux:heading>
        <flux:subheading>{{ $event->name }}</flux:subheading>
    </div>

    <flux:text>{{ __('prizes.help') }}</flux:text>
    <flux:error name="draw" />

    <div class="flex flex-col gap-2">
        @foreach ($prizes as $prize)
            <div wire:key="prize-{{ $prize->id }}" class="flex flex-wrap items-center gap-4 rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <span class="flex-1 font-semibold">{{ $prize->name }}</span>
                @if ($prize->winner)
                    <span class="font-mono">{{ $prize->winner->bib }}</span>
                    <span>{{ $prize->winner->first_name }} {{ $prize->winner->last_name }}@if ($prize->winner->club), {{ $prize->winner->club }}@endif</span>
                    <flux:button size="sm" wire:click="draw({{ $prize->id }})">{{ __('prizes.redraw') }}</flux:button>
                @else
                    <flux:button size="sm" variant="primary" wire:click="draw({{ $prize->id }})">{{ __('prizes.draw') }}</flux:button>
                @endif
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removePrize({{ $prize->id }})" :aria-label="__('prizes.remove')" />
            </div>
        @endforeach
    </div>

    <form wire:submit="addPrize" class="flex flex-wrap items-end gap-2">
        <flux:input wire:model="prizeName" :label="__('prizes.name')" :placeholder="__('prizes.name_example')" class="min-w-80" />
        <flux:button type="submit">{{ __('prizes.add') }}</flux:button>
    </form>
</div>
