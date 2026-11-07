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

    public function mount(): void
    {
        $this->city = $this->event->city ?? '';
        $this->raceDirector = $this->event->race_director ?? '';
        $this->weather = $this->event->weather ?? '';
        $this->contactEmail = $this->event->contact_email ?? '';
        $this->raceId = $this->event->races()->orderBy('name')->value('id');
    }

    public function with(): array
    {
        $races = $this->event->races()->with(['priceSteps', 'raceClasses' => fn ($q) => $q->orderBy('start_at')->orderBy('name')])->orderBy('name')->get();

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

        return [
            'races' => $races,
            'setup' => $setup,
            'unpriced' => $unpriced,
            'unassigned' => $this->event->raceClasses()->whereNull('race_id')->orderBy('start_at')->get(),
            'currency' => $this->event->organizer->currency,
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
    }

    public function editRace(int $id): void
    {
        $race = $this->event->races()->findOrFail($id);

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

    public function cancelRace(): void
    {
        $this->reset('editingRaceId', 'raceName', 'raceType', 'courseMeasurer', 'measuredOn', 'ageGroups', 'championship', 'championshipDistricts', 'championshipVeterans');
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
        $this->reset('editingId', 'className', 'distanceMeters', 'teamSize', 'gender', 'timed', 'startTime', 'minTimeMinutes');
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

<div class="flex max-w-4xl flex-col gap-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $event->name }}</flux:heading>
            <flux:subheading>{{ \App\Support\Format::date($event->date) }}@if ($event->city) · {{ $event->city }}@endif · {{ $event->timezone }}</flux:subheading>
            @unless ($setup)
                <flux:button :href="route('events.registrations', $event)" icon="users" variant="primary" class="mt-3" wire:navigate>{{ __('registrations.title') }}</flux:button>
            @endunless
        </div>
        @unless ($setup)
        <div class="flex flex-wrap gap-2">
            <flux:button :href="route('events.chips', $event)" icon="tag" wire:navigate>{{ __('chips.title') }}</flux:button>
            <flux:button :href="route('events.onsite', $event)" icon="user-plus" wire:navigate>{{ __('onsite.title') }}</flux:button>
            <flux:button :href="route('events.timing', $event)" icon="clock">{{ __('timing.title') }}</flux:button>
            <flux:button :href="route('events.start', $event)" icon="flag" wire:navigate>{{ __('results.start') }}</flux:button>
            <flux:button :href="route('events.results', $event)" icon="trophy" wire:navigate>{{ __('results.title') }}</flux:button>
            <flux:button :href="route('events.missing', $event)" icon="question-mark-circle" wire:navigate>{{ __('results.missing') }}</flux:button>
            <flux:button :href="route('events.uncollected', $event)" icon="squares-2x2" wire:navigate>{{ __('results.uncollected') }}</flux:button>
            <flux:button :href="route('events.prizes', $event)" icon="gift" wire:navigate>{{ __('prizes.title') }}</flux:button>
        </div>
        @endunless
    </div>

    @if ($setup)
        @php($current = array_search($setup, ['race', 'class', 'price']))
        <section class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <div>
                <flux:heading size="lg">{{ __('events.setup_title') }}</flux:heading>
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
    <flux:input :value="$event->publicUrl()" readonly copyable :label="__('events.public_link')" />

    <form wire:submit="saveDetails" class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading>{{ __('events.details') }}</flux:heading>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="city" :label="__('events.city')" />
            <flux:input wire:model="raceDirector" :label="__('events.race_director')" />
            <flux:input wire:model="weather" :label="__('events.weather')" :placeholder="__('events.weather_example')" />
            <flux:input wire:model="contactEmail" type="email" :label="__('events.contact_email')" />
        </div>
        <div><flux:button type="submit">{{ __('events.save') }}</flux:button></div>
    </form>
    @endif

    @foreach ($races as $race)
        <section wire:key="race-{{ $race->id }}" class="flex flex-col gap-3">
            <div class="flex items-center gap-3">
                <flux:heading size="lg">{{ $race->name }}</flux:heading>
                <span class="text-sm text-zinc-500">{{ $race->type }}@if ($race->championship) · {{ $race->championship }}{{ $race->championship_veterans ? ' / V'.$race->championship : '' }}@endif @if ($race->course_measurer) · {{ __('events.measured_by', ['name' => $race->course_measurer, 'date' => $race->measured_on?->toDateString()]) }}@endif</span>
                <flux:button size="sm" variant="ghost" class="ms-auto" wire:click="editRace({{ $race->id }})">{{ __('events.edit') }}</flux:button>
            </div>

            @if (! $setup || $setup === 'price' && $unpriced($race))
            <div class="flex flex-col gap-2 rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                <flux:heading size="sm">{{ __('events.prices') }}</flux:heading>
                @php($price = $race->priceAt(now()))
                @if ($price > 0 && ! $event->organizer->canTakePayments())
                    <flux:badge color="amber" class="self-start">{{ __('events.registration_needs_stripe') }}</flux:badge>
                @elseif ($price !== null)
                    <flux:badge color="green" class="self-start">{{ __('events.registration_open', ['date' => \App\Support\Format::shortDate($race->priceSteps->last()->until), 'price' => Money::format($price, $currency)]) }}</flux:badge>
                @elseif ($race->priceSteps->isNotEmpty())
                    <flux:badge color="zinc" class="self-start">{{ __('events.registration_ended', ['date' => \App\Support\Format::shortDate($race->priceSteps->last()->until)]) }}</flux:badge>
                @else
                    <flux:badge color="amber" class="self-start">{{ __('events.registration_closed') }}</flux:badge>
                    <flux:text class="text-sm">{{ __('events.free_help') }}</flux:text>
                @endif
                @foreach ($race->priceSteps as $step)
                    <div wire:key="step-{{ $step->id }}" class="flex items-center gap-4 text-sm">
                        <span class="min-w-48">{{ __('events.price_until', ['date' => \App\Support\Format::shortDate($step->until)]) }}</span>
                        <span class="font-semibold">{{ Money::format($step->amount, $currency) }}</span>
                        <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="removePriceStep({{ $step->id }})" :aria-label="__('events.remove')" />
                    </div>
                @endforeach
                @if ($race->onsite_price !== null)
                    <div class="flex items-center gap-4 text-sm">
                        <span class="min-w-48">{{ __('events.onsite_price') }}</span>
                        <span class="font-semibold">{{ Money::format($race->onsite_price, $currency) }}</span>
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

            @forelse (\App\Support\Format::classOrder($race->raceClasses) as $class)
                @include('livewire.events.class-row')
            @empty
                @unless ($setup === 'class')
                    <flux:text>{{ __('events.no_classes') }}</flux:text>
                @endunless
            @endforelse
        </section>
    @endforeach

    @foreach ($unassigned as $class)
        @include('livewire.events.class-row')
    @endforeach

    @if (! $setup || $setup === 'race' || $editingRaceId)
    <form wire:submit="saveRace" class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading>{{ $editingRaceId ? __('events.edit_race') : __('events.new_race') }}</flux:heading>
        <flux:input wire:model="raceName" :label="__('events.race_name')" :placeholder="__('events.race_name_example')" />
        <div class="grid gap-4 sm:grid-cols-3">
            <flux:select wire:model="raceType" :label="__('events.race_type')">
                @foreach (App\Models\Race::TYPES as $type)
                    <option value="{{ $type }}">{{ $type }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="courseMeasurer" :label="__('events.course_measurer')" />
            <flux:input wire:model="measuredOn" type="date" :label="__('events.measured_on')" />
        </div>
        <flux:text class="text-sm">{{ __('events.measurement_help') }}</flux:text>
        <flux:checkbox wire:model="ageGroups" :label="__('events.age_groups')" :description="__('events.age_groups_help')" />
        <flux:select wire:model.live="championship" :label="__('events.championship')">
            <option value="">{{ __('events.championship_none') }}</option>
            <option value="DM">{{ __('events.championship_dm') }}</option>
            <option value="SM">{{ __('events.championship_sm') }}</option>
        </flux:select>
        @if ($championship === 'DM')
            <flux:checkbox.group wire:model="championshipDistricts" :label="__('events.championship_districts')" :description="__('events.championship_districts_help')">
                <div class="grid grid-cols-2 gap-1 sm:grid-cols-3">
                    @foreach ($districts as $district)
                        <flux:checkbox :value="$district" :label="$district" />
                    @endforeach
                </div>
            </flux:checkbox.group>
        @endif
        @if ($championship)
            <flux:checkbox wire:model="championshipVeterans" :label="__('events.championship_veterans', ['name' => 'V'.$championship])" />
        @endif
        <div class="flex gap-2">
            <flux:button type="submit">{{ $editingRaceId ? __('events.save') : __('events.create') }}</flux:button>
            @if ($editingRaceId)
                <flux:button wire:click="cancelRace">{{ __('events.cancel') }}</flux:button>
            @endif
        </div>
    </form>
    @endif

    @if (! $setup || $setup === 'class' || $editingId)
    <form wire:submit="saveClass" class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        @if ($setup === 'class' && ! $editingId)
            {{-- The race is the one that still lacks classes, see with(). --}}
            <flux:heading>{{ __('events.new_class_in', ['race' => $races->firstWhere('id', $raceId)->name]) }}</flux:heading>
        @else
            <flux:heading>{{ $editingId ? __('events.edit_class') : __('events.new_class') }}</flux:heading>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            @unless ($setup === 'class' && ! $editingId)
                <flux:select wire:model="raceId" :label="__('events.race')" :placeholder="__('events.choose_race')">
                    @foreach ($races as $race)
                        <option value="{{ $race->id }}">{{ $race->name }}</option>
                    @endforeach
                </flux:select>
            @endunless
            <flux:input wire:model="className" :label="__('events.class_name')" :placeholder="__('events.class_name_example')" />
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <flux:input wire:model="startTime" :label="__('events.start_time')" placeholder="10:00:00" />
            <flux:input wire:model="distanceMeters" type="number" min="1" :label="__('events.distance_meters')" />
            <flux:input wire:model="teamSize" type="number" min="2" max="20" :label="__('events.team_size')" :description="__('events.team_size_help')" />
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
    @endif
</div>
