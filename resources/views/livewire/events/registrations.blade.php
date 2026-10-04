<?php

use App\Models\Event;
use Livewire\Volt\Component;

new class extends Component {
    public Event $event;

    public string $search = '';

    public function with(): array
    {
        $search = trim($this->search);

        return [
            'registrations' => $this->event->registrations()->with('raceClass')
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('bib', $search)))
                ->orderBy('bib')->orderBy('last_name')
                ->limit(200)
                ->get(),
        ];
    }

    public function toggleHidden(int $id): void
    {
        $registration = $this->event->registrations()->findOrFail($id);
        $registration->update(['hidden_at' => $registration->hidden_at ? null : now()]);
    }
}; ?>

<div class="flex max-w-4xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('registrations.title') }}</flux:heading>
        <flux:subheading>{{ $event->name }}</flux:subheading>
    </div>

    <flux:input wire:model.live.debounce.250ms="search" icon="magnifying-glass" :placeholder="__('registrations.search')" />

    <table class="w-full text-left text-sm">
        <thead class="text-zinc-500">
            <tr>
                <th class="py-1 pe-2">{{ __('results.bib') }}</th>
                <th class="py-1 pe-2">{{ __('results.name') }}</th>
                <th class="py-1 pe-2">{{ __('registration.class') }}</th>
                <th class="py-1 pe-2">{{ __('registrations.status') }}</th>
                <th class="py-1"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($registrations as $registration)
                <tr wire:key="registration-{{ $registration->id }}" class="border-t border-zinc-100 dark:border-zinc-800">
                    <td class="py-1 pe-2 font-mono">{{ $registration->bib }}</td>
                    <td class="py-1 pe-2">
                        {{ $registration->first_name }} {{ $registration->last_name }}
                        @if ($registration->hidden_at)<flux:badge size="sm" color="zinc">{{ __('registrations.hidden') }}</flux:badge>@endif
                    </td>
                    <td class="py-1 pe-2">{{ $registration->raceClass->name }}</td>
                    <td class="py-1 pe-2">{{ $registration->paid_at ? __('registrations.paid') : __('registrations.unpaid') }}</td>
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
