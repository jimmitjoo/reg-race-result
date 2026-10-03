<?php

use App\Models\Club;
use App\Models\Event;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public Event $event;

    public string $raceClassId = '';
    public string $bib = '';
    public string $firstName = '';
    public string $lastName = '';
    public string $birthDate = '';
    public string $gender = '';
    public string $club = '';
    public string $email = '';
    public bool $paidWithSwish = false;

    public function with(): array
    {
        return [
            'classes' => $this->event->raceClasses()->with('race')->orderBy('start_at')->orderBy('name')->get(),
            'today' => $this->event->registrations()->with('raceClass')->where('payment_method', 'swish_onsite')->latest('registrations.created_at')->get(),
            'clubs' => Club::orderBy('name')->pluck('name'),
            'currency' => $this->event->organizer->currency,
        ];
    }

    public function register(): void
    {
        foreach (['bib', 'firstName', 'lastName', 'club', 'email'] as $field) {
            $this->{$field} = trim($this->{$field});
        }

        $this->validate([
            'raceClassId' => ['required', Rule::exists('race_classes', 'id')->where('event_id', $this->event->id)],
            'bib' => ['required', 'integer', 'min:1'],
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'birthDate' => ['required', 'date_format:Y-m-d', 'before:today'],
            'gender' => ['required', 'in:M,K'],
            'club' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'paidWithSwish' => ['accepted'],
        ]);

        $class = RaceClass::with('race')->findOrFail((int) $this->raceClassId);
        if (! $this->bibIsUsable((int) $this->bib, $class)) {
            return;
        }

        $club = Club::match($this->club);
        $registration = $class->registrations()->create([
            'bib' => (int) $this->bib,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'birth_date' => $this->birthDate,
            'gender' => $this->gender,
            'club' => $club?->name ?? ($this->club ?: null),
            'club_id' => $club?->id,
            'email' => $this->email ?: null,
            'price' => $class->race?->onsite_price ?? $class->race?->priceAt(now()) ?? 0,
        ]);
        $registration->markPaid('swish_onsite');

        $this->reset('bib', 'firstName', 'lastName', 'birthDate', 'gender', 'club', 'email', 'paidWithSwish');
    }

    public function changeBib(int $id, string $bib): void
    {
        $registration = $this->event->registrations()->with('raceClass')->findOrFail($id);
        if (! ctype_digit($bib) || ! $this->bibIsUsable((int) $bib, $registration->raceClass, $registration->id)) {
            $this->addError('bib', $this->getErrorBag()->first('bib') ?: __('onsite.bib_invalid'));

            return;
        }

        $registration->update(['bib' => (int) $bib]);
    }

    private function bibIsUsable(int $bib, RaceClass $class, ?int $ignore = null): bool
    {
        if ($this->event->registrations()->where('bib', $bib)->when($ignore, fn ($q) => $q->where('registrations.id', '!=', $ignore))->exists()) {
            $this->addError('bib', __('onsite.bib_taken', ['bib' => $bib]));

            return false;
        }

        if ($class->timed && ! $this->event->chips()->where('bib', $bib)->exists()) {
            $this->addError('bib', __('onsite.bib_without_chip', ['bib' => $bib]));

            return false;
        }

        return true;
    }
}; ?>

<div class="flex max-w-3xl flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('onsite.title') }}</flux:heading>
        <flux:subheading>{{ $event->name }}</flux:subheading>
    </div>

    <form wire:submit="register" class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <flux:select wire:model="raceClassId" :label="__('registration.class')" :placeholder="__('registration.choose_class')">
                    @foreach ($classes as $class)
                        @php($price = $class->race?->onsite_price ?? $class->race?->priceAt(now()))
                        <option value="{{ $class->id }}">{{ $class->race?->name }} – {{ $class->name }}@if ($price !== null) ({{ Money::format($price, $currency) }})@endif</option>
                    @endforeach
                </flux:select>
            </div>
            <flux:input wire:model="bib" inputmode="numeric" :label="__('onsite.bib')" :description="__('onsite.bib_help')" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="firstName" :label="__('registration.first_name')" />
            <flux:input wire:model="lastName" :label="__('registration.last_name')" />
            <flux:input wire:model="birthDate" type="date" :label="__('registration.birth_date')" />
            <flux:select wire:model="gender" :label="__('registration.gender')" :placeholder="__('registration.gender')">
                <option value="K">{{ __('registration.gender_k') }}</option>
                <option value="M">{{ __('registration.gender_m') }}</option>
            </flux:select>
        </div>
        <flux:input wire:model="club" list="clubs" autocomplete="off" :label="__('registration.club')" />
        <datalist id="clubs">
            @foreach ($clubs as $name)
                <option value="{{ $name }}">
            @endforeach
        </datalist>
        <flux:input wire:model="email" type="email" :label="__('onsite.email_optional')" />
        <flux:checkbox wire:model="paidWithSwish" :label="__('onsite.paid_with_swish')" :description="__('onsite.paid_with_swish_help')" />
        <div><flux:button type="submit" variant="primary">{{ __('registration.submit') }}</flux:button></div>
    </form>

    <section class="flex flex-col gap-2">
        <flux:heading size="lg">{{ __('onsite.today') }}</flux:heading>
        <flux:error name="bib" />
        @forelse ($today as $registration)
            <div wire:key="onsite-{{ $registration->id }}" x-data="{ bib: '{{ $registration->bib }}' }" class="flex flex-wrap items-center gap-3 rounded-lg border border-zinc-200 px-4 py-2 dark:border-zinc-700">
                <span class="font-mono text-lg font-semibold">{{ $registration->bib }}</span>
                <span class="flex-1">{{ $registration->first_name }} {{ $registration->last_name }} · {{ $registration->raceClass->name }}</span>
                <flux:badge color="green">{{ __('onsite.paid') }}</flux:badge>
                <flux:input x-model="bib" size="sm" class="w-24" :aria-label="__('onsite.bib')" />
                <flux:button size="sm" x-on:click="$wire.changeBib({{ $registration->id }}, bib)">{{ __('onsite.change_bib') }}</flux:button>
            </div>
        @empty
            <flux:text>{{ __('onsite.none_today') }}</flux:text>
        @endforelse
    </section>
</div>
