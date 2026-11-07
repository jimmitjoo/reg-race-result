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

    #[Url]
    public string $sort = '';

    #[Url]
    public string $dir = 'asc';

    /** The columns a header sorts on, empty values last. */
    private const SORTS = [
        'bib' => ['registrations.bib'],
        'name' => ['registrations.last_name', 'registrations.first_name'],
        'class' => ['race_classes.position', 'race_classes.name'],
        'status' => ['registrations.paid_at'],
        'created' => ['registrations.created_at'],
    ];

    public function sortBy(string $column): void
    {
        $this->dir = $this->sort === $column && $this->dir === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
        $this->resetPage();
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $status = in_array($this->status, RegistrationsCsv::STATUSES, true) ? $this->status : 'paid';
        $filters = ['status' => $status, 'race' => $this->race ?: null, 'search' => trim($this->search) ?: null];
        $counts = $this->event->allRegistrations()->toBase()
            ->selectRaw('registrations.race_class_id, count(*) as total, sum(case when registrations.paid_at is not null and registrations.refunded_at is null then 1 else 0 end) as paid')
            ->groupBy('registrations.race_class_id')->get()->keyBy('race_class_id');

        $ageGroups = RegistrationsCsv::hasAgeGroups($this->event);

        $registrations = RegistrationsCsv::query($this->event, $status, (int) $this->race ?: null, trim($this->search));
        if ($columns = self::SORTS[$this->sort] ?? null) {
            $registrations->reorder();
            foreach ($columns as $column) {
                $registrations->orderByRaw("{$column} is null")->orderBy($column, $this->dir === 'desc' ? 'desc' : 'asc');
            }
            $registrations->orderBy('registrations.id');
        }

        return [
            'registrations' => $registrations->paginate(100),
            'ageGroups' => $ageGroups,
            // How many paid runners in each age group, for the prizes.
            'ageGroupCounts' => $ageGroups ? RegistrationsCsv::query($this->event)->get()
                ->map(fn ($r) => $r->raceClass->ageGroupOf($r))->filter()->countBy()->sortKeys() : collect(),
            'races' => $this->event->races()->with(['raceClasses' => fn ($q) => $q->orderBy('start_at')->orderBy('name')])->ordered()->get(),
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

    {{-- One card per race: what is paid, class by class. A card is also the race filter. --}}
    <section aria-labelledby="counts" class="flex flex-col gap-3">
        <h2 id="counts" class="sr-only">{{ __('registrations.counts') }}</h2>
        <div class="grid gap-3 md:grid-cols-2">
            @foreach ($races as $r)
                @php($paid = $r->raceClasses->sum(fn ($c) => $counts[$c->id]->paid ?? 0))
                @php($unpaid = $r->raceClasses->sum(fn ($c) => $counts[$c->id]->total ?? 0) - $paid)
                <button type="button" wire:key="count-race-{{ $r->id }}" wire:click="$set('race', '{{ $race == $r->id ? '' : $r->id }}')" aria-pressed="{{ $race == $r->id ? 'true' : 'false' }}"
                    class="rounded-panel flex flex-col gap-3 border p-4 text-left transition hover:border-zinc-400 aria-pressed:border-zinc-800 aria-pressed:ring-1 aria-pressed:ring-zinc-800 dark:hover:border-zinc-500 dark:aria-pressed:border-zinc-200 dark:aria-pressed:ring-zinc-200 border-zinc-200 dark:border-zinc-700">
                    <span class="flex flex-wrap items-baseline justify-between gap-x-4">
                        <span class="font-semibold">{{ $r->name }}</span>
                        <span class="text-sm">
                            <span class="font-semibold tabular-nums">{{ trans_choice('registrations.paid_count', $paid) }}</span>
                            @if ($unpaid)<span class="text-zinc-500">· {{ trans_choice('registrations.unpaid_count', $unpaid) }}</span>@endif
                        </span>
                    </span>
                    <span class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-0.5 text-sm sm:grid-cols-[1fr_auto_1fr_auto]">
                        @foreach (\App\Support\Format::classOrder($r->raceClasses) as $class)
                            @php($n = (int) ($counts[$class->id]->paid ?? 0))
                            <span @class(['text-zinc-500' => ! $n])>{{ $class->name }}</span>
                            <span @class(['text-right tabular-nums', 'text-zinc-500' => ! $n])>{{ $n }}</span>
                        @endforeach
                    </span>
                </button>
            @endforeach
        </div>

        @if ($ageGroupCounts->isNotEmpty())
            <flux:text class="text-sm">
                <span class="font-medium">{{ __('registrations.per_age_group') }}:</span>
                {{ $ageGroupCounts->map(fn ($n, $group) => "{$group}: {$n}")->implode(' · ') }}
            </flux:text>
        @endif
    </section>

    <section aria-labelledby="list" class="flex flex-col gap-3">
        <h2 id="list" class="sr-only">{{ __('registrations.title') }}</h2>
        <div class="flex flex-wrap items-center gap-3">
            <div class="min-w-64 flex-1">
                <flux:input wire:model.live.debounce.250ms="search" icon="magnifying-glass" :placeholder="__('registrations.search')" :aria-label="__('registrations.search')" />
            </div>
            <div class="w-56">
                <flux:select wire:model.live="status" :aria-label="__('registrations.status')">
                    <option value="paid">{{ __('registrations.filter_paid') }}</option>
                    <option value="unpaid">{{ __('registrations.filter_unpaid') }}</option>
                    <option value="all">{{ __('registrations.filter_all') }}</option>
                </flux:select>
            </div>
            <p class="text-sm text-zinc-500" aria-live="polite">
                {{ trans_choice('registrations.shown', $registrations->total()) }}@if ($race && ($picked = $races->firstWhere('id', (int) $race))) · {{ $picked->name }}
                    <button type="button" wire:click="$set('race', '')" class="underline underline-offset-2 hover:text-zinc-800 dark:hover:text-zinc-200">{{ __('registrations.all_races') }}</button>
                @endif
            </p>
        </div>

        <table class="w-full text-left text-sm">
            <thead class="text-zinc-500">
                <tr class="border-b border-zinc-200 dark:border-zinc-700">
                    <x-sort-header column="bib" :$sort :$dir class="py-2 pe-3 font-medium">{{ __('results.bib') }}</x-sort-header>
                    <x-sort-header column="name" :$sort :$dir class="py-2 pe-3 font-medium">{{ __('results.name') }}</x-sort-header>
                    <x-sort-header column="class" :$sort :$dir class="py-2 pe-3 font-medium max-sm:hidden">{{ __('registration.class') }}</x-sort-header>
                    <x-sort-header column="status" :$sort :$dir class="py-2 pe-3 font-medium">{{ __('registrations.status') }}</x-sort-header>
                    <x-sort-header column="created" :$sort :$dir class="py-2 font-medium max-md:hidden">{{ __('registrations.created_at') }}</x-sort-header>
                </tr>
            </thead>
            @foreach ($registrations as $registration)
                @php($name = $registration->first_name.' '.$registration->last_name)
                <tbody wire:key="registration-{{ $registration->id }}" x-data="{ open: false }" class="border-b border-zinc-100 dark:border-zinc-800">
                    <tr class="cursor-pointer align-top hover:bg-zinc-50 dark:hover:bg-zinc-800/50" x-on:click="open = ! open">
                        <td class="py-2 pe-3 font-mono tabular-nums">{{ $registration->bib }}</td>
                        <td class="py-2 pe-3">
                            <button type="button" class="text-left font-medium" x-on:click.stop="open = ! open" x-bind:aria-expanded="open" aria-controls="details-{{ $registration->id }}">
                                {{ $registration->team_name ?: $name }}
                            </button>
                            @if ($registration->hidden_at)<flux:badge size="sm" color="zinc">{{ __('registrations.hidden') }}</flux:badge>@endif
                            <span class="block text-zinc-500">
                                @if ($registration->team_name){{ $name }} · @endif<span class="sm:hidden">{{ $registration->raceClass->name }}{{ $registration->club ? ' · ' : '' }}</span>{{ $registration->club }}
                            </span>
                        </td>
                        <td class="py-2 pe-3 max-sm:hidden">{{ $registration->raceClass->name }}</td>
                        <td class="py-2 pe-3">
                            @if ($registration->refunded_at)
                                <flux:badge size="sm" color="zinc">{{ __('registrations.refunded') }}</flux:badge>
                            @elseif ($registration->paid_at)
                                <span class="tabular-nums">{{ $registration->price === null ? __('registrations.paid') : Money::format($registration->price, $currency) }}</span>
                            @elseif ($registration->payment_method === 'invoice')
                                <flux:badge size="sm" color="sky">{{ __('registrations.invoiced') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber">{{ __('registrations.unpaid') }}</flux:badge>
                            @endif
                        </td>
                        <td class="py-2 tabular-nums text-zinc-500 max-md:hidden">{{ \App\Support\Format::dateTime($registration->created_at->setTimezone($event->timezone)) }}</td>
                    </tr>
                    <tr id="details-{{ $registration->id }}" hidden x-bind:hidden="! open">
                        <td colspan="5" class="pb-4">
                            <div class="rounded-panel flex flex-wrap items-start justify-between gap-4 bg-zinc-50 p-4 dark:bg-zinc-800/50">
                                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 sm:grid-cols-[auto_1fr_auto_1fr]">
                                    <dt class="text-zinc-500">{{ __('registration.email') }}</dt><dd class="break-all">{{ $registration->email }}</dd>
                                    <dt class="text-zinc-500">{{ __('registration.phone') }}</dt><dd>{{ $registration->phone }}</dd>
                                    <dt class="text-zinc-500">{{ __('registration.birth_date') }}</dt><dd class="tabular-nums">{{ $registration->birth_date?->toDateString() }}</dd>
                                    <dt class="text-zinc-500">{{ __('registration.gender') }}</dt><dd>{{ $registration->gender }}</dd>
                                    @if ($ageGroups)
                                        <dt class="text-zinc-500">{{ __('results.age_group') }}</dt><dd>{{ $registration->raceClass->ageGroupOf($registration) }}</dd>
                                    @endif
                                    <dt class="text-zinc-500">{{ __('registrations.price') }}</dt>
                                    <dd>
                                        {{ $registration->price === null ? '' : Money::format($registration->price, $currency) }}
                                        @if ($registration->campaign_code)<flux:badge size="sm">{{ $registration->campaign_code }}</flux:badge>@endif
                                    </dd>
                                    <dt class="text-zinc-500">{{ __('registrations.payment_method') }}</dt><dd>{{ $registration->payment_method ? __("registrations.method.{$registration->payment_method}") : '' }}</dd>
                                    <dt class="text-zinc-500">{{ __('registrations.paid_at') }}</dt><dd class="tabular-nums">{{ $registration->paid_at ? \App\Support\Format::dateTime($registration->paid_at->setTimezone($event->timezone)) : '' }}</dd>
                                    <dt class="text-zinc-500 md:hidden">{{ __('registrations.created_at') }}</dt><dd class="tabular-nums md:hidden">{{ \App\Support\Format::dateTime($registration->created_at->setTimezone($event->timezone)) }}</dd>
                                </dl>
                                <flux:button size="sm" wire:click="toggleHidden({{ $registration->id }})">
                                    {{ $registration->hidden_at ? __('registrations.show_name') : __('registrations.hide_name') }}
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            @endforeach
        </table>

        {{ $registrations->links('livewire::simple-tailwind') }}
    </section>
</div>
