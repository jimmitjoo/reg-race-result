<?php

use App\Models\Event;
use App\Models\Organizer;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.public')] class extends Component {
    public Event $raceEvent;

    public string $search = '';

    public function mount(string $organizer, string $event): void
    {
        $this->raceEvent = Organizer::where('slug', $organizer)->firstOrFail()
            ->events()->where('slug', $event)->firstOrFail();
    }

    public function with(): array
    {
        $search = mb_strtolower(trim($this->search));

        return [
            'event' => $this->raceEvent,
            'classes' => $this->raceEvent->raceClasses()->orderBy('start_at')->orderBy('name')
                ->with(['registrations' => fn ($q) => $q->whereNotNull('paid_at')->orderBy('bib')->orderBy('last_name')])
                ->get()
                ->map(fn ($class) => [
                    'class' => $class,
                    'runners' => $class->registrations->filter(fn ($r) => $search === ''
                        || (string) $r->bib === $search
                        || (! $r->hidden_at && str_contains(mb_strtolower("{$r->first_name} {$r->last_name}"), $search))),
                ])
                ->filter(fn ($row) => $row['runners']->isNotEmpty()),
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('onsite.start_list') }}</flux:heading>
        <flux:subheading>{{ $event->name }}</flux:subheading>
    </div>

    <flux:input wire:model.live.debounce.250ms="search" icon="magnifying-glass" :placeholder="__('onsite.search')" />

    @forelse ($classes as ['class' => $class, 'runners' => $runners])
        <section wire:key="class-{{ $class->id }}" class="flex flex-col gap-1">
            <flux:heading size="lg">{{ $class->name }}</flux:heading>
            <table class="w-full text-sm">
                @foreach ($runners as $runner)
                    <tr class="border-t border-zinc-100 dark:border-zinc-800">
                        <td class="w-16 py-1 font-mono">{{ $runner->bib }}</td>
                        <td class="py-1">{{ $runner->hidden_at ? __('privacy.anonymous') : $runner->first_name.' '.$runner->last_name }}</td>
                        <td class="py-1 text-zinc-500">{{ $runner->hidden_at ? '' : $runner->club }}</td>
                    </tr>
                @endforeach
            </table>
        </section>
    @empty
        <flux:text>{{ __('onsite.nobody_found') }}</flux:text>
    @endforelse
</div>
