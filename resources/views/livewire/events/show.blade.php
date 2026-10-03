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
    public string $onsitePrice = '';
    public string $raceType = 'Väg';
    public string $courseMeasurer = '';
    public string $measuredOn = '';
    public bool $ageGroups = false;

    public ?int $editingId = null;
    public ?int $raceId = null;
    public string $className = '';
    public ?int $distanceMeters = null;
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
        return [
            'races' => $this->event->races()->with(['priceSteps', 'raceClasses' => fn ($q) => $q->orderBy('start_at')->orderBy('name')])->orderBy('name')->get(),
            'unassigned' => $this->event->raceClasses()->whereNull('race_id')->orderBy('start_at')->get(),
            'currency' => $this->event->organizer->currency,
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
        $this->onsitePrice = $race->onsite_price === null ? '' : (string) ($race->onsite_price / 100);
        $this->raceType = $race->type;
        $this->courseMeasurer = $race->course_measurer ?? '';
        $this->measuredOn = $race->measured_on?->toDateString() ?? '';
        $this->ageGroups = $race->age_groups;
    }

    public function cancelRace(): void
    {
        $this->reset('editingRaceId', 'raceName', 'onsitePrice', 'raceType', 'courseMeasurer', 'measuredOn', 'ageGroups');
        $this->resetValidation();
    }

    public function saveRace(): void
    {
        $this->validate([
            'raceName' => ['required', 'string', 'max:255'],
            'onsitePrice' => ['nullable', 'regex:/^\d[\d ]*([.,]\d{1,2})?$/'],
            'raceType' => ['required', Rule::in(App\Models\Race::TYPES)],
            'courseMeasurer' => ['nullable', 'string', 'max:100'],
            'measuredOn' => ['nullable', 'date'],
            'ageGroups' => ['boolean'],
        ]);

        $attributes = [
            'name' => $this->raceName,
            'onsite_price' => $this->onsitePrice === '' ? null : Money::toMinor($this->onsitePrice),
            'type' => $this->raceType,
            'course_measurer' => $this->courseMeasurer ?: null,
            'measured_on' => $this->measuredOn ?: null,
            'age_groups' => $this->ageGroups,
        ];

        if ($this->editingRaceId) {
            $this->event->races()->findOrFail($this->editingRaceId)->update($attributes);
        } else {
            $race = $this->event->races()->create($attributes);
            $this->raceId ??= $race->id;
        }

        $this->cancelRace();
    }

    public function addPriceStep(int $raceId, string $until, string $amount): void
    {
        validator(compact('until', 'amount'), [
            'until' => ['required', 'date'],
            'amount' => ['required', 'regex:/^\d[\d ]*([.,]\d{1,2})?$/'],
        ])->validate();

        $this->event->races()->findOrFail($raceId)->priceSteps()->create(['until' => $until, 'amount' => Money::toMinor($amount)]);
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
        $this->gender = $class->gender ?? '';
        $this->timed = $class->timed;
        $this->startTime = $class->start_at->setTimezone($this->event->timezone)->format('H:i:s');
        $this->minTimeMinutes = intdiv($class->min_time_seconds, 60);
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'className', 'distanceMeters', 'gender', 'timed', 'startTime', 'minTimeMinutes');
        $this->resetValidation();
    }

    public function saveClass(): void
    {
        $this->validate([
            'raceId' => ['required', Rule::exists('races', 'id')->where('event_id', $this->event->id)],
            'className' => ['required', 'string', 'max:255'],
            'distanceMeters' => ['nullable', 'integer', 'min:1'],
            'gender' => ['nullable', 'in:M,K'],
            'timed' => ['boolean'],
            'startTime' => ['required', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            'minTimeMinutes' => ['integer', 'min:0'],
        ]);

        $attributes = [
            'race_id' => $this->raceId,
            'name' => $this->className,
            'distance_meters' => $this->distanceMeters,
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
            <flux:subheading>{{ $event->date->toDateString() }}@if ($event->city) · {{ $event->city }}@endif · {{ $event->timezone }}</flux:subheading>
        </div>
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
    </div>

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

    @foreach ($races as $race)
        <section wire:key="race-{{ $race->id }}" class="flex flex-col gap-3">
            <div class="flex items-center gap-3">
                <flux:heading size="lg">{{ $race->name }}</flux:heading>
                <span class="text-sm text-zinc-500">{{ $race->type }}@if ($race->course_measurer) · {{ __('events.measured_by', ['name' => $race->course_measurer, 'date' => $race->measured_on?->toDateString()]) }}@endif</span>
                <flux:button size="sm" variant="ghost" class="ms-auto" wire:click="editRace({{ $race->id }})">{{ __('events.edit') }}</flux:button>
            </div>

            <div class="flex flex-col gap-2 rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800" x-data="{ until: '', amount: '' }">
                <flux:heading size="sm">{{ __('events.prices') }}</flux:heading>
                @foreach ($race->priceSteps as $step)
                    <div wire:key="step-{{ $step->id }}" class="flex items-center gap-4 text-sm">
                        <span class="min-w-48">{{ __('events.price_until', ['date' => $step->until->toDateString()]) }}</span>
                        <span class="font-semibold">{{ Money::format($step->amount, $currency) }}</span>
                        <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="removePriceStep({{ $step->id }})" :aria-label="__('events.remove')" />
                    </div>
                @endforeach
                @if ($race->onsite_price !== null)
                    <div class="flex items-center gap-4 text-sm">
                        <span class="min-w-48">{{ __('events.onsite_price') }}</span>
                        <span class="font-semibold">{{ Money::format($race->onsite_price, $currency) }}</span>
                    </div>
                @endif
                <div class="flex flex-wrap items-end gap-2">
                    <flux:input x-model="until" type="date" size="sm" :label="__('events.price_until_label')" />
                    <flux:input x-model="amount" size="sm" :label="__('events.amount', ['currency' => $currency])" />
                    <flux:button size="sm" x-on:click="$wire.addPriceStep({{ $race->id }}, until, amount).then(() => { until = ''; amount = '' })">{{ __('events.add_price_step') }}</flux:button>
                </div>
            </div>

            @forelse ($race->raceClasses as $class)
                @include('livewire.events.class-row')
            @empty
                <flux:text>{{ __('events.no_classes') }}</flux:text>
            @endforelse
        </section>
    @endforeach

    @foreach ($unassigned as $class)
        @include('livewire.events.class-row')
    @endforeach

    <form wire:submit="saveRace" class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading>{{ $editingRaceId ? __('events.edit_race') : __('events.new_race') }}</flux:heading>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="raceName" :label="__('events.race_name')" :placeholder="__('events.race_name_example')" />
            <flux:input wire:model="onsitePrice" :label="__('events.onsite_price_label', ['currency' => $currency])" />
        </div>
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
        <div class="flex gap-2">
            <flux:button type="submit">{{ $editingRaceId ? __('events.save') : __('events.create') }}</flux:button>
            @if ($editingRaceId)
                <flux:button wire:click="cancelRace">{{ __('events.cancel') }}</flux:button>
            @endif
        </div>
    </form>

    <form wire:submit="saveClass" class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading>{{ $editingId ? __('events.edit_class') : __('events.new_class') }}</flux:heading>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model="raceId" :label="__('events.race')" :placeholder="__('events.choose_race')">
                @foreach ($races as $race)
                    <option value="{{ $race->id }}">{{ $race->name }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="className" :label="__('events.class_name')" :placeholder="__('events.class_name_example')" />
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <flux:input wire:model="startTime" :label="__('events.start_time')" placeholder="10:00:00" />
            <flux:input wire:model="distanceMeters" type="number" min="1" :label="__('events.distance_meters')" />
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
</div>
