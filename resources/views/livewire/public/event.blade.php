<?php

use App\Models\Club;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Payments\StripeCheckout;
use App\Support\Money;
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
    public bool $acceptTerms = false;

    // A team class (#31): the one who registers is the team's contact and names the members.
    public string $teamName = '';
    public array $members = [];

    // The arrangemang's slug shows its current upplaga, so the link stays the same every year; an upplaga's own slug still works.
    public function mount(string $organizer, string $event): void
    {
        $organizer = Organizer::where('slug', $organizer)->firstOrFail();
        $this->raceEvent = $organizer->series()->where('slug', $event)->first()?->current()
            ?? $organizer->events()->where('slug', $event)->firstOrFail();
    }

    public function with(): array
    {
        $now = now();
        $canPay = $this->raceEvent->organizer->canTakePayments();

        return [
            'event' => $this->raceEvent,
            'races' => $this->raceEvent->races()->with(['raceClasses' => fn ($q) => $q->orderBy('start_at')->orderBy('name')])->orderBy('name')->get()
                ->map(fn ($race) => ['race' => $race, 'price' => $race->priceAt($now)])
                // A paid race waits until the organizer's Stripe account can take payments.
                ->map(fn ($r) => $r + ['soon' => $r['price'] > 0 && ! $canPay]),
            'currency' => $this->raceEvent->organizer->currency,
            'ageGroup' => $this->ageGroup(),
            'teamSize' => $this->selectedClass()?->team_size,
            // Earlier upplagor of the arrangemang, for the runners who look up last year's time.
            'earlier' => $this->raceEvent->series?->events()->whereKeyNot($this->raceEvent->id)->whereNotNull('results_public_at')
                ->where('date', '<', $this->raceEvent->date)->orderByDesc('date')->get() ?? collect(),
            'clubs' => Club::orderBy('name')->pluck('name'),
        ];
    }

    /** "Du tävlar i M45" once the class, birth date and gender are filled in, for a race with age groups (#92). */
    private function ageGroup(): ?string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->birthDate)) {
            return null;
        }

        return $this->selectedClass()?->ageGroupOf(new Registration(['birth_date' => $this->birthDate, 'gender' => $this->gender ?: null]));
    }

    private function selectedClass(): ?RaceClass
    {
        return $this->raceClassId
            ? $this->raceEvent->raceClasses()->with('race')->find((int) $this->raceClassId)?->setRelation('event', $this->raceEvent)
            : null;
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
            'acceptTerms' => ['accepted'],
        ]);

        $class = RaceClass::with('race')->findOrFail((int) $this->raceClassId);
        $price = $class->race?->priceAt(now());

        if ($price === null || ($price > 0 && ! $this->raceEvent->organizer->canTakePayments())) {
            $this->addError('raceClassId', __('registration.class_not_open'));

            return;
        }

        if ($class->team_size) {
            $this->teamName = trim($this->teamName);
            $this->members = array_map('trim', array_pad(array_slice(array_values($this->members), 0, $class->team_size), $class->team_size, ''));
            $this->validate(
                ['teamName' => ['required', 'string', 'max:100'], 'members.*' => ['required', 'string', 'max:100']],
                [],
                ['teamName' => __('registration.team_name'), 'members.*' => __('registration.team_member')],
            );
        }

        if ($class->gender && $class->gender !== $this->gender) {
            $this->addError('raceClassId', __('registration.wrong_gender'));

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
            'terms_accepted_at' => now(),
            'team_name' => $class->team_size ? $this->teamName : null,
            'team_members' => $class->team_size ? $this->members : null,
        ]);

        $confirmationUrl = $registration->confirmationUrl();

        if ($price === 0) {
            $registration->markPaid('free');
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
        <div class="flex gap-4">
            <flux:link :href="route('public.start-list', ['organizer' => $event->organizer->slug, 'event' => $event->slug])" class="text-sm">{{ __('onsite.start_list') }}</flux:link>
            @if ($event->results_public_at)
                <flux:link :href="route('public.results', ['organizer' => $event->organizer->slug, 'event' => $event->slug])" class="text-sm">{{ __('live.title') }}</flux:link>
            @endif
        </div>
    </div>

    <section class="flex flex-col gap-3">
        <flux:heading size="lg">{{ __('registration.classes') }}</flux:heading>
        @foreach ($races as ['race' => $race, 'price' => $price, 'soon' => $soon])
            <div wire:key="race-{{ $race->id }}" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="flex justify-between font-semibold">
                    <span>{{ $race->name }}</span>
                    <span>{{ match (true) { $price === null => __('registration.closed'), $soon => __('registration.opens_soon'), default => Money::format($price, $currency) } }}</span>
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

        <flux:select wire:model.live="raceClassId" :label="__('registration.class')" :placeholder="__('registration.choose_class')">
            @foreach ($races as ['race' => $race, 'price' => $price, 'soon' => $soon])
                @if ($price !== null && ! $soon)
                    @foreach ($race->raceClasses->filter(fn ($class) => ! $class->gender || ! $gender || $class->gender === $gender) as $class)
                        <option value="{{ $class->id }}">{{ $race->name }} – {{ $class->name }} ({{ Money::format($price, $currency) }})</option>
                    @endforeach
                @endif
            @endforeach
        </flux:select>

        @if ($ageGroup)
            <flux:text class="text-sm">{{ __('registration.your_age_group', ['group' => $ageGroup]) }}</flux:text>
        @endif

        @if ($teamSize)
            <div class="flex flex-col gap-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:input wire:model="teamName" :label="__('registration.team_name')" />
                <div class="grid gap-3 sm:grid-cols-2">
                    @for ($i = 0; $i < $teamSize; $i++)
                        <flux:input wire:key="member-{{ $i }}" wire:model="members.{{ $i }}" :label="__('registration.team_member_n', ['n' => $i + 1])" />
                    @endfor
                </div>
                <flux:text class="text-sm">{{ __('registration.team_help') }}</flux:text>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="firstName" :label="__('registration.first_name')" autocomplete="given-name" />
            <flux:input wire:model="lastName" :label="__('registration.last_name')" autocomplete="family-name" />
            <flux:input wire:model.live.blur="birthDate" type="date" :label="__('registration.birth_date')" autocomplete="bday" />
            <flux:select wire:model.live="gender" :label="__('registration.gender')" :placeholder="__('registration.gender')">
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

        <flux:text class="text-sm">{{ __('registration.publication_notice') }}
            <flux:link :href="route('public.terms', $event->organizer->slug)" target="_blank">{{ __('privacy.read_terms') }}</flux:link>
        </flux:text>
        <flux:checkbox wire:model="acceptTerms" :label="__('privacy.accept_terms')" />

        <div><flux:button type="submit" variant="primary">{{ __('registration.submit') }}</flux:button></div>
    </form>

    @if ($earlier->isNotEmpty())
        <section class="flex flex-col gap-2">
            <flux:heading size="lg">{{ __('registration.earlier_results') }}</flux:heading>
            <ul class="flex flex-col gap-1 text-sm">
                @foreach ($earlier as $edition)
                    <li wire:key="earlier-{{ $edition->id }}">
                        <flux:link :href="route('public.results', ['organizer' => $event->organizer->slug, 'event' => $edition->slug])">{{ $edition->name }}</flux:link>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
