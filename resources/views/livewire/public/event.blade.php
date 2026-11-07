<?php

use App\Models\Club;
use App\Support\Format;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Payments\StripeCheckout;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.public')] class extends Component {
    public const HALF_MARATHON_METERS = 21097;

    // A family signs up together and pays once (#109), as a third of the old system's registrations did.
    public const MAX_PARTICIPANTS = 20;

    public Event $raceEvent;

    // What the runner has filled in survives a reload or a step back from Stripe, per upplaga (#118).
    private const REMEMBERED = ['participants', 'email', 'phone'];

    /**
     * Each one who runs. raceId only steers which classes a participant's card shows; a team class (#31) adds
     * the team's name and members, with the participant as the team's contact.
     */
    public array $participants = [];

    public string $email = '';
    public string $phone = '';
    public bool $acceptTerms = false;

    // The arrangemang's slug shows its current upplaga, so the link stays the same every year; an upplaga's own slug still works.
    public function mount(string $organizer, string $event): void
    {
        $organizer = Organizer::where('slug', $organizer)->firstOrFail();
        $this->raceEvent = $organizer->series()->where('slug', $event)->first()?->current()
            ?? $organizer->events()->where('slug', $event)->firstOrFail();

        foreach (session($this->rememberKey(), []) as $field => $value) {
            if (in_array($field, self::REMEMBERED, true)) {
                $this->{$field} = $value;
            }
        }
        $this->participants = array_map(
            fn ($participant) => array_merge(self::blank(), array_intersect_key((array) $participant, self::blank())),
            array_slice(array_values((array) $this->participants), 0, self::MAX_PARTICIPANTS),
        ) ?: [self::blank()];
    }

    private static function blank(): array
    {
        return ['raceId' => '', 'raceClassId' => '', 'firstName' => '', 'lastName' => '', 'birthDate' => '', 'gender' => '', 'club' => '', 'teamName' => '', 'members' => []];
    }

    public function updated(string $property): void
    {
        // Another race for a participant: the class chosen in the old one no longer applies.
        if (preg_match('/^participants\.(\d+)\.raceId$/', $property, $m)) {
            $this->participants[$m[1]]['raceClassId'] = '';
        }

        $field = strtok($property, '.');
        if (in_array($field, self::REMEMBERED, true)) {
            session()->put("{$this->rememberKey()}.{$field}", $this->{$field});
        }
    }

    public function addParticipant(): void
    {
        if (count($this->participants) >= self::MAX_PARTICIPANTS) {
            return;
        }
        $this->participants[] = self::blank();
        $this->updated('participants');
        $this->js("document.getElementById('deltagare-".(count($this->participants) - 1)."')?.focus()");
    }

    public function removeParticipant(int $index): void
    {
        if (count($this->participants) > 1) {
            array_splice($this->participants, $index, 1);
            $this->resetErrorBag();
            $this->updated('participants');
        }
    }

    private function rememberKey(): string
    {
        return "registration.{$this->raceEvent->id}";
    }

    public function rendering($view): void
    {
        $view->title(__('public.title', ['page' => __('registration.title'), 'event' => $this->raceEvent->name, 'organizer' => $this->raceEvent->organizer->name]))
            ->layoutData(['organizer' => $this->raceEvent->organizer]);
    }

    public function with(): array
    {
        $now = now();
        $canPay = $this->raceEvent->organizer->canTakePayments();
        $classes = $this->classes();

        $races = $this->raceEvent->races()->with(['priceSteps', 'raceClasses'])->orderBy('name')->get()
            ->map(function ($race) use ($now, $canPay) {
                $today = $now->copy()->setTimezone($this->raceEvent->timezone)->toDateString();
                $steps = $race->priceSteps->filter(fn ($step) => $step->until->toDateString() >= $today)->values();
                $price = $race->priceAt($now);

                // A paid race waits until the organizer's Stripe account can take payments.
                return ['race' => $race, 'price' => $price, 'until' => $steps->first()?->until, 'next' => $steps->get(1), 'soon' => $price > 0 && ! $canPay];
            });

        // Per participant: the chosen class, its price now and the age group it gives (#92).
        $lines = collect($this->participants)->map(function ($participant) use ($classes, $races) {
            $class = $classes->get((int) $participant['raceClassId']);

            return [
                'class' => $class,
                'price' => $class ? $races->firstWhere('race.id', $class->race_id)['price'] ?? null : null,
                'ageGroup' => $class ? $this->ageGroup($class, $participant) : null,
            ];
        });

        return [
            'event' => $this->raceEvent,
            'races' => $races,
            'lines' => $lines,
            'currency' => $this->raceEvent->organizer->currency,
            // Earlier upplagor of the arrangemang, for the runners who look up last year's time.
            'earlier' => $this->raceEvent->series?->events()->whereKeyNot($this->raceEvent->id)->whereNotNull('results_public_at')
                ->where('date', '<', $this->raceEvent->date)->orderByDesc('date')->get() ?? collect(),
            'clubs' => Club::orderBy('name')->pluck('name'),
        ];
    }

    /** "Du tävlar i M45" once the class, birth date and gender are filled in, for a race with age groups (#92). */
    private function ageGroup(RaceClass $class, array $participant): ?string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $participant['birthDate'])) {
            return null;
        }

        return $class->ageGroupOf(new Registration(['birth_date' => $participant['birthDate'], 'gender' => $participant['gender'] ?: null]));
    }

    /** @return Collection<int, RaceClass> */
    private function classes(): Collection
    {
        return $this->raceEvent->raceClasses()->with('race')->get()
            ->each(fn ($class) => $class->setRelation('event', $this->raceEvent))
            ->keyBy('id');
    }

    public function register(): void
    {
        $this->email = trim($this->email);
        $this->phone = trim($this->phone);
        foreach ($this->participants as $i => $participant) {
            foreach (['firstName', 'lastName', 'club', 'teamName'] as $field) {
                $this->participants[$i][$field] = trim((string) $participant[$field]);
            }
            // "19910403", "1991 04 03" and "1991-4-3" are the same date as "1991-04-03".
            if (preg_match('/^(\d{4})\D*(\d{1,2})\D*(\d{1,2})$/', trim((string) $participant['birthDate']), $m)) {
                $this->participants[$i]['birthDate'] = sprintf('%s-%02d-%02d', $m[1], $m[2], $m[3]);
            }
        }

        $this->validate([
            'participants' => ['required', 'array', 'min:1', 'max:'.self::MAX_PARTICIPANTS],
            'participants.*.raceClassId' => ['required', Rule::exists('race_classes', 'id')->where('event_id', $this->raceEvent->id)],
            'participants.*.firstName' => ['required', 'string', 'max:100'],
            'participants.*.lastName' => ['required', 'string', 'max:100'],
            'participants.*.birthDate' => ['required', 'date_format:Y-m-d', 'before:today'],
            'participants.*.gender' => ['required', 'in:M,K'],
            'participants.*.club' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'acceptTerms' => ['accepted'],
        ], [], [
            'participants.*.raceClassId' => __('registration.class'),
            'participants.*.firstName' => __('registration.first_name'),
            'participants.*.lastName' => __('registration.last_name'),
            'participants.*.birthDate' => __('registration.birth_date'),
            'participants.*.gender' => __('registration.gender'),
            'participants.*.club' => __('registration.club'),
        ]);

        // Every participant is checked before anything is saved, and each error lands on its own participant.
        $classes = $this->classes();
        $canPay = $this->raceEvent->organizer->canTakePayments();
        $year = now()->setTimezone($this->raceEvent->timezone)->year;
        $prices = [];

        foreach ($this->participants as $i => $participant) {
            $class = $classes->get((int) $participant['raceClassId']);
            $price = $class->race?->priceAt(now());
            $prices[$i] = $price;

            if ($price === null || ($price > 0 && ! $canPay)) {
                $this->addError("participants.{$i}.raceClassId", __('registration.class_not_open'));
            } elseif ($class->gender && $class->gender !== $participant['gender']) {
                $this->addError("participants.{$i}.raceClassId", __('registration.wrong_gender'));
            }

            if ($class->distance_meters >= self::HALF_MARATHON_METERS && $year - (int) substr($participant['birthDate'], 0, 4) < 17) {
                $this->addError("participants.{$i}.birthDate", __('registration.min_age'));
            }

            if ($class->team_size) {
                $members = array_map(fn ($member) => trim((string) $member), array_pad(array_slice(array_values((array) $participant['members']), 0, $class->team_size), $class->team_size, ''));
                $this->participants[$i]['members'] = $members;
                if ($participant['teamName'] === '') {
                    $this->addError("participants.{$i}.teamName", __('validation.required', ['attribute' => __('registration.team_name')]));
                }
                foreach ($members as $n => $member) {
                    if ($member === '') {
                        $this->addError("participants.{$i}.members.{$n}", __('validation.required', ['attribute' => __('registration.team_member_n', ['n' => $n + 1])]));
                    }
                }
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $order = DB::transaction(function () use ($classes, $prices) {
            $ref = (string) Str::uuid();

            return collect($this->participants)->map(function ($participant, $i) use ($classes, $prices, $ref) {
                $class = $classes->get((int) $participant['raceClassId']);
                $club = Club::match($participant['club']);

                return $class->registrations()->create([
                    'order_ref' => $ref,
                    'first_name' => $participant['firstName'],
                    'last_name' => $participant['lastName'],
                    'birth_date' => $participant['birthDate'],
                    'gender' => $participant['gender'],
                    'club' => $club?->name ?? ($participant['club'] ?: null),
                    'club_id' => $club?->id,
                    'email' => $this->email,
                    'phone' => $this->phone ?: null,
                    'price' => $prices[$i],
                    'terms_accepted_at' => now(),
                    'team_name' => $class->team_size ? $participant['teamName'] : null,
                    'team_members' => $class->team_size ? $participant['members'] : null,
                ])->setRelation('raceClass', $class);
            });
        });

        $first = $order->first();
        $confirmationUrl = $first->confirmationUrl();

        if ($order->sum('price') === 0) {
            $first->markPaid('free');
            $this->redirect($confirmationUrl);

            return;
        }

        $eventUrl = route('public.event', ['organizer' => $this->raceEvent->organizer->slug, 'event' => $this->raceEvent->slug]);
        $session = app(StripeCheckout::class)->create($order, $confirmationUrl, $eventUrl);
        Registration::where('order_ref', $first->order_ref)->update(['stripe_checkout_session_id' => $session['id']]);
        $this->redirect($session['url']);
    }
}; ?>

@php($open = $races->contains(fn ($r) => $r['price'] !== null && ! $r['soon']))
@php($total = $lines->sum(fn ($line) => $line['price'] ?? 0))
@php($max = $this::MAX_PARTICIPANTS)
<div x-data="{ summaryInView: false }" class="flex flex-col gap-12">
    <header class="flex flex-col gap-4">
        <p class="text-[15px] text-ink-soft"><a href="{{ route('public.organizer', $event->organizer->slug) }}" class="underline decoration-contour hover:text-ink">{{ $event->organizer->name }}</a></p>
        <h1 class="text-[38px] leading-[42px] font-normal tracking-[-0.02em] text-balance sm:text-[52px] sm:leading-[56px]">{{ $event->name }}</h1>
        <p class="text-[17px] text-ink-soft sm:text-[19px]">{{ Format::date($event->date) }}@if ($event->city) · {{ $event->city }}@endif</p>
        <div class="flex flex-wrap items-center gap-2 pt-2">
            @if ($open)
                <a href="#steg-1" class="inline-flex h-11 items-center rounded-full bg-brand px-6 text-[15px] font-semibold text-brand-contrast hover:opacity-90">{{ __('registration.cta') }}</a>
            @endif
            <a href="{{ route('public.start-list', ['organizer' => $event->organizer->slug, 'event' => $event->slug]) }}" class="inline-flex h-11 items-center rounded-full bg-white px-5 text-[15px] font-semibold text-ink shadow-rest hover:shadow-hover">{{ __('onsite.start_list') }}</a>
            @if ($event->results_public_at)
                <a href="{{ route('public.results', ['organizer' => $event->organizer->slug, 'event' => $event->slug]) }}" class="inline-flex h-11 items-center rounded-full bg-white px-5 text-[15px] font-semibold text-ink shadow-rest hover:shadow-hover">{{ __('live.title') }}</a>
            @endif
        </div>
    </header>

    <form wire:submit="register" class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start" novalidate>
        <div class="flex flex-col gap-10">
            <section aria-labelledby="steg-1" class="flex scroll-mt-6 flex-col gap-4" id="steg-1-section">
                <h2 id="steg-1" class="flex scroll-mt-6 items-center gap-3 text-[21px] font-medium sm:text-[26px]">
                    <span aria-hidden="true" class="grid size-8 place-items-center rounded-full bg-brand text-[15px] font-semibold text-brand-contrast">1</span>
                    {{ __('registration.step_choose') }}
                </h2>

                @foreach ($races as ['race' => $race, 'price' => $racePrice, 'until' => $until, 'next' => $next, 'soon' => $soon])
                    @php($available = $racePrice !== null && ! $soon)
                    <fieldset wire:key="race-{{ $race->id }}" class="rounded-card bg-white p-5 shadow-rest sm:p-7" @disabled(! $available)>
                        <legend class="sr-only">{{ $race->name }}</legend>
                        <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-2">
                            <h3 class="text-[21px] font-medium" aria-hidden="true">{{ $race->name }}</h3>
                            <div class="sm:text-right">
                                @if ($available)
                                    <p class="text-[21px] font-medium tabular-nums">{{ Money::format($racePrice, $currency) }}</p>
                                    @if ($next)
                                        <p class="text-[14px] text-ink-soft">{{ __('registration.price_then', ['date' => Format::shortDate($until), 'price' => Money::format($next->amount, $currency)]) }}</p>
                                    @elseif ($until)
                                        <p class="text-[14px] text-ink-soft">{{ __('registration.open_until', ['date' => Format::shortDate($until)]) }}</p>
                                    @endif
                                @else
                                    <p class="rounded-full bg-peach-light px-3 py-1 text-[14px] font-medium text-peach-deep">{{ $soon ? __('registration.opens_soon') : __('registration.closed') }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="mt-5 flex flex-wrap gap-2">
                            @foreach (Format::classOrder($race->raceClasses) as $class)
                                <label wire:key="class-{{ $class->id }}" class="group relative">
                                    <input type="radio" name="participants.0.raceClassId" value="{{ $class->id }}" wire:model.live="participants.0.raceClassId" class="peer sr-only" @disabled(! $available)>
                                    <span class="flex min-h-11 cursor-pointer flex-col justify-center rounded-full border-[1.5px] border-contour bg-white px-4 py-1.5 leading-tight transition-colors peer-checked:border-brand peer-checked:bg-brand peer-checked:text-brand-contrast peer-focus-visible:outline-3 peer-focus-visible:outline-offset-3 peer-focus-visible:outline-brand peer-disabled:cursor-not-allowed peer-disabled:opacity-60 hover:border-ink-soft">
                                        <span class="text-[15px] font-medium">{{ $class->name }}</span>
                                        <span class="text-[13px] opacity-80">{{ Format::time($class->start_at->setTimezone($event->timezone)) }}@if ($class->team_size) · {{ __('events.team_of', ['size' => $class->team_size]) }}@endif</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </section>

            <section aria-labelledby="steg-2" class="flex flex-col gap-4">
                    <h2 id="steg-2" class="flex scroll-mt-6 items-center gap-3 text-[21px] font-medium sm:text-[26px]">
                        <span aria-hidden="true" class="grid size-8 place-items-center rounded-full bg-brand text-[15px] font-semibold text-brand-contrast">2</span>
                        {{ count($participants) > 1 ? __('registration.step_who_many') : ($lines[0]['class']?->team_size ? __('registration.step_team') : __('registration.step_who')) }}
                    </h2>
                    @if (! $lines[0]['class'])
                        <p class="rounded-card bg-white/60 px-5 py-4 text-[15px] text-ink-soft sm:px-7">{{ __('registration.who_after_class') }}</p>
                    @else
                    @foreach ($participants as $i => $participant)
                        @php($line = $lines[$i])
                        @php($teamSize = $line['class']?->team_size)
                        <div wire:key="deltagare-{{ $i }}" role="group" aria-labelledby="deltagare-{{ $i }}" class="flex flex-col gap-5 rounded-card bg-white p-5 shadow-rest sm:p-7">
                            <div class="flex items-baseline justify-between gap-4">
                                <h3 id="deltagare-{{ $i }}" tabindex="-1" class="text-[17px] font-medium outline-none">{{ __('registration.participant_n', ['n' => $i + 1]) }}</h3>
                                @if (count($participants) > 1)
                                    <button type="button" wire:click="removeParticipant({{ $i }})" class="text-[15px] text-ink-soft underline decoration-contour hover:text-ink">{{ __('registration.remove') }}<span class="sr-only"> {{ __('registration.participant_n', ['n' => $i + 1]) }}</span></button>
                                @endif
                            </div>

                            @if ($i === 0)
                                {{-- The first participant's class is the one pressed on the race cards above. --}}
                                <p class="-mt-3 text-[15px] text-ink-soft">{{ $line['class']->race?->name }} – {{ $line['class']->name }} · <a href="#steg-1" class="underline decoration-contour hover:text-ink">{{ __('registration.change_class') }}</a></p>
                            @else
                                {{-- The same pills as on the race cards, one race at a time: never one long list of every class. --}}
                                @php($raceId = (int) ($participant['raceId'] ?: $line['class']?->race_id))
                                @if ($races->count() > 1)
                                    <fieldset class="flex flex-col">
                                        <legend class="mb-2 text-sm leading-tight font-medium text-zinc-800">{{ __('registration.race') }}</legend>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($races as ['race' => $race, 'price' => $racePrice, 'soon' => $soon])
                                                @php($available = $racePrice !== null && ! $soon)
                                                <label wire:key="lopp-{{ $i }}-{{ $race->id }}">
                                                    <input type="radio" name="participants.{{ $i }}.raceId" value="{{ $race->id }}" wire:model.live="participants.{{ $i }}.raceId" @checked($raceId === $race->id) class="peer sr-only" @disabled(! $available)>
                                                    <span class="flex min-h-11 cursor-pointer items-center gap-2 rounded-full border-[1.5px] border-contour bg-white px-4 text-[15px] font-medium transition-colors peer-checked:border-ink peer-checked:bg-ink peer-checked:text-white peer-focus-visible:outline-3 peer-focus-visible:outline-offset-3 peer-focus-visible:outline-brand peer-disabled:cursor-not-allowed peer-disabled:opacity-60 hover:border-ink-soft">
                                                        {{ $race->name }}@if ($available)<span class="font-normal tabular-nums opacity-80">{{ Money::format($racePrice, $currency) }}</span>@endif
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                @else
                                    @php($raceId = $races->first()['race']->id)
                                @endif
                                @if ($raceId)
                                    @php($raceRow = $races->firstWhere('race.id', $raceId))
                                    <fieldset class="flex flex-col">
                                        <legend class="mb-2 text-sm leading-tight font-medium text-zinc-800">{{ __('registration.class') }}</legend>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach (Format::classOrder($raceRow['race']->raceClasses) as $class)
                                                <label wire:key="klass-{{ $i }}-{{ $class->id }}">
                                                    <input type="radio" name="participants.{{ $i }}.raceClassId" value="{{ $class->id }}" wire:model.live="participants.{{ $i }}.raceClassId" class="peer sr-only" @disabled($raceRow['price'] === null || $raceRow['soon'])>
                                                    <span class="flex min-h-11 cursor-pointer flex-col justify-center rounded-full border-[1.5px] border-contour bg-white px-4 py-1.5 leading-tight transition-colors peer-checked:border-brand peer-checked:bg-brand peer-checked:text-brand-contrast peer-focus-visible:outline-3 peer-focus-visible:outline-offset-3 peer-focus-visible:outline-brand peer-disabled:cursor-not-allowed peer-disabled:opacity-60 hover:border-ink-soft">
                                                        <span class="text-[15px] font-medium">{{ $class->name }}</span>
                                                        <span class="text-[13px] opacity-80">{{ Format::time($class->start_at->setTimezone($event->timezone)) }}@if ($class->team_size) · {{ __('events.team_of', ['size' => $class->team_size]) }}@endif</span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                @endif
                            @endif
                            @error("participants.{$i}.raceClassId")<p class="-mt-2 text-[15px] font-medium text-danger">{{ $message }}</p>@enderror

                            @if ($teamSize)
                                <flux:input wire:model="participants.{{ $i }}.teamName" :label="__('registration.team_name')" />
                                <div class="grid gap-4 sm:grid-cols-2">
                                    @for ($n = 0; $n < $teamSize; $n++)
                                        <flux:input wire:key="member-{{ $i }}-{{ $n }}" wire:model="participants.{{ $i }}.members.{{ $n }}" :label="__('registration.team_member_n', ['n' => $n + 1])" autocomplete="off" />
                                    @endfor
                                </div>
                                <flux:text class="text-[15px]">{{ __('registration.team_help') }}</flux:text>
                                <h4 class="pt-2 text-[17px] font-medium">{{ __('registration.team_contact') }}</h4>
                            @endif

                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:input wire:model="participants.{{ $i }}.firstName" :label="__('registration.first_name')" :autocomplete="$i === 0 ? 'given-name' : 'off'" />
                                <flux:input wire:model="participants.{{ $i }}.lastName" :label="__('registration.last_name')" :autocomplete="$i === 0 ? 'family-name' : 'off'" />
                                <flux:input wire:model.live.blur="participants.{{ $i }}.birthDate" :label="__('registration.birth_date')" :description:trailing="__('registration.birth_date_help')" placeholder="ÅÅÅÅ-MM-DD" inputmode="numeric" :autocomplete="$i === 0 ? 'bday' : 'off'" maxlength="10" />
                                <fieldset class="flex flex-col">
                                    <legend class="mb-2 text-sm leading-tight font-medium text-zinc-800">{{ __('registration.gender') }}</legend>
                                    <div class="flex gap-2">
                                        @foreach (['K' => __('registration.gender_k'), 'M' => __('registration.gender_m')] as $value => $label)
                                            <label class="flex-1">
                                                <input type="radio" name="participants.{{ $i }}.gender" value="{{ $value }}" wire:model.live="participants.{{ $i }}.gender" class="peer sr-only">
                                                <span class="flex h-12 cursor-pointer items-center justify-center rounded-full border-[1.5px] border-contour text-base font-medium peer-checked:border-brand peer-checked:bg-brand peer-checked:text-brand-contrast peer-focus-visible:outline-3 peer-focus-visible:outline-offset-3 peer-focus-visible:outline-brand hover:border-ink-soft">{{ $label }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error("participants.{$i}.gender")<p class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                                </fieldset>
                            </div>
                            @if ($line['ageGroup'])
                                <p class="text-[15px] text-ink-soft" aria-live="polite">{{ __('registration.your_age_group', ['group' => $line['ageGroup']]) }}</p>
                            @endif

                            <flux:input wire:model="participants.{{ $i }}.club" list="clubs" autocomplete="off" :label="__('registration.club_optional')" :description:trailing="__('registration.club_help')" />
                        </div>
                    @endforeach
                    <datalist id="clubs">
                        @foreach ($clubs as $name)
                            <option value="{{ $name }}">
                        @endforeach
                    </datalist>

                    @if (count($participants) < $max)
                        <button type="button" wire:click="addParticipant" class="inline-flex h-11 items-center gap-2 self-start rounded-full bg-white px-5 text-[15px] font-semibold text-ink shadow-rest hover:shadow-hover">
                            <flux:icon.plus class="size-4" />{{ __('registration.add_participant') }}
                        </button>
                    @endif

                    <div role="group" aria-labelledby="kontakt" class="flex flex-col gap-5 rounded-card bg-white p-5 shadow-rest sm:p-7">
                        <h3 id="kontakt" class="text-[17px] font-medium">{{ __('registration.contact') }}</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model="email" type="email" :label="__('registration.email')" :description:trailing="__('registration.email_help')" autocomplete="email" />
                            <flux:input wire:model="phone" type="tel" inputmode="tel" :label="__('registration.phone_optional')" autocomplete="tel" />
                        </div>
                    </div>
                    @endif
            </section>
        </div>

        <aside aria-labelledby="steg-3" x-intersect:enter="summaryInView = true" x-intersect:leave="summaryInView = false" class="flex scroll-mt-6 flex-col gap-4 lg:sticky lg:top-6" id="steg-3-section">
            <h2 id="steg-3" class="flex scroll-mt-6 items-center gap-3 text-[21px] font-medium sm:text-[26px]">
                <span aria-hidden="true" class="grid size-8 place-items-center rounded-full bg-brand text-[15px] font-semibold text-brand-contrast">3</span>
                {{ __('registration.step_pay') }}
            </h2>
            <div class="flex flex-col gap-5 rounded-card bg-white p-5 shadow-rest sm:p-7" aria-live="polite">
                @if ($lines[0]['class'])
                    <ul class="flex flex-col gap-3">
                        @foreach ($lines as $i => $line)
                            <li wire:key="rad-{{ $i }}" class="flex items-baseline justify-between gap-4">
                                <span class="flex flex-col">
                                    <span class="text-[17px] font-medium">{{ trim($participants[$i]['firstName'].' '.$participants[$i]['lastName']) ?: __('registration.participant_n', ['n' => $i + 1]) }}</span>
                                    <span class="text-[15px] text-ink-soft">{{ $line['class'] ? $line['class']->race?->name.' – '.$line['class']->name.', '.Format::time($line['class']->start_at->setTimezone($event->timezone)) : __('registration.no_class') }}</span>
                                </span>
                                <span class="text-[17px] tabular-nums">{{ $line['price'] === null ? '–' : Money::format($line['price'], $currency) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="-mt-2 text-[15px] text-ink-soft">{{ Format::date($event->date) }}</p>
                    <div class="flex items-baseline justify-between border-t border-contour pt-4">
                        <span class="text-[15px] text-ink-soft">{{ __('registration.total') }}</span>
                        <span class="text-[26px] font-medium tabular-nums">{{ Money::format($total, $currency) }}</span>
                    </div>

                    <div class="flex flex-col gap-3">
                        <flux:checkbox wire:model="acceptTerms" :label="__('privacy.accept_terms')" />
                        <p class="text-[14px] text-ink-soft">{{ __('registration.publication_notice') }}
                            <a href="{{ route('public.terms', $event->organizer->slug) }}" target="_blank" class="underline">{{ __('privacy.read_terms') }}<span class="sr-only"> {{ __('public.new_tab') }}</span></a>
                        </p>
                    </div>

                    @if ($errors->any())
                        {{-- Where the runner pressed pay; each line takes them to its field, naming the participant when there are several. --}}
                        <div role="alert" tabindex="-1" x-init="$el.focus()" class="rounded-[18px] bg-danger-surface px-5 py-4 text-danger outline-none">
                            <h3 class="text-[15px] font-semibold">{{ __('registration.errors_heading') }}</h3>
                            <ul class="mt-2 flex flex-col gap-1 text-[15px]">
                                {{-- In the order of the form: participant by participant, then contact and terms. --}}
                                @php($fields = ['raceClassId', 'teamName', 'members', 'firstName', 'lastName', 'birthDate', 'gender', 'club'])
                                @foreach (collect($errors->messages())->sortBy(fn ($m, $field) => preg_match('/^participants\.(\d+)\.(\w+)(?:\.(\d+))?/', $field, $p)
                                    ? sprintf('%03d-%02d-%02d', $p[1], array_search($p[2], $fields), $p[3] ?? 0)
                                    : '999-'.array_search($field, ['email', 'phone', 'acceptTerms'])) as $field => $messages)
                                    @php($who = count($participants) > 1 && preg_match('/^participants\.(\d+)\./', $field, $m) ? __('registration.participant_n', ['n' => $m[1] + 1]).': ' : '')
                                    <li>
                                        <a href="#" class="underline" @click.prevent="
                                            const field = [...document.querySelectorAll('input, select, textarea')]
                                                .find(el => [...el.attributes].some(a => a.name.startsWith('wire:model') && a.value === @js($field)))
                                                ?? document.querySelector('[name=' + CSS.escape(@js($field)) + ']');
                                            field?.scrollIntoView({ block: 'center' }); field?.focus();
                                        ">{{ $who.$messages[0] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <button type="submit" class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-brand px-6 text-[15px] font-semibold text-brand-contrast hover:opacity-90 disabled:opacity-60" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="register">{{ $total ? __('registration.pay', ['price' => Money::format($total, $currency)]) : __('registration.submit_free') }}</span>
                        <span wire:loading wire:target="register">{{ __('registration.working') }}</span>
                    </button>
                    @if ($total)
                        <p class="text-[13px] text-ink-soft">{{ __('registration.secure_payment') }}</p>
                    @endif
                @else
                    <p class="text-[15px] text-ink-soft">{{ $open ? __('registration.choose_first') : __('registration.not_open') }}</p>
                @endif
            </div>
        </aside>
    </form>

    @if ($earlier->isNotEmpty())
        <section aria-labelledby="tidigare" class="flex flex-col gap-3">
            <h2 id="tidigare" class="text-[21px] font-medium">{{ __('registration.earlier_results') }}</h2>
            <ul class="flex flex-wrap gap-2">
                @foreach ($earlier as $edition)
                    <li wire:key="earlier-{{ $edition->id }}">
                        <a href="{{ route('public.results', ['organizer' => $event->organizer->slug, 'event' => $edition->slug]) }}" class="inline-flex h-11 items-center rounded-full bg-white px-5 text-[15px] font-medium shadow-rest hover:shadow-hover">{{ $edition->name }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- On a phone, once a class is chosen, the way to pay stays at hand until the summary is in view. --}}
    @if ($lines[0]['class'])
        <div x-show="! summaryInView" x-transition.opacity class="fixed inset-x-0 bottom-0 z-40 border-t border-contour bg-white/95 px-4 py-3 lg:hidden">
            <a href="#steg-3" class="flex h-12 w-full items-center justify-center rounded-full bg-brand text-[15px] font-semibold text-brand-contrast">{{ $total ? __('registration.pay', ['price' => Money::format($total, $currency)]) : __('registration.submit_free') }}</a>
        </div>
    @endif
</div>
