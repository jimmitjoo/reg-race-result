<?php

use App\Models\Club;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\RaceClass;
use App\Payments\StripeCheckout;
use App\Support\Money;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.public')] class extends Component {
    public const HALF_MARATHON_METERS = 21097;

    public Event $raceEvent;

    // A string, not null: a null select value makes the browser show the first class as chosen.
    public string $raceClassId = '';
    public string $firstName = '';
    public string $lastName = '';
    public string $birthDate = '';
    public string $gender = '';
    public string $club = '';
    public string $email = '';
    public string $phone = '';

    public function mount(string $organizer, string $event): void
    {
        $this->raceEvent = Organizer::where('slug', $organizer)->firstOrFail()
            ->events()->where('slug', $event)->firstOrFail();
    }

    public function with(): array
    {
        $now = now();

        return [
            'event' => $this->raceEvent,
            'races' => $this->raceEvent->races()->with(['raceClasses' => fn ($q) => $q->orderBy('start_at')->orderBy('name')])->orderBy('name')->get()
                ->map(fn ($race) => ['race' => $race, 'price' => $race->priceAt($now)]),
            'currency' => $this->raceEvent->organizer->currency,
            'clubs' => Club::orderBy('name')->pluck('name'),
        ];
    }

    public function register(): void
    {
        foreach (['firstName', 'lastName', 'club', 'email', 'phone'] as $field) {
            $this->{$field} = trim($this->{$field});
        }

        $this->validate([
            'raceClassId' => ['required', Rule::exists('race_classes', 'id')->where('event_id', $this->raceEvent->id)],
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'birthDate' => ['required', 'date_format:Y-m-d', 'before:today'],
            'gender' => ['required', 'in:M,K'],
            'club' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $class = RaceClass::with('race')->findOrFail((int) $this->raceClassId);
        $price = $class->race?->priceAt(now());

        if ($price === null) {
            $this->addError('raceClassId', __('registration.class_not_open'));

            return;
        }

        $year = now()->setTimezone($this->raceEvent->timezone)->year;
        if ($class->distance_meters >= self::HALF_MARATHON_METERS && $year - (int) substr($this->birthDate, 0, 4) < 17) {
            $this->addError('birthDate', __('registration.min_age'));

            return;
        }

        $club = Club::match($this->club);

        $registration = $class->registrations()->create([
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'birth_date' => $this->birthDate,
            'gender' => $this->gender,
            'club' => $club?->name ?? ($this->club ?: null),
            'club_id' => $club?->id,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'price' => $price,
        ]);

        $confirmationUrl = URL::signedRoute('public.registration', [
            'organizer' => $this->raceEvent->organizer->slug,
            'event' => $this->raceEvent->slug,
            'registration' => $registration,
        ]);

        if ($price === 0) {
            $registration->markPaid();
            $this->redirect($confirmationUrl);

            return;
        }

        $eventUrl = route('public.event', ['organizer' => $this->raceEvent->organizer->slug, 'event' => $this->raceEvent->slug]);
        $session = app(StripeCheckout::class)->create($registration, $confirmationUrl, $eventUrl);
        $registration->update(['stripe_checkout_session_id' => $session['id']]);
        $this->redirect($session['url']);
    }
}; ?>

<div class="flex flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ $event->name }}</flux:heading>
        <flux:subheading>{{ $event->date->toDateString() }} · {{ $event->organizer->name }}</flux:subheading>
    </div>

    <section class="flex flex-col gap-3">
        <flux:heading size="lg">{{ __('registration.classes') }}</flux:heading>
        @foreach ($races as ['race' => $race, 'price' => $price])
            <div wire:key="race-{{ $race->id }}" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="flex justify-between font-semibold">
                    <span>{{ $race->name }}</span>
                    <span>{{ $price === null ? __('registration.closed') : Money::format($price, $currency) }}</span>
                </div>
                <ul class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    @foreach ($race->raceClasses as $class)
                        <li>{{ $class->name }} · {{ $class->start_at->setTimezone($event->timezone)->format('H:i') }}</li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </section>

    <form wire:submit="register" class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('registration.title') }}</flux:heading>

        <flux:select wire:model="raceClassId" :label="__('registration.class')" :placeholder="__('registration.choose_class')">
            @foreach ($races as ['race' => $race, 'price' => $price])
                @if ($price !== null)
                    @foreach ($race->raceClasses as $class)
                        <option value="{{ $class->id }}">{{ $race->name }} – {{ $class->name }} ({{ Money::format($price, $currency) }})</option>
                    @endforeach
                @endif
            @endforeach
        </flux:select>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="firstName" :label="__('registration.first_name')" autocomplete="given-name" />
            <flux:input wire:model="lastName" :label="__('registration.last_name')" autocomplete="family-name" />
            <flux:input wire:model="birthDate" type="date" :label="__('registration.birth_date')" autocomplete="bday" />
            <flux:select wire:model="gender" :label="__('registration.gender')" :placeholder="__('registration.gender')">
                <option value="K">{{ __('registration.gender_k') }}</option>
                <option value="M">{{ __('registration.gender_m') }}</option>
            </flux:select>
        </div>

        <flux:input wire:model="club" list="clubs" autocomplete="off" :label="__('registration.club')" :description="__('registration.club_help')" />
        <datalist id="clubs">
            @foreach ($clubs as $name)
                <option value="{{ $name }}">
            @endforeach
        </datalist>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="email" type="email" :label="__('registration.email')" autocomplete="email" />
            <flux:input wire:model="phone" type="tel" :label="__('registration.phone')" autocomplete="tel" />
        </div>

        <flux:text class="text-sm">{{ __('registration.publication_notice') }}</flux:text>

        <div><flux:button type="submit" variant="primary">{{ __('registration.submit') }}</flux:button></div>
    </form>
</div>
