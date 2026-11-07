<?php

use App\Models\Club;
use App\Support\Format;
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

    // What the runner has filled in survives a reload or a step back from Stripe, per upplaga (#118).
    private const REMEMBERED = ['raceClassId', 'firstName', 'lastName', 'birthDate', 'gender', 'club', 'email', 'phone', 'teamName', 'members'];

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

        foreach (session($this->rememberKey(), []) as $field => $value) {
            if (in_array($field, self::REMEMBERED, true)) {
                $this->{$field} = $value;
            }
        }
    }

    public function updated(string $property): void
    {
        $field = strtok($property, '.');
        if (in_array($field, self::REMEMBERED, true)) {
            session()->put("{$this->rememberKey()}.{$field}", $this->{$field});
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

        return [
            'event' => $this->raceEvent,
            'races' => $this->raceEvent->races()->with(['priceSteps', 'raceClasses'])->orderBy('name')->get()
                ->map(function ($race) use ($now, $canPay) {
                    $today = $now->copy()->setTimezone($this->raceEvent->timezone)->toDateString();
                    $steps = $race->priceSteps->filter(fn ($step) => $step->until->toDateString() >= $today)->values();
                    $price = $race->priceAt($now);

                    // A paid race waits until the organizer's Stripe account can take payments.
                    return ['race' => $race, 'price' => $price, 'until' => $steps->first()?->until, 'next' => $steps->get(1), 'soon' => $price > 0 && ! $canPay];
                }),
            'selected' => $this->selectedClass(),
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
        // "19910403", "1991 04 03" and "1991-4-3" are the same date as "1991-04-03".
        if (preg_match('/^(\d{4})\D*(\d{1,2})\D*(\d{1,2})$/', trim($this->birthDate), $m)) {
            $this->birthDate = sprintf('%s-%02d-%02d', $m[1], $m[2], $m[3]);
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

@php($open = $races->contains(fn ($r) => $r['price'] !== null && ! $r['soon']))
@php($price = $selected ? $races->firstWhere('race.id', $selected->race_id)['price'] ?? null : null)
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
                @error('raceClassId')<p class="text-[15px] font-medium text-danger">{{ $message }}</p>@enderror

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
                                    <input type="radio" name="raceClassId" value="{{ $class->id }}" wire:model.live="raceClassId" class="peer sr-only" @disabled(! $available)>
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
                        {{ $teamSize ? __('registration.step_team') : __('registration.step_who') }}
                    </h2>
                    @if (! $selected)
                        <p class="rounded-card bg-white/60 px-5 py-4 text-[15px] text-ink-soft sm:px-7">{{ __('registration.who_after_class') }}</p>
                    @else
                    <div class="flex flex-col gap-5 rounded-card bg-white p-5 shadow-rest sm:p-7">
                        @if ($teamSize)
                            <flux:input wire:model="teamName" :label="__('registration.team_name')" />
                            <div class="grid gap-4 sm:grid-cols-2">
                                @for ($i = 0; $i < $teamSize; $i++)
                                    <flux:input wire:key="member-{{ $i }}" wire:model="members.{{ $i }}" :label="__('registration.team_member_n', ['n' => $i + 1])" autocomplete="off" />
                                @endfor
                            </div>
                            <flux:text class="text-[15px]">{{ __('registration.team_help') }}</flux:text>
                            <h3 class="pt-2 text-[17px] font-medium">{{ __('registration.team_contact') }}</h3>
                        @endif

                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model="firstName" :label="__('registration.first_name')" autocomplete="given-name" />
                            <flux:input wire:model="lastName" :label="__('registration.last_name')" autocomplete="family-name" />
                            <flux:input wire:model.live.blur="birthDate" :label="__('registration.birth_date')" :description:trailing="__('registration.birth_date_help')" placeholder="ÅÅÅÅ-MM-DD" inputmode="numeric" autocomplete="bday" maxlength="10" />
                            <fieldset class="flex flex-col">
                                <legend class="mb-2 text-sm leading-tight font-medium text-zinc-800">{{ __('registration.gender') }}</legend>
                                <div class="flex gap-2">
                                    @foreach (['K' => __('registration.gender_k'), 'M' => __('registration.gender_m')] as $value => $label)
                                        <label class="flex-1">
                                            <input type="radio" name="gender" value="{{ $value }}" wire:model.live="gender" class="peer sr-only">
                                            <span class="flex h-12 cursor-pointer items-center justify-center rounded-full border-[1.5px] border-contour text-base font-medium peer-checked:border-brand peer-checked:bg-brand peer-checked:text-brand-contrast peer-focus-visible:outline-3 peer-focus-visible:outline-offset-3 peer-focus-visible:outline-brand hover:border-ink-soft">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('gender')<p class="text-sm font-medium text-danger">{{ $message }}</p>@enderror
                            </fieldset>
                        </div>
                        @if ($ageGroup)
                            <p class="text-[15px] text-ink-soft" aria-live="polite">{{ __('registration.your_age_group', ['group' => $ageGroup]) }}</p>
                        @endif

                        <flux:input wire:model="club" list="clubs" autocomplete="off" :label="__('registration.club_optional')" :description:trailing="__('registration.club_help')" />
                        <datalist id="clubs">
                            @foreach ($clubs as $name)
                                <option value="{{ $name }}">
                            @endforeach
                        </datalist>

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
                @if ($selected)
                    <dl class="flex flex-col gap-1">
                        <dt class="sr-only">{{ __('registration.class') }}</dt>
                        <dd class="text-[17px] font-medium">{{ $selected->race?->name }} – {{ $selected->name }}</dd>
                        <dt class="sr-only">{{ __('receipt.start') }}</dt>
                        <dd class="text-[15px] text-ink-soft">{{ Format::date($event->date) }}, {{ Format::time($selected->start_at->setTimezone($event->timezone)) }}</dd>
                    </dl>
                    <div class="flex items-baseline justify-between border-t border-contour pt-4">
                        <span class="text-[15px] text-ink-soft">{{ __('registration.total') }}</span>
                        <span class="text-[26px] font-medium tabular-nums">{{ $price === null ? '–' : Money::format($price, $currency) }}</span>
                    </div>

                    <div class="flex flex-col gap-3">
                        <flux:checkbox wire:model="acceptTerms" :label="__('privacy.accept_terms')" />
                        <p class="text-[14px] text-ink-soft">{{ __('registration.publication_notice') }}
                            <a href="{{ route('public.terms', $event->organizer->slug) }}" target="_blank" class="underline">{{ __('privacy.read_terms') }}<span class="sr-only"> {{ __('public.new_tab') }}</span></a>
                        </p>
                    </div>

                    @if ($errors->any())
                        {{-- Where the runner pressed pay; each line takes them to its field. --}}
                        <div role="alert" tabindex="-1" x-init="$el.focus()" class="rounded-[18px] bg-danger-surface px-5 py-4 text-danger outline-none">
                            <h3 class="text-[15px] font-semibold">{{ __('registration.errors_heading') }}</h3>
                            <ul class="mt-2 flex flex-col gap-1 text-[15px]">
                                @foreach ($errors->messages() as $field => $messages)
                                    <li>
                                        <a href="#" class="underline" @click.prevent="
                                            const field = [...document.querySelectorAll('input, select, textarea')]
                                                .find(el => [...el.attributes].some(a => a.name.startsWith('wire:model') && a.value === @js($field)))
                                                ?? document.querySelector('[name=' + @js($field) + ']');
                                            field?.scrollIntoView({ block: 'center' }); field?.focus();
                                        ">{{ $messages[0] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <button type="submit" class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-brand px-6 text-[15px] font-semibold text-brand-contrast hover:opacity-90 disabled:opacity-60" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="register">{{ $price ? __('registration.pay', ['price' => Money::format($price, $currency)]) : __('registration.submit_free') }}</span>
                        <span wire:loading wire:target="register">{{ __('registration.working') }}</span>
                    </button>
                    @if ($price)
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
    @if ($selected && $price !== null)
        <div x-show="! summaryInView" x-transition.opacity class="fixed inset-x-0 bottom-0 z-40 border-t border-contour bg-white/95 px-4 py-3 lg:hidden">
            <a href="#steg-3" class="flex h-12 w-full items-center justify-center rounded-full bg-brand text-[15px] font-semibold text-brand-contrast">{{ $price ? __('registration.pay', ['price' => Money::format($price, $currency)]) : __('registration.submit_free') }}</a>
        </div>
    @endif
</div>
