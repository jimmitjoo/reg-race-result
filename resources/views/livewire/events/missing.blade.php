<?php

use App\Models\Event;
use App\Models\Registration;
use App\Timing\EventResults;
use App\Timing\ResultStatus;
use Carbon\CarbonImmutable;
use Livewire\Volt\Component;

new class extends Component {
    public Event $event;

    public function with(): array
    {
        $results = EventResults::for($this->event)->results;
        $registrations = $this->event->registrations()->with('raceClass')->orderBy('bib')->get();

        return [
            'missing' => $registrations->filter(fn (Registration $r) => $r->raceClass->timed
                && $r->status !== 'dns'
                && ($results[(string) $r->bib] ?? null)?->status !== ResultStatus::Finished),
            'untimed' => $registrations->reject(fn (Registration $r) => $r->raceClass->timed),
        ];
    }

    public function setStatus(int $id, string $status, ?string $note = null): void
    {
        validator(compact('status'), ['status' => ['required', 'in:registered,dns,dnf,dq']])->validate();

        $this->registration($id)->update(['status' => $status] + ($note !== null ? ['note' => $note] : []));
    }

    public function addTime(int $id, string $time): void
    {
        validator(compact('time'), ['time' => ['required', 'regex:/^([01]?\d|2[0-3]):[0-5]\d:[0-5]\d$/']])->validate();

        $registration = $this->registration($id);
        $localDate = $registration->raceClass->start_at->setTimezone($this->event->timezone)->toDateString();
        $registration->update(['manual_finish_at' => CarbonImmutable::parse("{$localDate} {$time}", $this->event->timezone), 'status' => 'registered']);
    }

    private function registration(int $id): Registration
    {
        return $this->event->registrations()->with('raceClass')->findOrFail($id);
    }
}; ?>

<div class="flex max-w-3xl flex-col gap-8" wire:poll.10s>
    <div>
        <flux:heading size="xl">{{ __('results.missing') }}</flux:heading>
        <flux:subheading>{{ $event->name }}</flux:subheading>
    </div>

    <flux:text>{{ __('results.missing_help') }}</flux:text>

    <div class="flex flex-col gap-2">
        @forelse ($missing as $registration)
            <div wire:key="missing-{{ $registration->id }}" x-data="{ time: '', note: '' }"
                 class="flex flex-col gap-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="flex flex-wrap items-baseline gap-x-4">
                    <span class="font-mono text-lg font-semibold">{{ $registration->bib }}</span>
                    <span class="font-semibold">{{ $registration->first_name }} {{ $registration->last_name }}</span>
                    <span class="text-sm text-zinc-500">{{ $registration->raceClass->name }}</span>
                    @if (in_array($registration->status, ['dnf', 'dq']))
                        <flux:badge color="red">{{ __('results.status_'.$registration->status) }}</flux:badge>
                    @endif
                </div>

                @if (in_array($registration->status, ['dnf', 'dq']))
                    <div class="flex items-center gap-2">
                        @if ($registration->note)<span class="text-sm">{{ $registration->note }}</span>@endif
                        <flux:button size="sm" wire:click="setStatus({{ $registration->id }}, 'registered')">{{ __('results.undo') }}</flux:button>
                    </div>
                @else
                    <div class="flex flex-wrap items-end gap-2">
                        <flux:input x-model="time" size="sm" placeholder="10:41:07" :label="__('results.finish_time')" />
                        <flux:button size="sm" variant="primary" x-on:click="$wire.addTime({{ $registration->id }}, time)">{{ __('results.add_time') }}</flux:button>
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <flux:button size="sm" wire:click="setStatus({{ $registration->id }}, 'dns')">{{ __('results.status_dns') }}</flux:button>
                        <flux:button size="sm" wire:click="setStatus({{ $registration->id }}, 'dnf')">{{ __('results.status_dnf') }}</flux:button>
                        <flux:input x-model="note" size="sm" :placeholder="__('results.note')" class="max-w-64" />
                        <flux:button size="sm" x-on:click="$wire.setStatus({{ $registration->id }}, 'dq', note)">{{ __('results.status_dq') }}</flux:button>
                    </div>
                @endif
            </div>
        @empty
            <flux:callout variant="success" icon="check-circle" :heading="__('results.none_missing')" />
        @endforelse
    </div>

    @if ($untimed->isNotEmpty())
        <section class="flex flex-col gap-2">
            <flux:heading size="lg">{{ __('results.untimed_classes') }}</flux:heading>
            @foreach ($untimed as $registration)
                <div wire:key="untimed-{{ $registration->id }}" class="flex items-center gap-4 rounded-lg border border-zinc-200 px-4 py-2 dark:border-zinc-700">
                    <span class="font-mono">{{ $registration->bib }}</span>
                    <span class="flex-1 {{ $registration->status === 'dns' ? 'text-zinc-400 line-through' : '' }}">{{ $registration->first_name }} {{ $registration->last_name }} · {{ $registration->raceClass->name }}</span>
                    @if ($registration->status === 'dns')
                        <flux:button size="sm" wire:click="setStatus({{ $registration->id }}, 'registered')">{{ __('results.undo') }}</flux:button>
                    @else
                        <flux:button size="sm" wire:click="setStatus({{ $registration->id }}, 'dns')">{{ __('results.strike') }}</flux:button>
                    @endif
                </div>
            @endforeach
        </section>
    @endif
</div>
