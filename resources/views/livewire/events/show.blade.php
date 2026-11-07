<?php

use App\Models\Event;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public Event $event;

    public string $city = '';
    public string $raceDirector = '';
    public string $weather = '';
    public string $contactEmail = '';

    public ?int $editingRaceId = null;
    public string $raceName = '';
    public string $raceType = 'Väg';
    public string $courseMeasurer = '';
    public string $measuredOn = '';
    public bool $ageGroups = false;
    public string $championship = '';
    public array $championshipDistricts = [];
    public bool $championshipVeterans = false;

    // New price step per race, keyed by race id.
    public array $priceUntil = [];
    public array $priceAmount = [];
    public array $onsiteAmount = [];

    public ?int $editingId = null;
    public ?int $raceId = null;
    public string $className = '';
    public ?int $distanceMeters = null;
    public ?int $teamSize = null;
    public string $gender = '';
    public bool $timed = true;
    public string $startTime = '';
    public int $minTimeMinutes = 0;

    // What is open for editing; the page shows the forms only when asked.
    public ?int $pricesOpen = null;
    public bool $addingRace = false;
    public ?int $addingClassTo = null;

    public function mount(): void
    {
        $this->city = $this->event->city ?? '';
        $this->raceDirector = $this->event->race_director ?? '';
        $this->weather = $this->event->weather ?? '';
        $this->contactEmail = $this->event->contact_email ?? '';
        $this->raceId = $this->event->races()->ordered()->value('id');
    }

    /** One step up (-1) or down (1) among the event's races. */
    public function moveRace(int $id, int $step): void
    {
        $this->renumber($this->event->races()->ordered()->get(), $id, $step);
        $this->renumberClasses();
    }

    /** One step up (-1) or down (1) among its race's classes. */
    public function moveClass(int $id, int $step): void
    {
        $class = $this->event->raceClasses()->findOrFail($id);
        $this->renumber(\App\Support\Format::classOrder($this->event->raceClasses()->where('race_id', $class->race_id)->get()), $id, $step);
        $this->renumberClasses();
    }

    private function renumber($items, int $id, int $step): void
    {
        $items = $items->values()->all();
        $at = collect($items)->search(fn ($item) => $item->id === $id);
        abort_if($at === false, 404);
        $to = max(0, min(count($items) - 1, $at + $step));
        [$items[$at], $items[$to]] = [$items[$to], $items[$at]];
        foreach ($items as $i => $item) {
            $item->update(['position' => $i + 1]);
        }
    }

    /** Classes numbered through the event race by race, so lists of every class keep the races' order. */
    private function renumberClasses(): void
    {
        $position = 0;
        foreach ($this->event->races()->ordered()->with('raceClasses')->get() as $race) {
            foreach (\App\Support\Format::classOrder($race->raceClasses) as $class) {
                $class->update(['position' => ++$position]);
            }
        }
    }

    public function with(): array
    {
        $races = $this->event->races()->with(['priceSteps', 'raceClasses' => fn ($q) => $q->orderBy('start_at')->orderBy('name')])->ordered()->get();

        // Setting up an event is a walk through these steps; until it is done the page shows only the next one.
        $unpriced = fn ($race) => $race->priceSteps->isEmpty() && $race->onsite_price === null;
        $setup = match (true) {
            $races->isEmpty() => 'race',
            $races->contains(fn ($race) => $race->raceClasses->isEmpty()) => 'class',
            $races->contains($unpriced) => 'price',
            default => null,
        };

        if ($setup === 'class' && ! $this->editingId) {
            $this->raceId = $races->first(fn ($race) => $race->raceClasses->isEmpty())->id;
        }

        // How many run and what has come in, per class.
        $counts = $this->event->registrations()->toBase()
            ->selectRaw('registrations.race_class_id, count(*) as runners, sum(coalesce(registrations.price, 0)) as money')
            ->groupBy('registrations.race_class_id')->get()->keyBy('race_class_id');
        $today = now()->setTimezone($this->event->timezone)->startOfDay();
        $prices = $races->mapWithKeys(fn ($race) => [$race->id => $race->priceAt(now())]);
        $open = $races->filter(fn ($race) => $prices[$race->id] !== null);
        $currency = $this->event->organizer->currency;

        return [
            'counts' => $counts,
            'prices' => $prices,
            'runners' => $counts->sum('runners'),
            'money' => $counts->sum('money'),
            'days' => (int) $today->diffInDays($this->event->date->copy()->startOfDay(), false),
            'openUntil' => $open->map(fn ($race) => $race->closesOn())->max(),
            // What waits for the organizer, each with where to fix it.
            'todo' => collect([
                ...($prices->contains(fn ($price) => $price > 0) && ! $this->event->organizer->canTakePayments()
                    ? [['text' => __('events.todo_stripe'), 'href' => route('payments.index')]] : []),
                ...($prices->contains(fn ($price) => $price > 0) && ! $this->event->organizer->org_number
                    ? [['text' => __('events.todo_receipt'), 'href' => route('payments.index')]] : []),
            ]),
            'races' => $races,
            'setup' => $setup,
            'unpriced' => $unpriced,
            'unassigned' => $this->event->raceClasses()->whereNull('race_id')->orderBy('start_at')->get(),
            'currency' => $currency,
            'districts' => App\Results\Districts::names(),
        ];
    }

    public function saveDetails(): void
    {
        $this->validate([
            'city' => ['nullable', 'string', 'max:100'],
            'raceDirector' => ['nullable', 'string', 'max:100'],
            'weather' => ['nullable', 'string', 'max:100'],
            'contactEmail' => ['nullable', 'email', 'max:255'],
        ]);

        $this->event->update([
            'city' => $this->city ?: null,
            'race_director' => $this->raceDirector ?: null,
            'weather' => $this->weather ?: null,
            'contact_email' => $this->contactEmail ?: null,
        ]);
        $this->dispatch('details-saved');
    }

    // A cancelled or moved upplaga (#117).
    public string $newDate = '';

    public function cancelEvent(): void
    {
        $this->event->update(['cancelled_at' => now()]);
    }

    public function reinstate(): void
    {
        $this->event->update(['cancelled_at' => null]);
    }

    public function move(): void
    {
        $this->validate(['newDate' => ['required', 'date_format:Y-m-d', Rule::notIn([$this->event->date->toDateString()])]], [], ['newDate' => __('events.new_date')]);
        $this->event->moveTo($this->newDate);
        $this->reset('newDate');
    }

    public function editRace(int $id): void
    {
        $race = $this->event->races()->findOrFail($id);
        $this->cancel();
        $this->cancelRace();

        $this->editingRaceId = $race->id;
        $this->raceName = $race->name;
        $this->raceType = $race->type;
        $this->courseMeasurer = $race->course_measurer ?? '';
        $this->measuredOn = $race->measured_on?->toDateString() ?? '';
        $this->ageGroups = $race->age_groups;
        $this->championship = $race->championship ?? '';
        $this->championshipDistricts = $race->championship_districts ?? [];
        $this->championshipVeterans = $race->championship_veterans;
    }

    public function addRace(): void
    {
        $this->cancel();
        $this->cancelRace();
        $this->addingRace = true;
    }

    public function addClass(int $raceId): void
    {
        $this->cancel();
        $this->cancelRace();
        $this->raceId = $this->event->races()->findOrFail($raceId)->id;
        $this->addingClassTo = $raceId;
    }

    public function togglePrices(int $raceId): void
    {
        $this->pricesOpen = $this->pricesOpen === $raceId ? null : $raceId;
    }

    public function cancelRace(): void
    {
        $this->reset('addingRace', 'editingRaceId', 'raceName', 'raceType', 'courseMeasurer', 'measuredOn', 'ageGroups', 'championship', 'championshipDistricts', 'championshipVeterans');
        $this->resetValidation();
    }

    public function saveRace(): void
    {
        $this->validate([
            'raceName' => ['required', 'string', 'max:255'],
            'raceType' => ['required', Rule::in(App\Models\Race::TYPES)],
            'courseMeasurer' => ['nullable', 'string', 'max:100'],
            'measuredOn' => ['nullable', 'date'],
            'ageGroups' => ['boolean'],
            'championship' => ['nullable', 'in:DM,SM'],
            'championshipDistricts' => ['array', 'required_if:championship,DM'],
            'championshipVeterans' => ['boolean'],
        ]);

        $attributes = [
            'name' => $this->raceName,
            'type' => $this->raceType,
            'course_measurer' => $this->courseMeasurer ?: null,
            'measured_on' => $this->measuredOn ?: null,
            'age_groups' => $this->ageGroups,
            'championship' => $this->championship ?: null,
            'championship_districts' => $this->championship === 'DM' ? array_values($this->championshipDistricts) : null,
            'championship_veterans' => $this->championship ? $this->championshipVeterans : false,
        ];

        if ($this->editingRaceId) {
            $this->event->races()->findOrFail($this->editingRaceId)->update($attributes);
        } else {
            $race = $this->event->races()->create($attributes);
            $this->raceId ??= $race->id;
        }

        $this->cancelRace();
    }

    public function addPriceStep(int $raceId): void
    {
        // "250 kr" is how people write a price.
        $this->priceAmount[$raceId] = trim(preg_replace('/[^\d ,.]/', '', $this->priceAmount[$raceId] ?? ''));

        $this->validate([
            "priceUntil.{$raceId}" => ['required', 'date'],
            "priceAmount.{$raceId}" => ['required', 'regex:/^\d[\d ]*([.,]\d{1,2})?$/'],
        ], [], [
            "priceUntil.{$raceId}" => __('events.price_until_label'),
            "priceAmount.{$raceId}" => __('events.amount', ['currency' => $this->event->organizer->currency]),
        ]);

        $this->event->races()->findOrFail($raceId)->priceSteps()->create([
            'until' => $this->priceUntil[$raceId],
            'amount' => Money::toMinor($this->priceAmount[$raceId]),
        ]);
        unset($this->priceUntil[$raceId], $this->priceAmount[$raceId]);
    }

    public function saveOnsitePrice(int $raceId): void
    {
        $this->onsiteAmount[$raceId] = trim(preg_replace('/[^\d ,.]/', '', $this->onsiteAmount[$raceId] ?? ''));

        $this->validate([
            "onsiteAmount.{$raceId}" => ['required', 'regex:/^\d[\d ]*([.,]\d{1,2})?$/'],
        ], [], [
            "onsiteAmount.{$raceId}" => __('events.onsite_price_label', ['currency' => $this->event->organizer->currency]),
        ]);

        $this->event->races()->findOrFail($raceId)->update(['onsite_price' => Money::toMinor($this->onsiteAmount[$raceId])]);
        unset($this->onsiteAmount[$raceId]);
    }

    public function removeOnsitePrice(int $raceId): void
    {
        $this->event->races()->findOrFail($raceId)->update(['onsite_price' => null]);
    }

    public function removePriceStep(int $id): void
    {
        App\Models\PriceStep::whereIn('race_id', $this->event->races()->select('id'))->findOrFail($id)->delete();
    }

    public function editClass(int $id): void
    {
        $class = $this->event->raceClasses()->findOrFail($id);
        $this->cancel();
        $this->cancelRace();

        $this->editingId = $class->id;
        $this->raceId = $class->race_id;
        $this->className = $class->name;
        $this->distanceMeters = $class->distance_meters;
        $this->teamSize = $class->team_size;
        $this->gender = $class->gender ?? '';
        $this->timed = $class->timed;
        $this->startTime = $class->start_at->setTimezone($this->event->timezone)->format('H:i:s');
        $this->minTimeMinutes = intdiv($class->min_time_seconds, 60);
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'addingClassTo', 'className', 'distanceMeters', 'teamSize', 'gender', 'timed', 'startTime', 'minTimeMinutes');
        $this->resetValidation();
    }

    public function saveClass(): void
    {
        $this->validate([
            'raceId' => ['required', Rule::exists('races', 'id')->where('event_id', $this->event->id)],
            'className' => ['required', 'string', 'max:255'],
            'distanceMeters' => ['nullable', 'integer', 'min:1'],
            'teamSize' => ['nullable', 'integer', 'min:2', 'max:20'],
            'gender' => ['nullable', 'in:M,K'],
            'timed' => ['boolean'],
            'startTime' => ['required', 'regex:/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            'minTimeMinutes' => ['integer', 'min:0'],
        ]);

        $attributes = [
            'race_id' => $this->raceId,
            'name' => $this->className,
            'distance_meters' => $this->distanceMeters,
            'team_size' => $this->teamSize ?: null,
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

@php
    use App\Support\Format;

    $panel = 'rounded-panel border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900';
    $raceRunners = fn ($race) => $race->raceClasses->sum(fn ($class) => $counts[$class->id]->runners ?? 0);
    $tools = [
        'before' => [
            ['events.campaigns', 'ticket', __('campaigns.title')],
            ['events.theme', 'paint-brush', __('theme.title')],
            ['events.chips', 'tag', __('chips.title')],
        ],
        'raceday' => [
            ['events.onsite', 'user-plus', __('onsite.title')],
            ['events.start', 'flag', __('results.start')],
            ['events.timing', 'clock', __('timing.title')],
            ['events.missing', 'question-mark-circle', __('results.missing')],
            ['events.results', 'trophy', __('results.title')],
            ['events.uncollected', 'squares-2x2', __('results.uncollected')],
            ['events.prizes', 'gift', __('prizes.title')],
        ],
    ];
@endphp

<div class="flex max-w-5xl flex-col gap-8">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <flux:heading size="xl" level="1">{{ $event->name }}</flux:heading>
            <flux:text class="mt-1">{{ Format::date($event->date) }}@if ($event->city) · {{ $event->city }}@endif</flux:text>
        </div>
        @unless ($setup)
            <div class="flex flex-wrap gap-2">
                <flux:button :href="$event->publicUrl()" target="_blank" icon-trailing="arrow-top-right-on-square">{{ __('events.view_page') }}</flux:button>
                <flux:button :href="route('events.registrations', $event)" icon="users" variant="primary" wire:navigate>{{ __('registrations.title') }}</flux:button>
            </div>
        @endunless
    </header>

    @if ($event->cancelled_at)
        <flux:callout variant="danger" icon="x-circle" :heading="__('events.cancelled')" :text="__('events.cancelled_help')" />
    @elseif ($event->status() === 'moved')
        <flux:callout icon="calendar" :heading="__('events.moved', ['from' => Format::date($event->moved_from), 'to' => Format::date($event->date)])" />
    @endif

    @if ($setup)
        @php($current = array_search($setup, ['race', 'class', 'price']))
        <section class="{{ $panel }} flex flex-col gap-4 p-6">
            <div>
                <flux:heading size="lg" level="2">{{ __('events.setup_title') }}</flux:heading>
                <flux:subheading>{{ __('events.setup_progress', ['step' => $current + 1]) }}</flux:subheading>
            </div>
            <ol class="flex flex-col gap-3">
                @foreach (['race', 'class', 'price'] as $i => $key)
                    <li class="flex gap-3 {{ $i > $current ? 'text-zinc-500' : '' }}">
                        @if ($i < $current)
                            <flux:icon.check-circle variant="solid" class="shrink-0 text-moss" />
                        @else
                            <span class="flex size-6 shrink-0 items-center justify-center rounded-full border text-sm {{ $i === $current ? 'border-zinc-800 font-semibold dark:border-white' : 'border-zinc-300' }}">{{ $i + 1 }}</span>
                        @endif
                        <div>
                            <div class="{{ $i === $current ? 'font-semibold' : '' }}">{{ __("events.setup_{$key}") }}</div>
                            @if ($i === $current)
                                <flux:text class="text-sm">{{ __("events.setup_{$key}_help") }}</flux:text>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    @else
        {{-- The state of the upplaga at a glance: is it open, who has come, what has come in, how long is left. --}}
        <section aria-labelledby="overview" class="{{ $panel }}">
            <h2 id="overview" class="sr-only">{{ __('events.overview') }}</h2>
            <dl class="grid grid-cols-2 lg:grid-cols-4">
                <div class="flex flex-col gap-1 p-5">
                    <dt class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('events.overview_registration') }}</dt>
                    @if ($event->cancelled_at)
                        <dd class="text-xl font-medium">{{ __('events.state_closed') }}</dd>
                        <dd class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('events.cancelled') }}</dd>
                    @elseif ($openUntil)
                        <dd class="flex items-center gap-2 text-xl font-medium"><span class="size-2 rounded-full bg-moss" aria-hidden="true"></span>{{ __('events.state_open') }}</dd>
                        <dd class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('events.open_until', ['date' => Format::shortDate($openUntil)]) }}</dd>
                    @else
                        <dd class="text-xl font-medium">{{ __('events.state_closed') }}</dd>
                    @endif
                </div>
                <div class="flex flex-col gap-1 border-zinc-200 p-5 max-lg:border-s lg:border-s dark:border-zinc-700">
                    <dt class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('events.overview_runners') }}</dt>
                    <dd class="text-xl font-medium tabular-nums">{{ $runners }}</dd>
                    <dd class="text-sm text-zinc-600 dark:text-zinc-300"><flux:link :href="route('events.registrations', $event)" wire:navigate>{{ __('events.see_registrations') }}</flux:link></dd>
                </div>
                <div class="flex flex-col gap-1 border-zinc-200 p-5 max-lg:border-t lg:border-s dark:border-zinc-700">
                    <dt class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('events.overview_money') }}</dt>
                    <dd class="text-xl font-medium tabular-nums">{{ Money::format($money, $currency) }}</dd>
                    <dd class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('events.money_help') }}</dd>
                </div>
                <div class="flex flex-col gap-1 border-zinc-200 p-5 max-lg:border-s max-lg:border-t lg:border-s dark:border-zinc-700">
                    <dt class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('events.overview_day') }}</dt>
                    <dd class="text-xl font-medium">{{ $days === 0 ? __('events.today') : ($days > 0 ? trans_choice('events.days_left', $days) : trans_choice('events.days_ago', -$days)) }}</dd>
                    <dd class="text-sm text-zinc-600 dark:text-zinc-300">{{ Format::shortDate($event->date) }}</dd>
                </div>
            </dl>
            <div class="border-t border-zinc-200 p-5 dark:border-zinc-700">
                <flux:input :value="$event->publicUrl()" readonly copyable :label="__('events.public_link')" />
            </div>
        </section>

        @if ($todo->isNotEmpty())
            <flux:callout color="amber" icon="clipboard-document-list">
                <flux:callout.heading>{{ __('events.todo') }}</flux:callout.heading>
                <flux:callout.text>
                    <ul class="flex flex-col gap-1">
                        @foreach ($todo as $item)
                            <li><flux:link :href="$item['href']" wire:navigate>{{ $item['text'] }}</flux:link></li>
                        @endforeach
                    </ul>
                </flux:callout.text>
            </flux:callout>
        @endif

        <nav aria-label="{{ __('events.tools') }}" class="{{ $panel }} grid gap-6 p-5 md:grid-cols-[1fr_2fr]">
            @foreach ($tools as $group => $links)
                <div>
                    <h2 class="px-2 text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ __("events.tools_{$group}") }}</h2>
                    <ul class="mt-1 grid {{ $group === 'raceday' ? 'sm:grid-cols-2' : '' }}">
                        @foreach ($links as [$route, $icon, $label])
                            <li>
                                <a href="{{ route($route, $event) }}" @if ($route !== 'events.timing') wire:navigate @endif
                                    class="flex h-11 items-center gap-3 rounded-field px-2 font-medium hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                    <flux:icon :name="$icon" variant="outline" class="size-5 text-zinc-500 dark:text-zinc-400" />{{ $label }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>
    @endif

    <section aria-labelledby="races" class="flex flex-col gap-4">
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="xl" level="2" id="races" class="!text-xl">{{ __('events.races_and_classes') }}</flux:heading>
            @unless ($setup || $addingRace)
                <flux:button icon="plus" size="sm" wire:click="addRace">{{ __('events.new_race') }}</flux:button>
            @endunless
        </div>

        @foreach ($races as $race)
            @php($priceOpen = $pricesOpen === $race->id || ($setup === 'price' && $unpriced($race)))
            @php($classFormHere = ($editingId && $race->raceClasses->contains('id', $editingId)) || $addingClassTo === $race->id || ($setup === 'class' && ! $editingId && $raceId === $race->id))
            <article wire:key="race-{{ $race->id }}" id="race-{{ $race->id }}" class="{{ $panel }}">
                @if ($editingRaceId === $race->id)
                    @include('livewire.events.race-form')
                @else
                    <header class="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 pt-4">
                        <h3 class="text-base font-semibold">{{ $race->name }}</h3>
                        <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ $race->type }}@if ($race->championship) · {{ $race->championship }}{{ $race->championship_veterans ? ' / V'.$race->championship : '' }}@endif @if ($race->course_measurer) · {{ __('events.measured_by', ['name' => $race->course_measurer, 'date' => $race->measured_on?->toDateString()]) }}@endif</span>
                        <div class="ms-auto flex items-center gap-1">
                            <span class="me-2 text-sm tabular-nums text-zinc-600 dark:text-zinc-300">{{ trans_choice('events.runners', $raceRunners($race)) }}</span>
                            <flux:button size="sm" variant="ghost" icon="chevron-up" wire:click="moveRace({{ $race->id }}, -1)" :disabled="$loop->first" :aria-label="__('events.move_up', ['name' => $race->name])" />
                            <flux:button size="sm" variant="ghost" icon="chevron-down" wire:click="moveRace({{ $race->id }}, 1)" :disabled="$loop->last" :aria-label="__('events.move_down', ['name' => $race->name])" />
                            <flux:button size="sm" variant="ghost" wire:click="editRace({{ $race->id }})">{{ __('events.edit') }}</flux:button>
                        </div>
                    </header>
                @endif

                {{-- The prices in one line; the forms only when asked for. --}}
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3">
                    @php($price = $prices[$race->id])
                    @if ($price > 0 && ! $event->organizer->canTakePayments())
                        <flux:badge color="amber" size="sm">{{ __('events.registration_needs_stripe') }}</flux:badge>
                    @elseif ($price !== null)
                        <flux:badge color="green" size="sm">{{ __('events.registration_open', ['date' => Format::shortDate($race->closesOn()), 'price' => Money::format($price, $currency)]) }}</flux:badge>
                    @elseif ($race->priceSteps->isNotEmpty())
                        <flux:badge color="zinc" size="sm">{{ __('events.registration_ended', ['date' => Format::shortDate($race->closesOn())]) }}</flux:badge>
                    @else
                        <flux:badge color="amber" size="sm">{{ __('events.registration_closed') }}</flux:badge>
                    @endif
                    <span class="text-sm tabular-nums text-zinc-600 dark:text-zinc-300">
                        {{ $race->priceSteps->map(fn ($step) => Money::format($step->amount, $currency).' '.mb_strtolower(__('events.price_until', ['date' => Format::shortDate($step->until)])))
                            ->when($race->onsite_price !== null, fn ($line) => $line->push(__('events.onsite_short', ['price' => Money::format($race->onsite_price, $currency)])))->implode(' · ') }}
                    </span>
                    @unless ($setup === 'price' && $unpriced($race))
                        <flux:button size="sm" variant="ghost" class="ms-auto" icon-trailing="{{ $priceOpen ? 'chevron-up' : 'chevron-down' }}" wire:click="togglePrices({{ $race->id }})" aria-expanded="{{ $priceOpen ? 'true' : 'false' }}">{{ __('events.edit_prices') }}</flux:button>
                    @endunless
                </div>

                @if ($priceOpen)
                    <div class="mx-5 mb-4 flex flex-col gap-3 rounded-field bg-zinc-50 p-4 dark:bg-zinc-800">
                        @if ($price === null && $race->priceSteps->isEmpty())
                            <flux:text class="text-sm">{{ __('events.free_help') }}</flux:text>
                        @endif
                        @foreach ($race->priceSteps as $step)
                            <div wire:key="step-{{ $step->id }}" class="flex items-center gap-4 text-sm">
                                <span class="min-w-40">{{ __('events.price_until', ['date' => Format::shortDate($step->until)]) }}</span>
                                <span class="font-semibold tabular-nums">{{ Money::format($step->amount, $currency) }}</span>
                                <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="removePriceStep({{ $step->id }})" :aria-label="__('events.remove')" />
                            </div>
                        @endforeach
                        @if ($race->onsite_price !== null)
                            <div class="flex items-center gap-4 text-sm">
                                <span class="min-w-40">{{ __('events.onsite_price') }}</span>
                                <span class="font-semibold tabular-nums">{{ Money::format($race->onsite_price, $currency) }}</span>
                                <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="removeOnsitePrice({{ $race->id }})" :aria-label="__('events.remove')" />
                            </div>
                        @endif
                        <div class="flex flex-wrap items-end gap-2">
                            <flux:input wire:model="priceUntil.{{ $race->id }}" type="date" size="sm" :label="__('events.price_until_label')" />
                            <flux:input wire:model="priceAmount.{{ $race->id }}" size="sm" :label="__('events.amount', ['currency' => $currency])" />
                            <flux:button size="sm" wire:click="addPriceStep({{ $race->id }})">{{ __('events.add_price_step') }}</flux:button>
                        </div>
                        <div class="flex flex-wrap items-end gap-2">
                            <flux:input wire:model="onsiteAmount.{{ $race->id }}" size="sm" :label="__('events.onsite_price_label', ['currency' => $currency])" :description="__('events.onsite_help')" />
                            <flux:button size="sm" wire:click="saveOnsitePrice({{ $race->id }})">{{ __('events.save') }}</flux:button>
                        </div>
                    </div>
                @endif

                @if ($race->raceClasses->isNotEmpty())
                    @include('livewire.events.class-table', ['classes' => Format::classOrder($race->raceClasses), 'caption' => __('events.classes_in', ['race' => $race->name])])
                @elseif ($setup !== 'class')
                    <flux:text class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">{{ __('events.no_classes') }}</flux:text>
                @endif

                @if ($classFormHere)
                    @include('livewire.events.class-form')
                @elseif (! $setup)
                    <div class="border-t border-zinc-200 px-3 py-2 dark:border-zinc-700">
                        <flux:button size="sm" variant="ghost" icon="plus" wire:click="addClass({{ $race->id }})">{{ __('events.new_class') }}</flux:button>
                    </div>
                @endif
            </article>
        @endforeach

        @if ($unassigned->isNotEmpty())
            <article class="{{ $panel }}">
                @include('livewire.events.class-table', ['classes' => $unassigned, 'caption' => __('events.classes')])
                @if ($editingId && $unassigned->contains('id', $editingId))
                    @include('livewire.events.class-form')
                @endif
            </article>
        @endif

        @if ($setup === 'race' || $addingRace)
            <div class="{{ $panel }}">@include('livewire.events.race-form')</div>
        @endif
    </section>

    @unless ($setup)
        {{-- Rarely needed: kept closed. wire:ignore.self keeps a details open across Livewire updates. --}}
        <section class="flex flex-col gap-3">
            <details wire:ignore.self class="{{ $panel }} group">
                <summary class="flex min-h-14 cursor-pointer list-none items-center gap-3 px-5 py-3">
                    <span class="font-medium">{{ __('events.details') }}</span>
                    <span class="truncate text-sm text-zinc-600 dark:text-zinc-300">{{ collect([$event->city, $event->race_director])->filter()->implode(' · ') ?: __('events.details_empty') }}</span>
                    <flux:icon.chevron-down class="ms-auto size-5 shrink-0 text-zinc-500 transition group-open:rotate-180" />
                </summary>
                <form wire:submit="saveDetails" class="flex flex-col gap-4 border-t border-zinc-200 p-5 dark:border-zinc-700">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="city" :label="__('events.city')" />
                        <flux:input wire:model="raceDirector" :label="__('events.race_director')" />
                        <flux:input wire:model="weather" :label="__('events.weather')" :placeholder="__('events.weather_example')" />
                        <flux:input wire:model="contactEmail" type="email" :label="__('events.contact_email')" />
                    </div>
                    <div class="flex items-center gap-3">
                        <flux:button type="submit">{{ __('events.save') }}</flux:button>
                        <x-action-message on="details-saved">{{ __('events.saved') }}</x-action-message>
                    </div>
                </form>
            </details>

            <details wire:ignore.self class="{{ $panel }} group">
                <summary class="flex min-h-14 cursor-pointer list-none items-center gap-3 px-5 py-3">
                    <span class="font-medium">{{ __('events.status') }}</span>
                    <flux:icon.chevron-down class="ms-auto size-5 shrink-0 text-zinc-500 transition group-open:rotate-180" />
                </summary>
                <div class="flex flex-col gap-3 border-t border-zinc-200 p-5 dark:border-zinc-700">
                    @if ($event->cancelled_at)
                        <div><flux:button wire:click="reinstate">{{ __('events.reinstate') }}</flux:button></div>
                    @else
                        <div class="flex flex-wrap items-end gap-3">
                            <flux:input wire:model="newDate" type="date" :label="__('events.new_date')" class="w-48" />
                            <flux:button wire:click="move">{{ __('events.move') }}</flux:button>
                            <flux:button variant="danger" wire:click="cancelEvent" wire:confirm="{{ __('events.cancel_confirm') }}" class="ms-auto">{{ __('events.cancel_event') }}</flux:button>
                        </div>
                        <flux:text class="text-sm">{{ __('events.status_help') }}</flux:text>
                    @endif
                </div>
            </details>
        </section>
    @endunless
</div>
