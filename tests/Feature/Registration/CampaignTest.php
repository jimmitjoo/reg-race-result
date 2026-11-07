<?php

use App\Models\Campaign;
use App\Models\CampaignCode;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\Series;
use App\Models\User;
use App\Payments\StripeCheckout;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

// #85: campaigns with own prices, codes, a place cap and invoice instead of card.
beforeEach(function () {
    $this->travelTo(now()->setTimezone('Europe/Stockholm')->setDate(2026, 11, 15)->setTime(12, 0));
    Mail::fake();
});

/** One race at 300 kr with a class for individuals and one for teams of three. */
function campaignEvent(): Collection
{
    $organizer = Organizer::factory()->create(['slug' => 'hogby-if', 'currency' => 'SEK', 'fee_basis_points' => 0, 'fee_fixed' => 300]);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'slug' => 'kalmarmilen-2026', 'date' => '2026-12-31']);
    $race = Race::factory()->for($event)->create(['name' => 'Kalmarmilen']);
    $race->priceSteps()->create(['until' => '2026-12-30', 'amount' => 30000]);

    return collect([
        'event' => $event,
        'race' => $race,
        'women' => RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'name' => 'Kvinnor 10 km', 'gender' => 'K']),
        'team' => RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'name' => 'Lag', 'team_size' => 3]),
    ]);
}

function campaignForm(RaceClass $class, string $code)
{
    $page = Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'kalmarmilen-2026'])
        ->set('participants.0.raceClassId', (string) $class->id)
        ->set('participants.0.firstName', 'Anna')->set('participants.0.lastName', 'Karlsson')
        ->set('participants.0.birthDate', '1991-04-03')->set('participants.0.gender', 'K')
        ->set('email', 'anna@example.com')->set('acceptTerms', true);
    if ($code !== '') {
        $page->set('participants.0.code', $code);
    }
    if ($class->team_size) {
        $page->set('participants.0.teamName', 'Kontoret')->set('participants.0.members', ['A', 'B', 'C']);
    }

    return $page;
}

function stripeReturns(): void
{
    test()->mock(StripeCheckout::class)->shouldReceive('create')->andReturn(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/x']);
}

it('gives a registration with the campaign code the campaign price, from the field or the link', function () {
    $e = campaignEvent();
    $e['event']->campaigns()->create(['name' => 'Atea', 'code' => 'ATEA2027', 'prices' => [$e['race']->id => 20000]]);
    stripeReturns();

    campaignForm($e['women'], ' atea 2027 ')
        ->assertSee(__('registration.code_applied', ['campaign' => 'Atea']))
        ->assertSee(__('registration.pay', ['price' => Money::format(20000, 'SEK')]))
        ->call('register')->assertHasNoErrors();
    $this->get('/hogby-if/kalmarmilen-2026?kod=atea2027')->assertOk()->assertSee('ATEA2027');

    expect(Registration::pluck('price')->all())->toBe([20000])
        ->and(Registration::pluck('campaign_code')->unique()->all())->toBe(['ATEA2027']);
    campaignForm($e['women'], 'NOPE')->call('register')->assertHasErrors(['participants.0.code']);
});

it('takes a single-use code once, not after its date, and only for the kind of registration it is for', function () {
    $e = campaignEvent();
    $campaign = $e['event']->campaigns()->create(['name' => 'Medlemmar', 'single_use' => true, 'prices' => [$e['race']->id => 0]]);
    $code = fn (array $attributes = []) => $campaign->codes()->create(['code' => CampaignCode::generate()] + $attributes)->code;
    $this->mock(StripeCheckout::class)->shouldNotReceive('create');

    $once = $code();
    campaignForm($e['women'], $once)->call('register')->assertHasNoErrors();
    expect(Registration::sole()->paid_at)->not->toBeNull()->and(CampaignCode::firstWhere('code', $once)->used_at)->not->toBeNull();
    campaignForm($e['women'], $once)->call('register')->assertHasErrors(['participants.0.code'])->assertSee(__('registration.code_used'));

    campaignForm($e['women'], $code(['valid_until' => '2026-11-14']))->call('register')->assertSee(__('registration.code_expired'));
    campaignForm($e['women'], $code(['applies_to' => 'team']))->call('register')->assertSee(__('registration.code_team'));
    campaignForm($e['team'], $code(['applies_to' => 'individual']))->call('register')->assertSee(__('registration.code_individual'));
    campaignForm($e['team'], $code(['applies_to' => 'team']))->call('register')->assertHasNoErrors();
    expect(Registration::count())->toBe(2);
});

it('frees a single-use code from an abandoned payment after 30 minutes and uses it when paid, whatever the way of paying', function () {
    $e = campaignEvent();
    $campaign = $e['event']->campaigns()->create(['name' => 'Medlemmar', 'single_use' => true, 'prices' => [$e['race']->id => 25000]]);
    $code = $campaign->codes()->create(['code' => 'KOD12345']);
    stripeReturns();

    campaignForm($e['women'], 'KOD12345')->call('register')->assertHasNoErrors();
    $abandoned = Registration::sole();
    expect($code->fresh())->registration_id->toBe($abandoned->id)->used_at->toBeNull();
    campaignForm($e['women'], 'KOD12345')->call('register')->assertSee(__('registration.code_used'));

    $this->travel(31)->minutes();
    campaignForm($e['women'], 'KOD12345')->call('register')->assertHasNoErrors();
    $second = Registration::latest('id')->first();
    expect($code->fresh()->registration_id)->toBe($second->id);

    $second->markPaid('swish_onsite');
    expect($code->fresh()->used_at)->not->toBeNull()
        ->and((new StripeCheckout)->params(collect([$abandoned]), 'https://ok', 'https://x')['expires_at'])->toBe(now()->addMinutes(31)->timestamp);
});

it('closes a campaign when its places are taken, counting registrations on their way to paying', function () {
    $e = campaignEvent();
    $campaign = $e['event']->campaigns()->create(['name' => 'Atea', 'code' => 'ATEA', 'cap' => 2]);
    stripeReturns();

    campaignForm($e['women'], 'ATEA')->call('register')->assertHasNoErrors();
    campaignForm($e['women'], 'ATEA')->call('register')->assertHasNoErrors();
    campaignForm($e['women'], 'ATEA')->assertSee(__('registration.campaign_full'))->call('register')->assertHasErrors(['participants.0.code']);

    // One abandoned payment frees its place after 30 minutes; a paid one keeps it.
    Registration::first()->update(['paid_at' => now()]);
    $this->travel(31)->minutes();
    expect($campaign->taken())->toBe(1);
    campaignForm($e['women'], 'ATEA')->call('register')->assertHasNoErrors();
});

it('registers through an invoice campaign without card, as a participant who shows as unpaid', function () {
    $e = campaignEvent();
    $e['event']->organizer->update(['stripe_charges_enabled' => false]);
    $e['event']->campaigns()->create(['name' => 'Skolan', 'code' => 'SKOLAN', 'invoice' => true, 'invoice_name' => 'Kalmar kommun', 'prices' => [$e['race']->id => 5000]]);
    $this->mock(StripeCheckout::class)->shouldNotReceive('create');

    campaignForm($e['women'], 'SKOLAN')
        ->assertSee(__('registration.submit_invoice'))
        ->call('register')->assertHasNoErrors()->assertRedirectContains('/registrations/');

    $registration = Registration::sole();
    expect($registration)->payment_method->toBe('invoice')->paid_at->toBeNull()->price->toBe(5000);
    Mail::assertNothingQueued();

    $this->get($registration->confirmationUrl())->assertOk()
        ->assertSee(__('registration.confirmation_heading'))
        ->assertSee(__('registration.invoiced_by', ['name' => 'Kalmar kommun']))
        ->assertDontSee(__('registration.try_again'));

    // An order is billed or paid by card, never both.
    $mixed = campaignForm($e['women'], 'SKOLAN')->call('addParticipant')
        ->set('participants.1.raceClassId', (string) $e['women']->id)->set('participants.1.code', '')
        ->set('participants.1.firstName', 'Bo')->set('participants.1.lastName', 'Ek')->set('participants.1.birthDate', '1990-01-01')->set('participants.1.gender', 'K');
    $mixed->call('register')->assertHasErrors(['participants.1.code']);
});

it('counts invoiced but unpaid registrations as participants and other unpaid ones not', function () {
    $e = campaignEvent();
    Registration::factory()->for($e['women'])->unpaid()->create(['first_name' => 'Fakturerad', 'payment_method' => 'invoice']);
    Registration::factory()->for($e['women'])->unpaid()->create(['first_name' => 'Obetald']);
    Registration::factory()->for($e['women'])->create(['first_name' => 'Betald']);

    expect($e['event']->registrations()->pluck('first_name')->sort()->values()->all())->toBe(['Betald', 'Fakturerad'])
        ->and($e['women']->registrations()->count())->toBe(2);
    $this->get('/hogby-if/kalmarmilen-2026/start-list')->assertSee('Fakturerad')->assertDontSee('Obetald');
});

it('marks an invoice campaign paid for all its registrations, with no mail', function () {
    $e = campaignEvent();
    $campaign = $e['event']->campaigns()->create(['name' => 'Skolan', 'code' => 'SKOLAN', 'invoice' => true, 'invoice_name' => 'Kalmar kommun']);
    $registrations = Registration::factory()->for($e['women'])->unpaid()->count(3)->create(['campaign_id' => $campaign->id, 'payment_method' => 'invoice', 'price' => 5000]);
    $this->actingAs(User::factory()->create(['organizer_id' => $e['event']->organizer_id]));

    Volt::test('events.campaigns', ['event' => $e['event']])
        ->assertSee('Skolan')->assertSee(__('campaigns.invoice_unpaid'))
        ->call('markInvoicePaid', $campaign->id)
        ->assertSee(__('campaigns.invoice_paid'));

    expect(Registration::whereNull('paid_at')->count())->toBe(0)->and($campaign->fresh()->invoice_paid_at)->not->toBeNull();
    Mail::assertNothingQueued();

    $this->get(route('events.campaigns.invoice', [$e['event'], $campaign, 'format' => 'csv']))->assertOk()
        ->assertSee('Kalmar kommun')->assertSee($registrations->first()->first_name)->assertSee('150,00');
});

it('reports invoiced, paid and the platform fee per organizer for a month', function () {
    $e = campaignEvent();
    Registration::factory()->for($e['women'])->unpaid()->count(2)->create(['payment_method' => 'invoice', 'price' => 5000, 'created_at' => '2026-10-10']);
    Registration::factory()->for($e['women'])->create(['payment_method' => 'invoice', 'price' => 5000, 'created_at' => '2026-10-10', 'paid_at' => '2026-10-20']);
    Registration::factory()->for($e['women'])->create(['payment_method' => 'stripe', 'price' => 30000, 'created_at' => '2026-10-10', 'paid_at' => '2026-10-10']);

    $this->artisan('invoices:report 2026-10')->assertSuccessful()
        ->expectsTable(['Arrangör', 'Fakturerat', 'Markerat betalt', 'Plattformsavgift'], [[
            $e['event']->organizer->name, Money::format(15000, 'SEK'), Money::format(5000, 'SEK'), Money::format(300, 'SEK'),
        ]]);
});

it('lets the club set up campaigns, hand out single-use codes and copies them to next year', function () {
    $e = campaignEvent();
    $series = Series::create(['organizer_id' => $e['event']->organizer_id, 'name' => 'Kalmarmilen']);
    $e['event']->update(['series_id' => $series->id]);
    $this->actingAs(User::factory()->create(['organizer_id' => $e['event']->organizer_id]));

    $page = Volt::test('events.campaigns', ['event' => $e['event']])
        ->call('edit')->set('name', 'Atea')->set('code', 'atea 2027')->set("prices.{$e['race']->id}", '200')->set('cap', '50')->call('save')->assertHasNoErrors()
        ->call('edit')->set('name', 'Medlemmar')->set('kind', 'single')->call('save')->assertHasNoErrors();
    $members = Campaign::firstWhere('name', 'Medlemmar');
    $page->set("generate.{$members->id}.count", 5)->set("generate.{$members->id}.validUntil", '2026-12-01')->call('generateCodes', $members->id);

    expect(Campaign::firstWhere('name', 'Atea'))->code->toBe('ATEA2027')->prices->toBe([(string) $e['race']->id => 20000])->cap->toBe(50)
        ->and($members->codes()->count())->toBe(5);
    $this->get(route('events.campaigns.codes', [$e['event'], $members]))->assertOk()->assertSee($members->codes()->first()->code);

    $next = $series->newEdition('2027-12-31');
    $copy = $next->campaigns()->firstWhere('name', 'Atea');
    expect($copy->code)->toBe('ATEA2027')->and($copy->prices)->toBe([(string) $next->races()->first()->id => 20000])
        ->and($next->campaigns()->firstWhere('name', 'Medlemmar')->codes()->count())->toBe(0);
});
