<?php

use App\Models\Campaign;
use App\Models\CampaignCode;
use App\Exceptions\CodeTaken;
use App\Models\Club;
use App\Support\Format;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Payments\StripeCheckout;
use App\Support\Money;
use App\Support\Page;
use App\Support\Theme;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
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

    // A campaign's shared code from its link (?kod=ATEA2027, #85), given to every participant added.
    #[Url(as: 'kod', except: '')]
    public string $linkCode = '';

    // The draft page behind a signed preview link.
    public bool $preview = false;

    // The arrangemang's slug shows its current upplaga, so the link stays the same every year; an upplaga's own slug still works.
    public function mount(string $organizer, string $event): void
    {
        $organizer = Organizer::where('slug', $organizer)->firstOrFail();
        $this->raceEvent = $organizer->series()->where('slug', $event)->first()?->current()
            ?? $organizer->events()->where('slug', $event)->firstOrFail();
        $this->preview = request()->boolean('forhandsvisning') && request()->hasValidSignature();

        foreach (session($this->rememberKey(), []) as $field => $value) {
            if (in_array($field, self::REMEMBERED, true)) {
                $this->{$field} = $value;
            }
        }
        $this->participants = array_map(
            fn ($participant) => array_merge(self::blank(), array_intersect_key((array) $participant, self::blank())),
            array_slice(array_values((array) $this->participants), 0, self::MAX_PARTICIPANTS),
        ) ?: [self::blank()];

        // The campaign link someone just followed wins over a code remembered from an earlier visit.
        $this->linkCode = Campaign::normalize($this->linkCode);
        if ($this->linkCode !== '') {
            foreach (array_keys($this->participants) as $i) {
                $this->participants[$i]['code'] = $this->linkCode;
            }
        }
    }

    private static function blank(): array
    {
        return ['raceId' => '', 'raceClassId' => '', 'firstName' => '', 'lastName' => '', 'birthDate' => '', 'gender' => '', 'club' => '', 'teamName' => '', 'members' => [], 'code' => ''];
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
        $this->participants[] = ['code' => $this->linkCode] + self::blank();
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
            ->layoutData(['organizer' => $this->raceEvent->organizer, 'event' => $this->raceEvent]);
    }

    public function with(): array
    {
        $now = now();
        $canPay = $this->raceEvent->organizer->canTakePayments();
        $classes = $this->classes();

        $races = $this->raceEvent->races()->with(['priceSteps', 'raceClasses'])->ordered()->get()
            ->map(function ($race) use ($now, $canPay) {
                $today = $now->copy()->setTimezone($this->raceEvent->timezone)->toDateString();
                $steps = $race->priceSteps->filter(fn ($step) => $step->until->toDateString() >= $today)->values();
                // Steps after race day never come: registration closes when the race has been run.
                $last = $steps->search(fn ($step) => $step->until->greaterThanOrEqualTo($this->raceEvent->date));
                $steps = $last === false ? $steps : $steps->take($last + 1);
                // A cancelled upplaga takes no registrations (#117).
                $price = $this->raceEvent->cancelled_at ? null : $race->priceAt($now);

                // A paid race waits until the organizer's Stripe account can take payments.
                return ['race' => $race, 'price' => $price, 'until' => $steps->first() ? min($steps->first()->until, $this->raceEvent->date) : null, 'next' => $steps->get(1), 'soon' => $price > 0 && ! $canPay];
            });

        // Per participant: the chosen class, its price now (a campaign's, with a code, #85) and the age group it gives (#92).
        $lines = collect($this->participants)->map(function ($participant) use ($classes, $races) {
            $class = $classes->get((int) $participant['raceClassId']);
            $offer = $class && trim((string) $participant['code']) !== '' ? Campaign::resolve($this->raceEvent, $class, $participant['code']) : null;

            return [
                'class' => $class,
                'price' => $offer ? $offer['price'] : ($class ? $races->firstWhere('race.id', $class->race_id)['price'] ?? null : null),
                'campaign' => $offer['campaign'] ?? null,
                'codeError' => $offer['error'] ?? null,
                'ageGroup' => $class ? $this->ageGroup($class, $participant) : null,
            ];
        });

        $open = $races->contains(fn ($r) => $r['price'] !== null && ! $r['soon']);
        $edition = Page::edition($this->raceEvent, $open);
        // The page's blocks in the state the upplaga is in; earlier results only once there are any.
        $blocks = array_values(array_filter(Page::for($this->raceEvent, $this->preview), fn ($block) => Page::shows($block, $edition['state'])
            && ($block['type'] !== 'history' || $edition['history']->isNotEmpty())));

        return [
            'event' => $this->raceEvent,
            'edition' => $this->raceEvent,
            'organizer' => $this->raceEvent->organizer,
            'races' => $races,
            'lines' => $lines,
            'open' => $open,
            'currency' => $this->raceEvent->organizer->currency,
            'clubs' => Club::orderBy('name')->pluck('name'),
            'blocks' => $blocks,
            // The hero's way to the registration, while it is open and on the page.
            'registering' => $open && collect($blocks)->contains('type', 'registration'),
            'editor' => false,
            'next' => null,
            'forms' => Page::forms($this->raceEvent),
            'logo' => Theme::logoUrl(Theme::for($this->raceEvent->organizer, $this->raceEvent, $this->preview)),
            ...$edition,
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
            'participants.*.code' => ['nullable', 'string', 'max:40'],
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
            'participants.*.code' => __('registration.code'),
        ]);

        if ($this->raceEvent->cancelled_at) {
            $this->addError('participants.0.raceClassId', __('registration.class_not_open'));

            return;
        }

        // Every participant is checked before anything is saved, and each error lands on its own participant.
        $classes = $this->classes();
        $canPay = $this->raceEvent->organizer->canTakePayments();
        $year = now()->setTimezone($this->raceEvent->timezone)->year;
        $offers = [];

        foreach ($this->participants as $i => $participant) {
            $class = $classes->get((int) $participant['raceClassId']);
            // A code sets the price before any payment begins (#85); the same campaign earlier in the order takes places too.
            $sameCampaign = collect($offers)->filter(fn ($offer) => $offer['campaign'])->countBy(fn ($offer) => $offer['campaign']->id);
            $offer = Campaign::resolve($this->raceEvent, $class, $participant['code']);
            if ($offer['campaign'] && ($already = $sameCampaign->get($offer['campaign']->id))) {
                $offer = Campaign::resolve($this->raceEvent, $class, $participant['code'], $already);
            }
            $offers[$i] = $offer;
            $price = $offer['price'];

            if ($offer['error']) {
                $this->addError("participants.{$i}.code", __("registration.{$offer['error']}"));
            }

            if ($price === null || ($price > 0 && ! $canPay && ! $offer['campaign']?->invoice)) {
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

        // An order is billed or paid by card, never both: the invoice goes to the campaign's company or school.
        $invoice = collect($offers)->contains(fn ($offer) => $offer['campaign']?->invoice);
        foreach ($offers as $i => $offer) {
            if ($invoice && ! $offer['campaign']?->invoice) {
                $this->addError("participants.{$i}.code", __('registration.invoice_mix'));
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        try {
            $order = DB::transaction(function () use ($classes, $offers, $invoice) {
                $ref = (string) Str::uuid();

                return collect($this->participants)->map(function ($participant, $i) use ($classes, $offers, $invoice, $ref) {
                    $class = $classes->get((int) $participant['raceClassId']);
                    $club = Club::match($participant['club']);
                    $offer = $offers[$i];

                    $registration = $class->registrations()->create([
                        'order_ref' => $ref,
                        'campaign_id' => $offer['campaign']?->id,
                        'campaign_code' => $offer['campaign'] ? Campaign::normalize($participant['code']) : null,
                        'first_name' => $participant['firstName'],
                        'last_name' => $participant['lastName'],
                        'birth_date' => $participant['birthDate'],
                        'gender' => $participant['gender'],
                        'club' => $club?->name ?? ($participant['club'] ?: null),
                        'club_id' => $club?->id,
                        'email' => $this->email,
                        'phone' => $this->phone ?: null,
                        'price' => $offer['price'],
                        'payment_method' => $invoice ? 'invoice' : null,
                        'terms_accepted_at' => now(),
                        'team_name' => $class->team_size ? $participant['teamName'] : null,
                        'team_members' => $class->team_size ? $participant['members'] : null,
                    ])->setRelation('raceClass', $class);

                    // Held for this registration; another order got it first if nothing changes.
                    if ($offer['code'] && ! CampaignCode::whereKey($offer['code']->id)->free()->update([
                        'registration_id' => $registration->id, 'reserved_at' => now(), 'used_at' => $invoice ? now() : null,
                    ])) {
                        throw new CodeTaken($i);
                    }

                    return $registration;
                });
            });
        } catch (CodeTaken $taken) {
            $this->addError("participants.{$taken->participant}.code", __('registration.code_used'));

            return;
        }

        $first = $order->first();
        $confirmationUrl = $first->confirmationUrl();

        // The club sends the invoice from its own books (#85); the registrations count as participants meanwhile.
        if ($invoice) {
            $this->redirect($confirmationUrl);

            return;
        }

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

@php($total = $lines->sum(fn ($line) => $line['price'] ?? 0))
@php($invoiced = $lines->first(fn ($line) => $line['campaign']?->invoice)['campaign'] ?? null)
@php($max = $this::MAX_PARTICIPANTS)
<div x-data="{ summaryInView: false }" class="flex flex-col gap-6">
    {{-- What search engines and Löpning.se's calendar read (#117, #120): the race, its status and its prices, from the same data as the page. --}}
    <script type="application/ld+json">{!! json_encode(App\Support\SchemaOrg::event($event, $races), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    {{-- The way back, and a cancelled or moved upplaga said first of all (#117), whatever the page's blocks. --}}
    <x-breadcrumbs :event="$event" />
    <x-event-status :event="$event" />

    {{-- The page the organizer built (#115), or name, registration and earlier results. --}}
    <div>
        @include('blocks.page')
    </div>

    {{-- On a phone, once a class is chosen, the way to pay stays at hand until the summary is in view. --}}
    @if ($lines[0]['class'])
        <div x-show="! summaryInView" x-transition.opacity class="fixed inset-x-0 bottom-0 z-40 border-t border-contour bg-white/95 px-4 py-3 lg:hidden">
            <a href="#steg-3" class="flex h-12 w-full items-center justify-center rounded-pill bg-brand text-[15px] font-semibold text-brand-contrast">{{ $invoiced ? __('registration.submit_invoice') : ($total ? __('registration.pay', ['price' => Money::format($total, $currency)]) : __('registration.submit_free')) }}</a>
        </div>
    @endif
</div>
