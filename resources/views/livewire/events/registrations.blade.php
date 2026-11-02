<?php

use App\Exports\RegistrationsCsv;
use App\Models\Event;
use App\Support\Money;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public Event $event;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'paid';

    #[Url]
    public string $race = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $status = in_array($this->status, RegistrationsCsv::STATUSES, true) ? $this->status : 'paid';
        $filters = ['status' => $status, 'race' => $this->race ?: null, 'search' => trim($this->search) ?: null];
        $counts = $this->event->allRegistrations()->toBase()
            ->selectRaw('registrations.race_class_id, count(*) as total, count(registrations.paid_at) as paid')
            ->groupBy('registrations.race_class_id')->get()->keyBy('race_class_id');

        return [
            'registrations' => RegistrationsCsv::query($this->event, $status, (int) $this->race ?: null, trim($this->search))->paginate(100),
            'races' => $this->event->races()->with(['raceClasses' => fn ($q) => $q->orderBy('start_at')->orderBy('name')])->orderBy('name')->get(),
            'counts' => $counts,
            'download' => route('events.exports.registrations', [$this->event, ...array_filter($filters)]),
            'currency' => $this->event->organizer->currency,
        ];
    }

    public function toggleHidden(int $id): void
    {
        $registration = $this->event->allRegistrations()->findOrFail($id);
        $registration->update(['hidden_at' => $registration->hidden_at ? null : now()]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('registrations.title') }}</flux:heading>
            <flux:subheading>{{ $event->name }}</flux:subheading>
        </div>
        <flux:button :href="$download" icon="arrow-down-tray">{{ __('registrations.download') }}</flux:button>
    </div>

    <table class="max-w-xl text-left text-sm">
        <thead class="text-zinc-500">
            <tr>
                <th class="py-1 pe-4">{{ __('registrations.counts') }}</th>
                <th class="py-1 pe-4 text-right">{{ __('registrations.registered') }}</th>
                <th class="py-1 text-right">{{ __('registrations.filter_paid') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($races as $r)
                <tr wire:key="count-race-{{ $r->id }}" class="border-t border-zinc-100 font-semibold dark:border-zinc-800">
                    <td class="py-1 pe-4">{{ $r->name }}</td>
                    <td class="py-1 pe-4 text-right">{{ $r->raceClasses->sum(fn ($c) => $counts[$c->id]->total ?? 0) }}</td>
                    <td class="py-1 text-right">{{ $r->raceClasses->sum(fn ($c) => $counts[$c->id]->paid ?? 0) }}</td>
                </tr>
                @foreach ($r->raceClasses as $class)
                    <tr wire:key="count-class-{{ $class->id }}">
                        <td class="py-1 pe-4 ps-4">{{ $class->name }}</td>
                        <td class="py-1 pe-4 text-right">{{ $counts[$class->id]->total ?? 0 }}</td>
                        <td class="py-1 text-right">{{ $counts[$class->id]->paid ?? 0 }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="grid gap-4 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.250ms="search" icon="magnifying-glass" :placeholder="__('registrations.search')" />
        <flux:select wire:model.live="status">
            <option value="paid">{{ __('registrations.filter_paid') }}</option>
            <option value="unpaid">{{ __('registrations.filter_unpaid') }}</option>
            <option value="all">{{ __('registrations.filter_all') }}</option>
        </flux:select>
        <flux:select wire:model.live="race">
            <option value="">{{ __('registrations.all_races') }}</option>
            @foreach ($races as $r)
                <option value="{{ $r->id }}">{{ $r->name }}</option>
            @endforeach
        </flux:select>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead class="text-zinc-500">
                <tr>
                    <th class="py-1 pe-3">{{ __('registration.class') }}</th>
                    <th class="py-1 pe-3">{{ __('results.bib') }}</th>
                    <th class="py-1 pe-3">{{ __('results.name') }}</th>
                    <th class="py-1 pe-3">{{ __('registration.birth_date') }}</th>
                    <th class="py-1 pe-3">{{ __('registration.gender') }}</th>
                    <th class="py-1 pe-3">{{ __('registration.club') }}</th>
                    <th class="py-1 pe-3">{{ __('registration.email') }}</th>
                    <th class="py-1 pe-3">{{ __('registration.phone') }}</th>
                    <th class="py-1 pe-3">{{ __('registrations.price') }}</th>
                    <th class="py-1 pe-3">{{ __('registrations.payment_method') }}</th>
                    <th class="py-1 pe-3">{{ __('registrations.status') }}</th>
                    <th class="py-1 pe-3">{{ __('registrations.created_at') }}</th>
                    <th class="py-1"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($registrations as $registration)
                    <tr wire:key="registration-{{ $registration->id }}" class="border-t border-zinc-100 dark:border-zinc-800">
                        <td class="py-1 pe-3">{{ $registration->raceClass->name }}</td>
                        <td class="py-1 pe-3 font-mono">{{ $registration->bib }}</td>
                        <td class="py-1 pe-3">
                            {{ $registration->first_name }} {{ $registration->last_name }}
                            @if ($registration->hidden_at)<flux:badge size="sm" color="zinc">{{ __('registrations.hidden') }}</flux:badge>@endif
                        </td>
                        <td class="py-1 pe-3">{{ $registration->birth_date?->toDateString() }}</td>
                        <td class="py-1 pe-3">{{ $registration->gender }}</td>
                        <td class="py-1 pe-3">{{ $registration->club }}</td>
                        <td class="py-1 pe-3">{{ $registration->email }}</td>
                        <td class="py-1 pe-3">{{ $registration->phone }}</td>
                        <td class="py-1 pe-3">{{ $registration->price === null ? '' : Money::format($registration->price, $currency) }}</td>
                        <td class="py-1 pe-3">{{ $registration->payment_method ? __("registrations.method.{$registration->payment_method}") : '' }}</td>
                        <td class="py-1 pe-3">
                            @if ($registration->paid_at)
                                {{ $registration->paid_at->setTimezone($event->timezone)->format('Y-m-d H:i') }}
                            @else
                                <flux:badge size="sm" color="amber">{{ __('registrations.unpaid') }}</flux:badge>
                            @endif
                        </td>
                        <td class="py-1 pe-3">{{ $registration->created_at->setTimezone($event->timezone)->format('Y-m-d H:i') }}</td>
                        <td class="py-1 text-right">
                            <flux:button size="xs" wire:click="toggleHidden({{ $registration->id }})">
                                {{ $registration->hidden_at ? __('registrations.show_name') : __('registrations.hide_name') }}
                            </flux:button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $registrations->links() }}
</div>
